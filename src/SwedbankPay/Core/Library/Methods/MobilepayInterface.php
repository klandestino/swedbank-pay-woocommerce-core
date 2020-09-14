<?php

namespace SwedbankPay\Core\Library\Methods;

/**
 * Interface MobilepayInterface
 * @package SwedbankPay\Core\Library\Methods
 */
interface MobilepayInterface
{
    const PRICE_TYPE_MOBILEPAY = 'MobilePay';
    const PRICE_TYPE_VISA = 'Visa';
    const PRICE_TYPE_MC = 'MC';
    const PRICE_TYPE_MAESTRO = 'Maestro';
    const PRICE_TYPE_DANKORT = 'Dankort';

    const PAYMENT_URL = '/psp/mobilepay/payments';
}
