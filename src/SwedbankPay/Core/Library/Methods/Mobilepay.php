<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;

/**
 * Trait Mobilepay
 * @package SwedbankPay\Core\Library\Methods
 */
trait Mobilepay
{
    /**
     * Initiate Mobilepay Payment
     *
     * @param mixed $orderId
     * @param string $phone Pre-fill phone, optional
     *
     * @return Response
     * @throws Exception
     */
    public function initiateMobilepayPayment($orderId, $phone = '')
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        // Process payment
        $params = [
            'payment' => [
                'operation' => self::OPERATION_PURCHASE,
                'intent' => self::INTENT_AUTHORIZATION,
                'currency' => $order->getCurrency(),
                'prices' => [
                    [
                        'type' => 'Visa',
                        'amount' => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents()
                    ],
                    [
                        'type' => 'MasterCard',
                        'amount' => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents()
                    ],
                    [
                        'type'      => 'Maestro',
                        'amount'    => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents(),
                    ],
                    [
                        'type'      => 'Dankort',
                        'amount'    => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents(),
                    ],
                ],
                'description' => $order->getDescription(),
                'payerReference' => $order->getPayerReference(),
                'userAgent' => $order->getHttpUserAgent(),
                'language' => $order->getLanguage(),
                'urls' => [
                    'completeUrl' => $urls->getCompleteUrl(),
                    'cancelUrl' => $urls->getCancelUrl(),
                    'callbackUrl' => $urls->getCallbackUrl(),
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'riskIndicator' => $this->getRiskIndicator($orderId)->toArray(),
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ],
            'mobilepay' => [
                'shoplogoUrl' => $urls->getLogoUrl()
            ]
        ];

        if (!empty($phone)) {
            $params['payment']['prefillInfo'] = [
                'msisdn' => $phone
            ];
        }

        try {
            $result = $this->request('POST', MobilepayInterface::PAYMENT_URL, $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }
}
