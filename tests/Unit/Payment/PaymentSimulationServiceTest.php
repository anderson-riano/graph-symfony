<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment;

use App\Application\Payment\Service\PaymentSimulationService;
use App\Domain\Order\Entity\Order;
use PHPUnit\Framework\TestCase;

final class PaymentSimulationServiceTest extends TestCase
{
    public function testApprovesPayment(): void
    {
        $service = new PaymentSimulationService();
        $order = new Order('Approved Customer', 'approved@test.com');

        self::assertTrue($service->isApproved($order));
    }

    public function testRejectsPaymentWhenEmailContainsFail(): void
    {
        $service = new PaymentSimulationService();
        $order = new Order('Rejected Customer', 'fail@test.com');

        self::assertFalse($service->isApproved($order));
    }
}
