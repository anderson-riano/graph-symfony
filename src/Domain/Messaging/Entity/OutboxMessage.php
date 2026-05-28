<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Entity;

use App\Domain\Messaging\Contract\OutboxMessageInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'outbox_messages')]
#[ORM\UniqueConstraint(name: 'uniq_outbox_message_id', columns: ['message_id'])]
#[ORM\Index(name: 'idx_outbox_pending_created_at', columns: ['published_at', 'created_at'])]
class OutboxMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 120, name: 'message_id')]
    private string $messageId;

    #[ORM\Column(type: 'string', length: 255, name: 'message_name')]
    private string $messageName;

    #[ORM\Column(type: 'string', length: 50)]
    private string $transport;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'published_at', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'integer')]
    private int $attempts = 0;

    #[ORM\Column(type: 'text', name: 'last_error', nullable: true)]
    private ?string $lastError = null;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(string $messageId, string $messageName, string $transport, array $payload)
    {
        $this->id = Uuid::v7();
        $this->messageId = $messageId;
        $this->messageName = $messageName;
        $this->transport = $transport;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromOutboxMessage(OutboxMessageInterface $message, string $transport = 'async'): self
    {
        return new self(
            $message->messageId(),
            $message::class,
            $transport,
            $message->toPayload()
        );
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getMessageName(): string
    {
        return $this->messageName;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function markPublished(): void
    {
        $this->publishedAt = new \DateTimeImmutable();
        $this->attempts++;
        $this->lastError = null;
    }

    public function markFailed(string $error): void
    {
        $this->attempts++;
        $this->lastError = $error;
    }
}
