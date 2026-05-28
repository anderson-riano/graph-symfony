<?php

declare(strict_types=1);

namespace App\Application\Order\UseCase;

use App\Application\Messaging\Service\OutboxRecorder;
use App\Application\Order\DTO\CreateOrderInput;
use App\Application\Order\Service\OrderEventLogger;
use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderItem;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Exception\InvalidOrderException;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Infrastructure\Messenger\Message\OrderCreatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CreateOrderUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private OrderRepositoryInterface $orderRepository,
        private OrderEventLogger $orderEventLogger,
        private EntityManagerInterface $entityManager,
        private OutboxRecorder $outboxRecorder
    ) {
    }

    public function execute(CreateOrderInput $input): Order
    {
        $customerName = trim($input->customerName);
        $customerEmail = trim(strtolower($input->customerEmail));

        if ($customerName === '' || $customerEmail === '') {
            throw new InvalidOrderException('Customer name and email are required.');
        }

        if ($input->items === []) {
            throw new InvalidOrderException('An order must have at least one item.');
        }

        $productIds = [];
        foreach ($input->items as $item) {
            if ($item->quantity <= 0) {
                throw new InvalidOrderException('Quantity must be greater than zero.');
            }

            $normalizedProductId = $this->normalizeUuid($item->productId);
            if (!Uuid::isValid($normalizedProductId)) {
                throw new InvalidOrderException(sprintf('Invalid product id "%s".', $item->productId));
            }

            $productIds[] = Uuid::fromString($normalizedProductId);
        }

        $products = $this->productRepository->findByIds($productIds);
        $productById = [];
        foreach ($products as $product) {
            $productById[$product->getId()->toRfc4122()] = $product;
        }

        $order = new Order($customerName, $customerEmail);
        foreach ($input->items as $item) {
            $normalizedProductId = $this->normalizeUuid($item->productId);
            $product = $productById[$normalizedProductId] ?? null;
            if ($product === null) {
                throw new InvalidOrderException(sprintf('Product "%s" does not exist.', $item->productId));
            }

            if (!$product->isActive()) {
                throw new InvalidOrderException(sprintf('Product "%s" is inactive.', $product->getSku()));
            }

            $order->addItem(new OrderItem($order, $product, $item->quantity, $product->getPrice()));
        }

        $order->ensureHasItems();
        $this->orderEventLogger->log($order, OrderEventName::ORDER_CREATED, [
            'total' => $order->getTotal(),
            'items' => array_map(
                static fn (OrderItem $orderItem): array => [
                    'productId' => $orderItem->getProduct()->getId()->toRfc4122(),
                    'sku' => $orderItem->getProduct()->getSku(),
                    'quantity' => $orderItem->getQuantity(),
                    'unitPrice' => $orderItem->getUnitPrice(),
                    'subtotal' => $orderItem->getSubtotal(),
                ],
                $order->getItems()->toArray()
            ),
        ]);

        $this->entityManager->wrapInTransaction(function () use ($order): void {
            $this->orderRepository->save($order);
            $this->outboxRecorder->record(new OrderCreatedMessage(
                Uuid::v7()->toRfc4122(),
                $order->getId()->toRfc4122()
            ));
            $this->entityManager->flush();
        });

        return $order;
    }

    private function normalizeUuid(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '/')) {
            $value = (string) strrchr($value, '/');
            $value = ltrim($value, '/');
        }

        return $value;
    }
}
