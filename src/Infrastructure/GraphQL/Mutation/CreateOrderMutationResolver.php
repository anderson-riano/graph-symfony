<?php

declare(strict_types=1);

namespace App\Infrastructure\GraphQL\Mutation;

use ApiPlatform\GraphQl\Resolver\MutationResolverInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Application\Order\DTO\CreateOrderInput;
use App\Application\Order\DTO\CreateOrderItemInput;
use App\Application\Order\UseCase\CreateOrderUseCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CreateOrderMutationResolver implements MutationResolverInterface
{
    public function __construct(
        private CreateOrderUseCase $createOrderUseCase,
        private ValidatorInterface $validator
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

        $rawItems = is_array($rawItems) ? $rawItems : [];

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                $items[] = new CreateOrderItemInput('', 0);
                continue;
            }

            $productId = (string) ($rawItem['productId'] ?? '');
            $quantity = (int) ($rawItem['quantity'] ?? 0);
            $items[] = new CreateOrderItemInput($productId, $quantity);
        }

        $orderInput = new CreateOrderInput(
            $customerName,
            $customerEmail,
            $items
        );
        $violations = $this->validator->validate($orderInput);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $order = $this->createOrderUseCase->execute($orderInput);

        return $order;
    }
}
