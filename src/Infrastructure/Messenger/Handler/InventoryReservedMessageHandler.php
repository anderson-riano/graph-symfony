<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Handler;

use App\Application\Inventory\Service\InventoryReservationService;
use App\Application\Messaging\Service\MessageIdempotencyService;
use App\Application\Messaging\Service\OutboxRecorder;
use App\Application\Order\Service\OrderEventLogger;
use App\Application\Payment\Service\PaymentSimulationService;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Infrastructure\Messenger\Message\InventoryReservedMessage;
use App\Infrastructure\Messenger\Message\PaymentApprovedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class InventoryReservedMessageHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private PaymentSimulationService $paymentSimulationService,
        private InventoryReservationService $inventoryReservationService,
        private OrderEventLogger $orderEventLogger,
        private MessageIdempotencyService $messageIdempotencyService,
        private EntityManagerInterface $entityManager,
        private OutboxRecorder $outboxRecorder
    ) {
    }

    public function __invoke(InventoryReservedMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $order = $this->orderRepository->findById(Uuid::fromString($message->orderId));
        if ($order === null || $order->getStatus() !== OrderStatus::INVENTORY_RESERVED) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($order, $message): void {
            if (!$this->messageIdempotencyService->guard($message->messageId, $message::class)) {
                return;
            }

            $approved = $this->paymentSimulationService->isApproved($order);

            if ($approved) {
                $order->transitionTo(OrderStatus::PAYMENT_APPROVED);
                $this->orderEventLogger->log(
                    $order,
                    OrderEventName::PAYMENT_APPROVED,
                    ['customerEmail' => $order->getCustomerEmail()]
                );

                $nextMessage = new PaymentApprovedMessage(
                    Uuid::v7()->toRfc4122(),
                    $order->getId()->toRfc4122()
                );
                $this->outboxRecorder->record($nextMessage);
            } else {
                $order->transitionTo(OrderStatus::FAILED);
                $this->inventoryReservationService->release($order);
                $this->orderEventLogger->log(
                    $order,
                    OrderEventName::PAYMENT_REJECTED,
                    ['customerEmail' => $order->getCustomerEmail()],
                    'Payment simulation rejected this order.'
                );
            }

            $this->entityManager->flush();
        });
    }
}
