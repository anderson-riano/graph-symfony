<?php

declare(strict_types=1);

namespace App\Infrastructure\GraphQL\Mutation;

use ApiPlatform\GraphQl\Resolver\MutationResolverInterface;
use App\Application\Order\DTO\CreateOrderInput;
use App\Application\Order\DTO\CreateOrderItemInput;
use App\Application\Order\UseCase\CreateOrderUseCase;
use App\Domain\Order\Exception\InvalidOrderException;

final readonly class CreateOrderMutationResolver implements MutationResolverInterface
{
    public function __construct(
        private CreateOrderUseCase $createOrderUseCase
    ) {
    }

    /**
     * @param object|null $item
     */
    public function __invoke($item, array $context): object
    {
        $input = $context['args']['input'] ?? [];

        $customerName = (string) ($input['customerName'] ?? '');
        $customerEmail = (string) ($input['customerEmail'] ?? '');
        $rawItems = $input['items'] ?? [];

        if (!is_array($rawItems)) {
            throw new InvalidOrderException('Items must be a list.');
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                throw new InvalidOrderException('Each order item must be an object.');
            }

            $productId = (string) ($rawItem['productId'] ?? '');
            $quantity = (int) ($rawItem['quantity'] ?? 0);
            $items[] = new CreateOrderItemInput($productId, $quantity);
        }

        $order = $this->createOrderUseCase->execute(new CreateOrderInput(
            $customerName,
            $customerEmail,
            $items
        ));

        return $order;
    }
}
