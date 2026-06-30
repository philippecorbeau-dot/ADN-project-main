<?php

namespace App\Entity\Chat;

use App\Entity\User\User;
use App\Repository\Chat\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_messages')]
#[ORM\HasLifecycleCallbacks]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $sender;

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'is_read', type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(name: 'sender_type', type: 'string', length: 20)]
    private string $senderType; // 'user' ou 'admin'

    #[ORM\Column(name: 'conversation_id', type: 'string', length: 255)]
    private string $conversationId; // Pour grouper les messages par conversation

    #[ORM\Column(name: 'is_system_message', type: 'boolean')]
    private bool $isSystemMessage = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function setSender(User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function setSenderType(string $senderType): self
    {
        $this->senderType = $senderType;
        return $this;
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

    public function isSystemMessage(): bool
    {
        return $this->isSystemMessage;
    }

    public function setIsSystemMessage(bool $isSystemMessage): self
    {
        $this->isSystemMessage = $isSystemMessage;
        return $this;
    }

    public function getFormattedTime(): string
    {
        $dt = clone $this->createdAt;
        $dt->setTimezone(new \DateTimeZone('Europe/Paris'));
        return $dt->format('H:i');
    }

    public function getFormattedDate(): string
    {
        $dt = clone $this->createdAt;
        $dt->setTimezone(new \DateTimeZone('Europe/Paris'));
        return $dt->format('d/m/Y');
    }

    public function getFormattedDateTime(): string
    {
        $dt = clone $this->createdAt;
        $dt->setTimezone(new \DateTimeZone('Europe/Paris'));
        return $dt->format('d/m/Y à H:i');
    }

    public function getSenderName(): string
    {
        if ($this->senderType === 'admin') {
            return 'Équipe ADN';
        }
        
        return $this->sender->getFirstName() . ' ' . $this->sender->getLastName();
    }

    public function getSenderInitials(): string
    {
        if ($this->senderType === 'admin') {
            return 'ADN';
        }
        
        $firstName = $this->sender->getFirstName() ?? '';
        $lastName = $this->sender->getLastName() ?? '';
        
        return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
    }

    public function isFromAdmin(): bool
    {
        return $this->senderType === 'admin';
    }

    public function isFromUser(): bool
    {
        return $this->senderType === 'user';
    }
}
