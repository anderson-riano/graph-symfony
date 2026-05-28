<?php

declare(strict_types=1);

namespace App\Application\Messaging\Service;

use App\Domain\Messaging\Contract\OutboxMessageInterface;
use App\Domain\Messaging\Entity\OutboxMessage;
use App\Domain\Messaging\Repository\OutboxMessageRepositoryInterface;

final readonly class OutboxRecorder
{
    public function __construct(
        private OutboxMessageRepositoryInterface $outboxMessageRepository
    ) {
    }

    public function record(OutboxMessageInterface $message, string $transport = 'async'): void
    {
        $this->outboxMessageRepository->add(
            OutboxMessage::fromOutboxMessage($message, $transport)
        );
    }
}
