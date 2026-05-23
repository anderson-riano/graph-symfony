<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order;

use App\Application\Order\DTO\CreateOrderInput;
use App\Application\Order\DTO\CreateOrderItemInput;
use App\Application\Order\UseCase\CreateOrderUseCase;
use App\Domain\Order\Entity\Order;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Product\Entity\Product;
use App\Infrastructure\Messenger\Handler\InventoryReservedMessageHandler;
use App\Infrastructure\Messenger\Handler\OrderConfirmedMessageHandler;
use App\Infrastructure\Messenger\Handler\OrderCreatedMessageHandler;
use App\Infrastructure\Messenger\Handler\PaymentApprovedMessageHandler;
use App\Infrastructure\Messenger\Message\InventoryReservedMessage;
use App\Infrastructure\Messenger\Message\OrderConfirmedMessage;
use App\Infrastructure\Messenger\Message\OrderCreatedMessage;
use App\Infrastructure\Messenger\Message\PaymentApprovedMessage;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;

final class OrderFlowIntegrationTest extends IntegrationTestCase
{
    public function testCreateOrderUseCase(): void
    {
        [$order] = $this->createPendingOrder(2, 'anderson@test.com');

        $this->entityManager->clear();
        $reloadedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());

        self::assertInstanceOf(Order::class, $reloadedOrder);
        self::assertSame(OrderStatus::PENDING, $reloadedOrder->getStatus());
        self::assertSame('240.00', $reloadedOrder->getTotal());
        self::assertCount(1, $reloadedOrder->getEvents());
        self::assertSame(OrderEventName::ORDER_CREATED, $reloadedOrder->getEvents()->first()->getEventName());
    }

    public function testStockReservationSuccess(): void
    {
        [$order, $orderCreatedMessage] = $this->createPendingOrder(2, 'anderson@test.com');
        $this->asyncTransport->reset();

        $handler = self::getContainer()->get(OrderCreatedMessageHandler::class);
        $handler($orderCreatedMessage);

        $this->entityManager->clear();
        $reloadedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        $keyboard = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);

        self::assertInstanceOf(Order::class, $reloadedOrder);
        self::assertSame(OrderStatus::INVENTORY_RESERVED, $reloadedOrder->getStatus());
        self::assertInstanceOf(Product::class, $keyboard);
        self::assertSame(8, $keyboard->getStock());
        self::assertCount(1, $this->asyncTransport->getSent());
        self::assertInstanceOf(InventoryReservedMessage::class, $this->asyncTransport->getSent()[0]->getMessage());
    }

    public function testStockReservationFailure(): void
    {
        [$order, $orderCreatedMessage] = $this->createPendingOrder(999, 'anderson@test.com');
        $this->asyncTransport->reset();

        $handler = self::getContainer()->get(OrderCreatedMessageHandler::class);
        $handler($orderCreatedMessage);

        $this->entityManager->clear();
        $reloadedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        $keyboard = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);

        self::assertInstanceOf(Order::class, $reloadedOrder);
        self::assertSame(OrderStatus::FAILED, $reloadedOrder->getStatus());
        self::assertInstanceOf(Product::class, $keyboard);
        self::assertSame(10, $keyboard->getStock());
        self::assertCount(0, $this->asyncTransport->getSent());
        self::assertTrue(
            $reloadedOrder->getEvents()->exists(
                static fn (int $key, mixed $event): bool => $event->getEventName() === OrderEventName::INVENTORY_RESERVATION_FAILED
            )
        );
    }

    public function testIdempotentMessageHandling(): void
    {
        [$order, $orderCreatedMessage] = $this->createPendingOrder(1, 'anderson@test.com');
        $this->asyncTransport->reset();

        $handler = self::getContainer()->get(OrderCreatedMessageHandler::class);
        $handler($orderCreatedMessage);
        $handler($orderCreatedMessage);

        $this->entityManager->clear();
        $reloadedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());
        $keyboard = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);
        $processedCount = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM processed_messages WHERE message_id = :messageId',
            ['messageId' => $orderCreatedMessage->messageId]
        );

        self::assertInstanceOf(Order::class, $reloadedOrder);
        self::assertSame(OrderStatus::INVENTORY_RESERVED, $reloadedOrder->getStatus());
        self::assertInstanceOf(Product::class, $keyboard);
        self::assertSame(9, $keyboard->getStock());
        self::assertSame(1, $processedCount);
    }

    public function testOrderConfirmedAfterSuccessfulFlow(): void
    {
        [$order, $orderCreatedMessage] = $this->createPendingOrder(1, 'anderson@test.com');
        $this->asyncTransport->reset();

        $orderCreatedHandler = self::getContainer()->get(OrderCreatedMessageHandler::class);
        $inventoryReservedHandler = self::getContainer()->get(InventoryReservedMessageHandler::class);
        $paymentApprovedHandler = self::getContainer()->get(PaymentApprovedMessageHandler::class);
        $orderConfirmedHandler = self::getContainer()->get(OrderConfirmedMessageHandler::class);

        $orderCreatedHandler($orderCreatedMessage);
        $inventoryMessage = $this->extractSentMessage(InventoryReservedMessage::class);
        $this->asyncTransport->reset();

        $inventoryReservedHandler($inventoryMessage);
        $paymentMessage = $this->extractSentMessage(PaymentApprovedMessage::class);
        $this->asyncTransport->reset();

        $paymentApprovedHandler($paymentMessage);
        $orderConfirmedMessage = $this->extractSentMessage(OrderConfirmedMessage::class);
        $this->asyncTransport->reset();

        $orderConfirmedHandler($orderConfirmedMessage);

        $this->entityManager->clear();
        $reloadedOrder = $this->entityManager->getRepository(Order::class)->find($order->getId());

        self::assertInstanceOf(Order::class, $reloadedOrder);
        self::assertSame(OrderStatus::CONFIRMED, $reloadedOrder->getStatus());

        $eventNames = array_map(
            static fn (mixed $event): string => $event->getEventName()->value,
            $reloadedOrder->getEvents()->toArray()
        );

        self::assertSame([
            OrderEventName::ORDER_CREATED->value,
            OrderEventName::INVENTORY_RESERVED->value,
            OrderEventName::PAYMENT_APPROVED->value,
            OrderEventName::ORDER_CONFIRMED->value,
            OrderEventName::NOTIFICATION_SENT->value,
        ], $eventNames);
    }

    /**
     * @return array{Order, OrderCreatedMessage}
     */
    private function createPendingOrder(int $quantity, string $email): array
    {
        $useCase = self::getContainer()->get(CreateOrderUseCase::class);
        $keyboard = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);
        self::assertInstanceOf(Product::class, $keyboard);

        $order = $useCase->execute(new CreateOrderInput(
            'Test Customer',
            $email,
            [new CreateOrderItemInput($keyboard->getId()->toRfc4122(), $quantity)]
        ));

        $envelopes = $this->asyncTransport->getSent();
        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(OrderCreatedMessage::class, $message);

        return [$order, $message];
    }

    /**
     * @template T of object
     * @param class-string<T> $expectedClass
     * @return T
     */
    private function extractSentMessage(string $expectedClass): object
    {
        $envelopes = $this->asyncTransport->getSent();
        self::assertCount(1, $envelopes);
        self::assertInstanceOf(Envelope::class, $envelopes[0]);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf($expectedClass, $message);

        return $message;
    }
}
