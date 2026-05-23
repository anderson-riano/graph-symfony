<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\Entity\Product;
use Symfony\Component\Uid\Uuid;

interface ProductRepositoryInterface
{
    public function findById(Uuid $id): ?Product;

    /**
     * @param list<Uuid> $ids
     * @return list<Product>
     */
    public function findByIds(array $ids): array;
}
