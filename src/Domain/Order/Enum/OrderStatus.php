<?php

declare(strict_types=1);

namespace App\Domain\Order\Enum;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case INVENTORY_RESERVED = 'INVENTORY_RESERVED';
    case PAYMENT_APPROVED = 'PAYMENT_APPROVED';
    case CONFIRMED = 'CONFIRMED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
