<?php

declare(strict_types=1);

namespace App\Application\Order\DTO;

final readonly class CreateOrderInput
{
    /**
     * @param list<CreateOrderItemInput> $items
     */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $items
    ) {
    }
}
