<?php

namespace App\Entity\Chat;

use App\Repository\Chat\ChatConversationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatConversationRepository::class)]
#[ORM\Table(name: 'chat_conversations')]
#[ORM\UniqueConstraint(name: 'uniq_conversation_id', columns: ['conversation_id'])]
#[ORM\HasLifecycleCallbacks]
class ChatConversation
{
    public const STATUS_OPEN = 'ouvert';
    public const STATUS_CLOSED = 'ferme';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'conversation_id', type: 'string', length: 255)]
    private string $conversationId;

    #[ORM\Column(name: 'status', type: 'string', length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct(string $conversationId = '')
    {
        $this->conversationId = $conversationId;
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversationId(): string
    {
        return $this->conversationId;
    }

    public function setConversationId(string $conversationId): self
    {
        $this->conversationId = $conversationId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [self::STATUS_OPEN, self::STATUS_CLOSED], true)) {
            $status = self::STATUS_OPEN;
        }
        $this->status = $status;
        return $this;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }
}


