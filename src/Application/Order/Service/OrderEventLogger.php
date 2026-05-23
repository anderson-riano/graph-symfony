<?php

declare(strict_types=1);

namespace App\Application\Order\Service;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderEventLog;
use App\Domain\Order\Enum\OrderEventName;

final class OrderEventLogger
{
    public function log(
        Order $order,
        OrderEventName $eventName,
        ?array $payload = null,
        ?string $errorMessage = null
    ): void {
        $event = new OrderEventLog(
            $order,
            $eventName,
            $order->getStatus(),
            $payload,
            $errorMessage
        );

        $order->addEvent($event);
    }
}
