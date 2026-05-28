<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messaging;

use App\Application\Order\DTO\CreateOrderInput;
use App\Application\Order\DTO\CreateOrderItemInput;
use App\Application\Order\UseCase\CreateOrderUseCase;
use App\Domain\Product\Entity\Product;
use App\Infrastructure\Messenger\Message\OrderCreatedMessage;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Uuid;

final class OutboxPublisherIntegrationTest extends IntegrationTestCase
{
    public function testPublishesPendingOutboxMessageToAsyncTransport(): void
    {
        $useCase = self::getContainer()->get(CreateOrderUseCase::class);
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);
        self::assertInstanceOf(Product::class, $product);

        $useCase->execute(new CreateOrderInput(
            'Outbox User',
            'outbox@test.com',
            [new CreateOrderItemInput($product->getId()->toRfc4122(), 1)]
        ));

        self::assertCount(0, $this->asyncTransport->getSent());
        self::assertSame(1, $this->outboxPublisher->publishPending(50));

        $envelopes = $this->asyncTransport->getSent();
        self::assertCount(1, $envelopes);
        self::assertInstanceOf(Envelope::class, $envelopes[0]);
        self::assertInstanceOf(OrderCreatedMessage::class, $envelopes[0]->getMessage());

        $publishedCount = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM outbox_messages WHERE published_at IS NOT NULL'
        );
        self::assertSame(1, $publishedCount);
    }

    public function testDoesNotRepublishAlreadyPublishedOutboxMessages(): void
    {
        $useCase = self::getContainer()->get(CreateOrderUseCase::class);
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);
        self::assertInstanceOf(Product::class, $product);

        $useCase->execute(new CreateOrderInput(
            'Outbox User',
            'outbox2@test.com',
            [new CreateOrderItemInput($product->getId()->toRfc4122(), 1)]
        ));

        self::assertSame(1, $this->outboxPublisher->publishPending(50));
        self::assertSame(0, $this->outboxPublisher->publishPending(50));
        self::assertCount(1, $this->asyncTransport->getSent());
    }

    public function testMarksMessageAsFailedWhenMessageClassIsInvalid(): void
    {
        $this->entityManager->getConnection()->insert('outbox_messages', [
            'id' => Uuid::v7()->toRfc4122(),
            'message_id' => Uuid::v7()->toRfc4122(),
            'message_name' => 'App\\Unknown\\InvalidMessage',
            'transport' => 'async',
            'payload' => json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'published_at' => null,
            'attempts' => 0,
            'last_error' => null,
        ], [
            'payload' => ParameterType::STRING,
        ]);

        self::assertSame(0, $this->outboxPublisher->publishPending(50));

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT attempts, last_error, published_at FROM outbox_messages WHERE message_name = :messageName',
            ['messageName' => 'App\\Unknown\\InvalidMessage']
        );

        self::assertIsArray($row);
        self::assertSame('1', (string) $row['attempts']);
        self::assertNotNull($row['last_error']);
        self::assertNull($row['published_at']);
    }
}
