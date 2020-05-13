<?php

use SwedbankPay\Core\Core;
use SwedbankPay\Core\Exception;

class OrderActionTest extends TestCase
{
    public function test_capture() {
        $this->expectException(Exception::class);
        $this->core->capture(1, 125, 25);
    }

    public function test_cancel() {
        $this->expectException(Exception::class);
        $this->core->cancel(1, 125, 25);
    }

    public function test_refund() {
        $this->expectException(Exception::class);
        $this->core->refund(1, 125, 25);
    }

    public function test_abort() {
        $this->expectException(Exception::class);
        $this->core->abort(1);
    }
}
