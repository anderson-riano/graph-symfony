<?php

declare(strict_types=1);

namespace App\Application\Inventory\Service;

use App\Domain\Order\Entity\Order;

final class InventoryReservationService
{
    public function reserve(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $item->getProduct()->reserveStock($item->getQuantity());
        }
    }

    public function release(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $item->getProduct()->releaseStock($item->getQuantity());
        }
    }
}
