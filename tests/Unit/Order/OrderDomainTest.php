<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderItem;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Exception\InvalidOrderException;
use App\Domain\Order\Exception\InvalidOrderTransitionException;
use App\Domain\Product\Entity\Product;
use App\Domain\Shared\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class OrderDomainTest extends TestCase
{
    public function testCalculatesOrderTotal(): void
    {
        $order = new Order('Test User', 'test@example.com');
        $product = new Product('Keyboard', 'SKU-KB-001', '120.00', 10);

        $order->addItem(new OrderItem($order, $product, 2, $product->getPrice()));
        $order->addItem(new OrderItem($order, $product, 1, $product->getPrice()));

        self::assertSame('360.00', $order->getTotal());
    }

    public function testInvalidOrderWithoutItems(): void
    {
        $this->expectException(InvalidOrderException::class);

        $order = new Order('Test User', 'test@example.com');
        $order->ensureHasItems();
    }

    public function testInvalidQuantity(): void
    {
        $this->expectException(DomainException::class);

        $order = new Order('Test User', 'test@example.com');
        $product = new Product('Keyboard', 'SKU-KB-001', '120.00', 10);
        new OrderItem($order, $product, 0, $product->getPrice());
    }

    public function testInvalidStatusTransition(): void
    {
        $this->expectException(InvalidOrderTransitionException::class);

        $order = new Order('Test User', 'test@example.com');
        $order->transitionTo(OrderStatus::PAYMENT_APPROVED);
    }
}
