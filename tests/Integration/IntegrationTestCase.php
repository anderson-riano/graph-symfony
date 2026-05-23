<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Product\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

abstract class IntegrationTestCase extends KernelTestCase
{
    private static bool $schemaCreated = false;

    protected EntityManagerInterface $entityManager;

    protected InMemoryTransport $asyncTransport;

    protected function setUp(): void
    {
        self::bootKernel();

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

        $this->resetDatabase();
        $this->seedProducts();
    }

    private function resetDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE processed_messages, order_event_logs, order_items, "orders", products RESTART IDENTITY CASCADE');
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
