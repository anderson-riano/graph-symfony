<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Contract;

interface OutboxMessageInterface
{
    public function messageId(): string;

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self;
}
