<?php

declare(strict_types=1);

namespace App\Domain\Order\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use App\Domain\Order\Enum\OrderEventName;
use App\Domain\Order\Enum\OrderStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_event_logs')]
#[ORM\Index(name: 'idx_order_event_logs_order_created_at', columns: ['order_id', 'created_at'])]
#[ApiResource(
    operations: [],
    graphQlOperations: [
        new Query(),
    ]
)]
class OrderEventLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(type: 'string', length: 80, enumType: OrderEventName::class)]
    private OrderEventName $eventName;

    #[ORM\Column(type: 'string', length: 40, enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Order $order,
        OrderEventName $eventName,
        OrderStatus $status,
        ?array $payload = null,
        ?string $errorMessage = null
    ) {
        $this->id = Uuid::v7();
        $this->order = $order;
        $this->eventName = $eventName;
        $this->status = $status;
        $this->payload = $payload;
        $this->errorMessage = $errorMessage;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getEventName(): OrderEventName
    {
        return $this->eventName;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
