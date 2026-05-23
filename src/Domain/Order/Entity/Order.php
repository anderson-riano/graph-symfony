<?php

declare(strict_types=1);

namespace App\Domain\Order\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Exception\InvalidOrderException;
use App\Domain\Order\Exception\InvalidOrderTransitionException;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_status', columns: ['status'])]
#[ApiResource(
    operations: [],
    graphQlOperations: [
        new Query(),
        new Mutation(
            name: 'create',
            resolver: 'App\Infrastructure\GraphQL\Mutation\CreateOrderMutationResolver',
            read: false,
            deserialize: false,
            validate: false,
            write: false,
            args: [
                'customerName' => ['type' => 'String!'],
                'customerEmail' => ['type' => 'String!'],
                'items' => ['type' => '[Iterable!]!'],
            ],
        ),
    ]
)]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $customerName;

    #[ORM\Column(length: 180)]
    private string $customerEmail;

    #[ORM\Column(type: 'string', length: 40, enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    /**
     * @var Collection<int, OrderEventLog>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderEventLog::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $events;

    public function __construct(string $customerName, string $customerEmail)
    {
        $this->id = Uuid::v7();
        $this->customerName = trim($customerName);
        $this->customerEmail = trim(strtolower($customerEmail));
        $this->status = OrderStatus::PENDING;
        $this->items = new ArrayCollection();
        $this->events = new ArrayCollection();
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

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @return Collection<int, OrderEventLog>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addItem(OrderItem $item): void
    {
        if ($item->getQuantity() <= 0) {
            throw new InvalidOrderException('Quantity must be greater than zero.');
        }

        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }

        $this->recalculateTotal();
        $this->touch();
    }

    public function ensureHasItems(): void
    {
        if ($this->items->isEmpty()) {
            throw new InvalidOrderException('An order must have at least one item.');
        }
    }

    public function transitionTo(OrderStatus $newStatus): void
    {
        if ($this->status === $newStatus) {
            return;
        }

        $allowedTransitions = [
            OrderStatus::PENDING->value => [
                OrderStatus::INVENTORY_RESERVED,
                OrderStatus::FAILED,
            ],
            OrderStatus::INVENTORY_RESERVED->value => [
                OrderStatus::PAYMENT_APPROVED,
                OrderStatus::FAILED,
            ],
            OrderStatus::PAYMENT_APPROVED->value => [
                OrderStatus::CONFIRMED,
                OrderStatus::FAILED,
            ],
            OrderStatus::CONFIRMED->value => [],
            OrderStatus::FAILED->value => [],
            OrderStatus::CANCELLED->value => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$this->status->value], true)) {
            throw new InvalidOrderTransitionException(sprintf(
                'Invalid transition from %s to %s.',
                $this->status->value,
                $newStatus->value
            ));
        }

        $this->status = $newStatus;
        $this->touch();
    }

    public function addEvent(OrderEventLog $event): void
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }
        $this->touch();
    }

    private function recalculateTotal(): void
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            $total = Money::add($total, $item->getSubtotal());
        }

        $this->total = $total;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
