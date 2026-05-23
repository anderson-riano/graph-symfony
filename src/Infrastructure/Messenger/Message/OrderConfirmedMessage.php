<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Message;

final readonly class OrderConfirmedMessage
{
    public function __construct(
        public string $messageId,
        public string $orderId
    ) {
    }
}
