<?php

declare(strict_types=1);

namespace App\Application\Order\DTO;

final readonly class CreateOrderItemInput
{
    public function __construct(
        public string $productId,
        public int $quantity
    ) {
    }
}
