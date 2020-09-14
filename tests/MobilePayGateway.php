<?php

class MobilePayGateway extends Gateway
{
    /**
     * @var string
     */
    public $merchant_token = MERCHANT_TOKEN_MOBILEPAY;

    /**
     * @var string
     */
    public $payee_id = PAYEE_ID_MOBILEPAY;
}
