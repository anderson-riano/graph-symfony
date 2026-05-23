<?php

declare(strict_types=1);

namespace App\Application\Notification\Service;

use App\Domain\Order\Entity\Order;

final class NotificationService
{
    public function sendOrderConfirmedNotification(Order $order): void
    {
        // Simulation placeholder: in production, this calls an external provider.
        $order->getId();
    }
}
