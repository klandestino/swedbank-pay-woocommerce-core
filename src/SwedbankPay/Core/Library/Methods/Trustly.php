<?php

namespace SwedbankPay\Core\Library\Methods;

use SwedbankPay\Core\Api\Response;
use SwedbankPay\Core\Exception;
use SwedbankPay\Core\Log\LogLevel;
use SwedbankPay\Core\Order;

trait Trustly
{
    /**
     * Initiate Trustly Payment
     *
     * @param mixed $orderId
     *
     * @return Response
     * @throws Exception
     */
    public function initiateTrustlyPayment($orderId)
    {
        /** @var Order $order */
        $order = $this->getOrder($orderId);

        $urls = $this->getPlatformUrls($orderId);

        // Process payment
        $params = [
            'payment' => [
                'operation' => self::OPERATION_PURCHASE,
                'intent' => self::INTENT_SALE,
                'currency' => $order->getCurrency(),
                'prices' => [
                    [
                        'type' => TrustlyInterface::PRICE_TYPE_TRUSTLY,
                        'amount' => $order->getAmountInCents(),
                        'vatAmount' => $order->getVatAmountInCents()
                    ]
                ],
                'description' => $order->getDescription(),
                'payerReference' => $order->getPayerReference(),
                'userAgent' => $order->getHttpUserAgent(),
                'language' => $order->getLanguage(),
                'urls' => [
                    'hostUrls' => $urls->getHostUrls(),
                    'completeUrl' => $urls->getCompleteUrl(),
                    'cancelUrl' => $urls->getCancelUrl(),
                    'callbackUrl' => $urls->getCallbackUrl(),
                    'termsOfServiceUrl' => $urls->getTermsUrl(),
                    'logoUrl'     => $urls->getLogoUrl(),
                ],
                'payeeInfo' => $this->getPayeeInfo($orderId)->toArray(),
                'riskIndicator' => $this->getRiskIndicator($orderId)->toArray(),
                'prefillInfo' => [
                    'firstName' => $order->getBillingFirstName(),
                    'lastName' => $order->getBillingLastName(),
                ],
                'metadata' => [
                    'order_id' => $order->getOrderId()
                ],
            ]
        ];

        try {
            $result = $this->request('POST', TrustlyInterface::PAYMENTS_URL, $params);
        } catch (\Exception $e) {
            $this->log(LogLevel::DEBUG, sprintf('%s::%s: API Exception: %s', __CLASS__, __METHOD__, $e->getMessage()));

            throw new Exception($e->getMessage());
        }

        return $result;
    }

}
