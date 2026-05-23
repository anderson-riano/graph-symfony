<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Messaging\Entity\ProcessedMessage;
use App\Domain\Messaging\Repository\ProcessedMessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessedMessage>
 */
final class ProcessedMessageRepository extends ServiceEntityRepository implements ProcessedMessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedMessage::class);
    }

    public function alreadyProcessed(string $messageId): bool
    {
        return null !== $this->findOneBy(['messageId' => $messageId]);
    }

    public function markProcessed(string $messageId, string $messageName): void
    {
        $entity = new ProcessedMessage($messageId, $messageName);
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }
}
