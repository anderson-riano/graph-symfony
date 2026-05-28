<?php

declare(strict_types=1);

namespace App\Application\Order\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderInput
{
    /**
     * @param list<CreateOrderItemInput> $items
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 180)]
        public string $customerName,
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $customerEmail,
        #[Assert\Count(min: 1, minMessage: 'At least one item is required.')]
        #[Assert\Valid]
        public array $items
    ) {
    }
}
