<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Repository;

interface ProcessedMessageRepositoryInterface
{
    public function alreadyProcessed(string $messageId): bool;

    public function markProcessed(string $messageId, string $messageName): void;
}
