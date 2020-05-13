<?php

use SwedbankPay\Core\Api\Response;

class ConsumerTest extends TestCase
{
    public function testInitiateConsumerSession()
    {
        $result = $this->core->initiateConsumerSession('SE');
        $this->assertInstanceOf(Response::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('operations', $result);
        $this->assertIsString($result->getOperationByRel('redirect-consumer-identification'));
        $this->assertIsString($result->getOperationByRel('view-consumer-identification'));
    }
}
