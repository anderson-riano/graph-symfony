<?php

declare(strict_types=1);

namespace App\Tests\GraphQL;

use App\Application\Order\DTO\CreateOrderInput;
use App\Application\Order\DTO\CreateOrderItemInput;
use App\Application\Order\UseCase\CreateOrderUseCase;
use App\Domain\Product\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class OrderGraphQlTest extends WebTestCase
{
    private static bool $schemaCreated = false;

    private EntityManagerInterface $entityManager;

    private InMemoryTransport $asyncTransport;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->asyncTransport = self::getContainer()->get('messenger.transport.async');
        $this->asyncTransport->reset();

        if (!self::$schemaCreated) {
            $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool = new SchemaTool($this->entityManager);
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            self::$schemaCreated = true;
        }

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE outbox_messages, processed_messages, order_event_logs, order_items, "orders", products RESTART IDENTITY CASCADE');
        $this->seedProducts();
    }

    public function testCreateOrderMutation(): void
    {
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);
        self::assertInstanceOf(Product::class, $product);

        $payload = $this->requestGraphQl(sprintf(
            'mutation { createOrder(input: { customerName: "Anderson Riano", customerEmail: "anderson@test.com", items: [{ productId: "%s", quantity: 2 }] }) { order { id status total createdAt } } }',
            $product->getId()->toRfc4122()
        ));

        self::assertArrayHasKey('data', $payload);
        self::assertSame('PENDING', $payload['data']['createOrder']['order']['status']);
        self::assertSame('240.00', $payload['data']['createOrder']['order']['total']);
        self::assertCount(0, $this->asyncTransport->getSent());

        $pendingOutboxCount = (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM outbox_messages WHERE published_at IS NULL'
        );
        self::assertSame(1, $pendingOutboxCount);
    }

    public function testOrderQuery(): void
    {
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => 'SKU-KEYBOARD-001']);
        self::assertInstanceOf(Product::class, $product);

        $useCase = self::getContainer()->get(CreateOrderUseCase::class);
        $order = $useCase->execute(new CreateOrderInput(
            'Query User',
            'query@test.com',
            [new CreateOrderItemInput($product->getId()->toRfc4122(), 1)]
        ));

        $payload = $this->requestGraphQl(sprintf(
            'query { order(id: "/api/orders/%s") { id customerName customerEmail status total items { edges { node { quantity unitPrice subtotal product { sku name } } } } events { edges { node { eventName status errorMessage } } } } }',
            $order->getId()->toRfc4122()
        ));

        self::assertArrayHasKey('data', $payload);
        self::assertSame('PENDING', $payload['data']['order']['status']);
        self::assertSame('Query User', $payload['data']['order']['customerName']);
        self::assertSame(1, $payload['data']['order']['items']['edges'][0]['node']['quantity']);
        self::assertSame('ORDER_CREATED', $payload['data']['order']['events']['edges'][0]['node']['eventName']);
    }

    public function testCreateOrderValidationErrors(): void
    {
        $payload = $this->requestGraphQl(
            'mutation { createOrder(input: { customerName: "", customerEmail: "not-email", items: [{ productId: "", quantity: 0 }] }) { order { id } } }',
            false
        );

        self::assertArrayHasKey('errors', $payload);
        self::assertNotEmpty($payload['errors']);
        self::assertStringContainsString('customerName', json_encode($payload['errors'], JSON_THROW_ON_ERROR));
        self::assertStringContainsString('customerEmail', json_encode($payload['errors'], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestGraphQl(string $query, bool $expectSuccess = true): array
    {
        $this->client->request('POST', '/api/graphql', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'query' => $query,
        ], JSON_THROW_ON_ERROR));

        if ($expectSuccess) {
            self::assertResponseIsSuccessful();
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function seedProducts(): void
    {
        $products = [
            new Product('Mechanical Keyboard', 'SKU-KEYBOARD-001', '120.00', 10),
            new Product('Wireless Mouse', 'SKU-MOUSE-001', '65.00', 20),
            new Product('27 Inch Monitor', 'SKU-MONITOR-001', '300.00', 5),
        ];

        foreach ($products as $product) {
            $this->entityManager->persist($product);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
