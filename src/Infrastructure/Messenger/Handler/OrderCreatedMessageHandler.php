<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Handler;

use App\Application\Messaging\Service\MessageIdempotencyService;
use App\Application\Inventory\Service\InventoryReservationService;
use App\Application\Order\Service\OrderEventLogger;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Infrastructure\Messenger\Message\InventoryReservedMessage;
use App\Infrastructure\Messenger\Message\OrderCreatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class OrderCreatedMessageHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private InventoryReservationService $inventoryReservationService,
        private OrderEventLogger $orderEventLogger,
        private MessageIdempotencyService $messageIdempotencyService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(OrderCreatedMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $order = $this->orderRepository->findById(Uuid::fromString($message->orderId));
        if ($order === null || $order->getStatus() !== OrderStatus::PENDING) {
            return;
        }

        $nextMessage = null;
        $this->entityManager->wrapInTransaction(function () use ($order, $message, &$nextMessage): void {
            if (!$this->messageIdempotencyService->guard($message->messageId, $message::class)) {
                return;
            }

            try {
                $this->inventoryReservationService->reserve($order);
                $order->transitionTo(OrderStatus::INVENTORY_RESERVED);
                $this->orderEventLogger->log(
                    $order,
                    OrderEventName::INVENTORY_RESERVED,
                    ['messageId' => Uuid::v7()->toRfc4122()]
                );

                $nextMessage = new InventoryReservedMessage(
                    Uuid::v7()->toRfc4122(),
                    $order->getId()->toRfc4122()
                );
            } catch (\Throwable $exception) {
                $order->transitionTo(OrderStatus::FAILED);
                $this->orderEventLogger->log(
                    $order,
                    OrderEventName::INVENTORY_RESERVATION_FAILED,
                    ['errorType' => $exception::class],
                    $exception->getMessage()
                );
            }
            $this->entityManager->flush();
        });

        if ($nextMessage !== null) {
            $this->messageBus->dispatch($nextMessage);
        }
    }
}
