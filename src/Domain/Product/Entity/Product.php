<?php

declare(strict_types=1);

namespace App\Domain\Product\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Domain\Product\Exception\ProductUnavailableException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'uniq_products_sku', columns: ['sku'])]
#[ApiResource(
    operations: [],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
    ]
)]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $sku;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(type: 'integer')]
    private int $stock;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $sku, string $price, int $stock, bool $isActive = true)
    {
        if (!Money::isGreaterThanZero($price)) {
            throw new DomainException('Price must be greater than zero.');
        }

        if ($stock < 0) {
            throw new DomainException('Stock cannot be negative.');
        }

        $this->id = Uuid::v7();
        $this->name = trim($name);
        $this->sku = trim($sku);
        $this->price = Money::normalize($price);
        $this->stock = $stock;
        $this->isActive = $isActive;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->id->toRfc4122();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isOrderable(int $quantity): bool
    {
        return $this->isActive && $quantity > 0 && $this->stock >= $quantity;
    }

    public function reserveStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero.');
        }

        if (!$this->isActive) {
            throw new ProductUnavailableException(sprintf('Product "%s" is inactive.', $this->sku));
        }

        if ($this->stock < $quantity) {
            throw new ProductUnavailableException(sprintf('Insufficient stock for product "%s".', $this->sku));
        }

        $this->stock -= $quantity;
        $this->touch();
    }

    public function releaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero.');
        }

        $this->stock += $quantity;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
