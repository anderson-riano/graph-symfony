<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'processed_messages')]
#[ORM\UniqueConstraint(name: 'uniq_processed_message_id', columns: ['message_id'])]
class ProcessedMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 120, name: 'message_id')]
    private string $messageId;

    #[ORM\Column(type: 'string', length: 180, name: 'message_name')]
    private string $messageName;

    #[ORM\Column(type: 'datetime_immutable', name: 'processed_at')]
    private \DateTimeImmutable $processedAt;

    public function __construct(string $messageId, string $messageName)
    {
        $this->id = Uuid::v7();
        $this->messageId = $messageId;
        $this->messageName = $messageName;
        $this->processedAt = new \DateTimeImmutable();
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

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
