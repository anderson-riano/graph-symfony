<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Handler;

use App\Application\Messaging\Service\MessageIdempotencyService;
use App\Application\Order\Service\OrderEventLogger;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Infrastructure\Messenger\Message\OrderConfirmedMessage;
use App\Infrastructure\Messenger\Message\PaymentApprovedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class PaymentApprovedMessageHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private OrderEventLogger $orderEventLogger,
        private MessageIdempotencyService $messageIdempotencyService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(PaymentApprovedMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $order = $this->orderRepository->findById(Uuid::fromString($message->orderId));
        if ($order === null || $order->getStatus() !== OrderStatus::PAYMENT_APPROVED) {
            return;
        }

        $nextMessage = null;
        $this->entityManager->wrapInTransaction(function () use ($order, $message, &$nextMessage): void {
            if (!$this->messageIdempotencyService->guard($message->messageId, $message::class)) {
                return;
            }

            $order->transitionTo(OrderStatus::CONFIRMED);
            $this->orderEventLogger->log(
                $order,
                OrderEventName::ORDER_CONFIRMED,
                ['orderId' => $order->getId()->toRfc4122()]
            );
            $this->entityManager->flush();

            $nextMessage = new OrderConfirmedMessage(
                Uuid::v7()->toRfc4122(),
                $order->getId()->toRfc4122()
            );
        });

        if ($nextMessage !== null) {
            $this->messageBus->dispatch($nextMessage);
        }
    }
}
