<?php

declare(strict_types=1);

namespace App\Application\Payment\Service;

use App\Domain\Order\Entity\Order;

final class PaymentSimulationService
{
    public function isApproved(Order $order): bool
    {
        return !str_contains(strtolower($order->getCustomerEmail()), 'fail');
    }
}
