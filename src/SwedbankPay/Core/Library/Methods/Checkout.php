<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;
use SwedbankPay\Core\OrderInterface;

trait Checkout
{
    /**
     * Initiate Payment Order Purchase.
     *
     * @param mixed $orderId
     * @param string|null $consumerProfileRef
     *
     * @return Response
     * @throws Exception
     */
    public function initiatePaymentOrderPurchase($orderId, $consumerProfileRef = null)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        $params = [
            'paymentorder' => [
                'operation' => self::OPERATION_PURCHASE,
                'currency' => $order->getCurrency(),
                'amount' => $order->getAmountInCents(),
                'vatAmount' => $order->getVatAmountInCents(),
                'description' => $order->getDescription(),
                'userAgent' => $order->getHttpUserAgent(),
                'language' => $order->getLanguage(),
                'generateRecurrenceToken' => false,
                'disablePaymentMenu' => false,
                'urls' => [
                    'hostUrls' => $urls->getHostUrls(),
                    'completeUrl' => $urls->getCompleteUrl(),
                    'cancelUrl' => $urls->getCancelUrl(),
                    'callbackUrl' => $urls->getCallbackUrl(),
                    'termsOfServiceUrl' => $this->configuration->getTermsUrl()
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'payer' => $order->getCardHolderInformation(),
                'orderItems' => $order->getItems(),
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
                'items' => [
                    [
                        'creditCard' => [
                            'rejectCreditCards' => $this->configuration->getRejectCreditCards(),
                            'rejectDebitCards' => $this->configuration->getRejectDebitCards(),
                            'rejectConsumerCards' => $this->configuration->getRejectConsumerCards(),
                            'rejectCorporateCards' => $this->configuration->getRejectCorporateCards()
                        ]
                    ]
                ]
            ]
        ];

        // Add consumerProfileRef if exists
        if (!empty($consumerProfileRef)) {
            $params['paymentorder']['payer'] = [
                'consumerProfileRef' => $consumerProfileRef
            ];
        }

        try {
            $result = $this->request('POST', '/psp/paymentorders', $params);
        } catch (\SwedbankPay\Core\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage(), $e->getCode(), null, $e->getProblems());
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * @param string $updateUrl
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function updatePaymentOrder($updateUrl, $orderId)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        // Update Order
        $params = [
            'paymentorder' => [
                'operation' => 'UpdateOrder',
                'amount' => $order->getAmountInCents(),
                'vatAmount' => $order->getVatAmountInCents(),
                'orderItems' => $order->getItems()
            ]
        ];

        try {
            $result = $this->request('PATCH', $updateUrl, $params);
        } catch (\SwedbankPay\Core\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage(), $e->getCode(), null, $e->getProblems());
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * Get Payment ID url by Payment Order.
     *
     * @param string $paymentOrderId
     *
     * @return string|false
     */
    public function getPaymentIdByPaymentOrder($paymentOrderId)
    {
        $payments = $this->request('GET', $paymentOrderId . '/payments');
        if (isset($payments['payments'])) {
            foreach ($payments['payments']['paymentList'] as $payment) {
                // Use the first item
                return $payment['id'];
            }
        }

        return false;
    }

    /**
     * Get Current Payment Resource.
     * The currentpayment resource displays the payment that are active within the payment order container.
     *
     * @param string $paymentOrderId
     * @return array|false
     */
    public function getCheckoutCurrentPayment($paymentOrderId)
    {
        $payment = $this->request('GET', $paymentOrderId . '/currentpayment');

        return isset($payment['payment']) ? $payment['payment'] : false;
    }

    /**
     * Capture Checkout.
     *
     * @param mixed $orderId
     * @param int|float $amount
     * @param int|float $vatAmount
     * @param array $items
     *
     * @return Response
     * @throws Exception
     */
    public function captureCheckout($orderId, $amount = null, $vatAmount = 0, array $items = [])
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if (!$amount) {
            $amount = $order->getAmount();
            $vatAmount = $order->getVatAmount();
        }

        $paymentOrderId = $order->getPaymentOrderId();
        if (empty($paymentOrderId)) {
            throw new Exception('Unable to get the payment order ID');
        }

        /** @var Response $result */
        $result = $this->request('GET', $paymentOrderId);
        $href = $result->getOperationByRel('create-paymentorder-capture');
        if (empty($href)) {
            throw new Exception('Capture is unavailable');
        }

        $params = [
            'transaction' => [
                'amount'         => (int)bcmul(100, $amount),
                'vatAmount'      => (int)bcmul(100, $vatAmount),
                'description'    => sprintf( 'Capture for Order #%s', $order->getOrderId() ),
                'payeeReference' => $this->generatePayeeReference($orderId),
                'orderItems' => $items
            ]
        ];

        $result = $this->request( 'POST', $href, $params );

        // Save transaction
        $transaction = $result['capture']['transaction'];
        $this->saveTransaction($orderId, $transaction);

        switch ($transaction['state']) {
            case 'Completed':
                $this->updateOrderStatus(OrderInterface::STATUS_CAPTURED, 'Transaction is captured.');
                break;
            case 'Initialized':
                $this->updateOrderStatus(OrderInterface::STATUS_AUTHORIZED,
                    sprintf('Transaction capture status: %s.', $transaction['state']));
                break;
            case 'Failed':
                $message = isset($transaction['failedReason']) ? $transaction['failedReason'] : 'Capture is failed.';
                throw new Exception($message);
            default:
                throw new Exception('Capture is failed.');
        }

        return $result;
    }

