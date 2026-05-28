<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Message;

use App\Domain\Messaging\Contract\OutboxMessageInterface;

final readonly class OrderConfirmedMessage implements OutboxMessageInterface
{
    public function __construct(
        public string $messageId,
        public string $orderId
    ) {
    }

    public function messageId(): string
    {
        return $this->messageId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'messageId' => $this->messageId,
            'orderId' => $this->orderId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            (string) ($payload['messageId'] ?? ''),
            (string) ($payload['orderId'] ?? '')
        );
    }
}
