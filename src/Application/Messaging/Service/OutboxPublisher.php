<?php

declare(strict_types=1);

namespace App\Application\Messaging\Service;

use App\Domain\Messaging\Contract\OutboxMessageInterface;
use App\Domain\Messaging\Repository\OutboxMessageRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final readonly class OutboxPublisher
{
    public function __construct(
        private OutboxMessageRepositoryInterface $outboxMessageRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    public function publishPending(int $limit): int
    {
        $published = 0;

        foreach ($this->outboxMessageRepository->findPending($limit) as $outboxMessage) {
            try {
                $messageClass = $outboxMessage->getMessageName();
                if (!is_subclass_of($messageClass, OutboxMessageInterface::class)) {
                    throw new \RuntimeException(sprintf(
                        'Message class "%s" does not implement %s.',
                        $messageClass,
                        OutboxMessageInterface::class
                    ));
                }

                /** @var class-string<OutboxMessageInterface> $messageClass */
                $message = $messageClass::fromPayload($outboxMessage->getPayload());

                $this->messageBus->dispatch(
                    $message,
                    [new TransportNamesStamp([$outboxMessage->getTransport()])]
                );

                $outboxMessage->markPublished();
                $this->outboxMessageRepository->save($outboxMessage);
                $published++;
            } catch (\Throwable $exception) {
                $outboxMessage->markFailed($exception->getMessage());
                $this->outboxMessageRepository->save($outboxMessage);

                $this->logger->error('Failed to publish outbox message.', [
                    'outboxId' => $outboxMessage->getId()->toRfc4122(),
                    'messageId' => $outboxMessage->getMessageId(),
                    'messageClass' => $outboxMessage->getMessageName(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $published;
    }
}