    /**
     * Cancel Checkout.
     *
     * @param mixed $orderId
     * @param int|float|null $amount
     * @param int|float $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function cancelCheckout($orderId, $amount = null, $vatAmount = 0)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if ($amount > 0 && $amount !== $order->getAmount()) {
            throw new Exception('Partial cancellation isn\'t available.');
        }

        if ($vatAmount > 0 && $vatAmount !== $order->getVatAmount()) {
            throw new Exception('Partial cancellation isn\'t available.');
        }

        $paymentOrderId = $order->getPaymentOrderId();
        if (empty($paymentOrderId)) {
            throw new Exception('Unable to get the payment order ID');
        }

        /** @var Response $result */
        $result = $this->request('GET', $paymentOrderId);
        $href = $result->getOperationByRel('create-paymentorder-cancel');
        if (empty($href)) {
            throw new Exception('Cancellation is unavailable');
        }

        $params = [
            'transaction' => [
                'description'    => sprintf( 'Cancellation for Order #%s', $order->getOrderId() ),
                'payeeReference' => $this->generatePayeeReference($orderId),
            ]
        ];

        $result = $this->request( 'POST', $href, $params );

        // Save transaction
        $transaction = $result['cancellation']['transaction'];
        $this->saveTransaction($orderId, $transaction);

        switch ($transaction['state']) {
            case 'Completed':
                $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED, 'Transaction is cancelled.');
                break;
            case 'Initialized':
            case 'AwaitingActivity':
                $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED,
                    sprintf('Transaction cancellation status: %s.', $transaction['state']));
                break;
            case 'Failed':
                $message = isset($transaction['failedReason']) ? $transaction['failedReason'] : 'Cancellation is failed.';
                throw new Exception($message);
            default:
                throw new Exception('Capture is failed.');
        }

        return $result;
    }

    /**
     * Refund Checkout.
     *
     * @param mixed $orderId
     * @param int|float|null $amount
     * @param int|float $vatAmount
     *
     * @return Response
     * @throws Exception
     */
    public function refundCheckout($orderId, $amount = null, $vatAmount = 0)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        if (!$amount) {
            $amount = $order->getAmount();
            $vatAmount = $order->getVatAmount();
        }

        $paymentOrderId = $order->getPaymentOrderId();
        if (empty($paymentOrderId)) {
            throw new Exception('Unable to get the payment order ID');
        }

        /** @var Response $result */
        $result = $this->request('GET', $paymentOrderId);
        $href = $result->getOperationByRel('create-paymentorder-reversal');
        if (empty($href)) {
            throw new Exception('Refund is unavailable');
        }

        // @todo Partial Refund

        $params = [
            'transaction' => [
                'description' => sprintf( 'Refund for Order #%s', $order->getOrderId() ),
                'amount' => (int)bcmul(100, $amount),
                'vatAmount' => (int)bcmul(100, $vatAmount),
                'payeeReference' => $this->generatePayeeReference($orderId),
                'receiptReference' => $this->generatePayeeReference($orderId),
                'orderItems' => $order->getItems()
            ]
        ];

        $result = $this->request('POST', $href, $params);

        // Save transaction
        $transaction = $result['reversal']['transaction'];
        $this->saveTransaction($orderId, $transaction);

        switch ($transaction['state']) {
            case 'Completed':
                $this->updateOrderStatus(
                    OrderInterface::STATUS_REFUNDED,
                    sprintf('Refunded: %s.', $amount)
                );
                break;
            case 'Initialized':
            case 'AwaitingActivity':
                $this->updateOrderStatus(OrderInterface::STATUS_CANCELLED,
                    sprintf('Transaction reversal status: %s.', $transaction['state'])
                );
                break;
            case 'Failed':
                $message = isset($transaction['failedReason']) ? $transaction['failedReason'] : 'Refund is failed.';
                throw new Exception($message);
            default:
                throw new Exception('Refund is failed.');
        }

        return $result;
    }
}
