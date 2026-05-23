<?php

declare(strict_types=1);

namespace App\Application\Messaging\Service;

use App\Domain\Messaging\Repository\ProcessedMessageRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

final readonly class MessageIdempotencyService
{
    public function __construct(
        private ProcessedMessageRepositoryInterface $processedMessageRepository,
        private LoggerInterface $logger
    ) {
    }

    public function guard(string $messageId, string $messageName): bool
    {
        if ($this->processedMessageRepository->alreadyProcessed($messageId)) {
            $this->logger->info('Skipping already processed message.', [
                'messageId' => $messageId,
                'messageName' => $messageName,
            ]);

            return false;
        }

        try {
            $this->processedMessageRepository->markProcessed($messageId, $messageName);
        } catch (UniqueConstraintViolationException) {
            $this->logger->info('Skipping duplicated message due to unique constraint.', [
                'messageId' => $messageId,
                'messageName' => $messageName,
            ]);

            return false;
        }

        return true;
    }
}
