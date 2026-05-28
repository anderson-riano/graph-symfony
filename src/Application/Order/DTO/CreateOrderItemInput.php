<?php

declare(strict_types=1);

namespace App\Application\Order\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderItemInput
{
    public function __construct(
        #[Assert\NotBlank]
        public string $productId,
        #[Assert\Positive]
        public int $quantity
    ) {
    }
}
