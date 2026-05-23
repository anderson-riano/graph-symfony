<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Product\Entity\Product;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Product>
 */
final class ProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findById(Uuid $id): ?Product
    {
        /** @var Product|null $product */
        $product = parent::find($id);

        return $product;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $idStrings = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $ids);

        /** @var list<Product> $products */
        $products = $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $idStrings)
            ->getQuery()
            ->getResult();

        return $products;
    }
}
