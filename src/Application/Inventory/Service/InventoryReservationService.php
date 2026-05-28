<?php

declare(strict_types=1);

namespace App\Application\Inventory\Service;

use App\Domain\Order\Entity\Order;
use App\Domain\Product\Entity\Product;
use App\Domain\Product\Exception\ProductUnavailableException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class InventoryReservationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function reserve(Order $order): void
    {
        foreach ($this->groupByProduct($order) as $productId => $quantity) {
            $product = $this->entityManager->find(Product::class, Uuid::fromString($productId), LockMode::PESSIMISTIC_WRITE);
            if (!$product instanceof Product) {
                throw new ProductUnavailableException(sprintf('Product "%s" does not exist.', $productId));
            }

            $product->reserveStock($quantity);
        }
    }

    public function release(Order $order): void
    {
        foreach ($this->groupByProduct($order) as $productId => $quantity) {
            $product = $this->entityManager->find(Product::class, Uuid::fromString($productId), LockMode::PESSIMISTIC_WRITE);
            if (!$product instanceof Product) {
                continue;
            }

            $product->releaseStock($quantity);
        }
    }

    /**
     * @return array<string, int>
     */
    private function groupByProduct(Order $order): array
    {
        $grouped = [];

        foreach ($order->getItems() as $item) {
            $productId = $item->getProduct()->getId()->toRfc4122();
            $grouped[$productId] = ($grouped[$productId] ?? 0) + $item->getQuantity();
        }

        return $grouped;
    }
}
