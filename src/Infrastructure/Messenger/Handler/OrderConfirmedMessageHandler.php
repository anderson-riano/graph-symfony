<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Handler;

use App\Application\Messaging\Service\MessageIdempotencyService;
use App\Application\Notification\Service\NotificationService;
use App\Application\Order\Service\OrderEventLogger;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Infrastructure\Messenger\Message\OrderConfirmedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class OrderConfirmedMessageHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private NotificationService $notificationService,
        private OrderEventLogger $orderEventLogger,
        private MessageIdempotencyService $messageIdempotencyService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(OrderConfirmedMessage $message): void
    {
        if (!Uuid::isValid($message->orderId)) {
            return;
        }

        $order = $this->orderRepository->findById(Uuid::fromString($message->orderId));
        if ($order === null || $order->getStatus() !== OrderStatus::CONFIRMED) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($order, $message): void {
            if (!$this->messageIdempotencyService->guard($message->messageId, $message::class)) {
                return;
            }

            try {
                $this->notificationService->sendOrderConfirmedNotification($order);
                $this->orderEventLogger->log(
                    $order,
                    OrderEventName::NOTIFICATION_SENT,
                    ['orderId' => $order->getId()->toRfc4122()]
                );
            } catch (\Throwable $exception) {
                $this->orderEventLogger->log(
                    $order,
                    OrderEventName::NOTIFICATION_FAILED,
                    ['errorType' => $exception::class],
                    $exception->getMessage()
                );
            }

            $this->entityManager->flush();
        });
    }
}
