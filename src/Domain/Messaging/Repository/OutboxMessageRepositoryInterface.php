<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Repository;

use App\Domain\Messaging\Entity\OutboxMessage;

interface OutboxMessageRepositoryInterface
{
    public function add(OutboxMessage $message): void;

    public function save(OutboxMessage $message): void;

    /**
     * @return list<OutboxMessage>
     */
    public function findPending(int $limit): array;
}
