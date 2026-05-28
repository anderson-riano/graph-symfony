<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Messaging\Entity\OutboxMessage;
use App\Domain\Messaging\Repository\OutboxMessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OutboxMessage>
 */
final class OutboxMessageRepository extends ServiceEntityRepository implements OutboxMessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutboxMessage::class);
    }

    public function add(OutboxMessage $message): void
    {
        $this->getEntityManager()->persist($message);
    }

    public function save(OutboxMessage $message): void
    {
        $em = $this->getEntityManager();
        $em->persist($message);
        $em->flush();
    }

    public function findPending(int $limit): array
    {
        $queryLimit = max(1, $limit);

        /** @var list<OutboxMessage> $messages */
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.publishedAt IS NULL')
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($queryLimit)
            ->getQuery()
            ->getResult();

        return $messages;
    }
}
