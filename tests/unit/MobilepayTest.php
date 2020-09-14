<?php

use SwedbankPay\Core\Api\Response;

class MobilepayTest extends TestCase
{
    public function testInitiateMobilepayPayment()
    {
        $this->gateway->currency = 'DDK';
        $result = $this->core->initiateMobilepayPayment(1, '+4581555853');
        $this->assertInstanceOf(Response::class, $result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('operations', $result);
        $this->assertIsArray($result['payment']);
        $this->assertArrayHasKey('id', $result['payment']);
        $this->assertArrayHasKey('number', $result['payment']);
        $this->assertArrayHasKey('operation', $result['payment']);
        $this->assertArrayHasKey('intent', $result['payment']);
        $this->assertArrayHasKey('state', $result['payment']);
        $this->assertEquals('MobilePay', $result['payment']['instrument']);
        $this->assertIsString($result->getOperationByRel('update-payment-abort'));
        $this->assertIsString($result->getOperationByRel('redirect-authorization'));

        return $result;
    }

    /**
     * @depends MobilepayTest::testInitiateMobilepayPayment
     * @param Response $response
     */
    public function testMobilepayPaymentPaymentPage(Response $response)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $response->getOperationByRel('update-payment-abort'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $this->assertIsString($output);
        $this->assertEquals(200, $code);
    }
}
