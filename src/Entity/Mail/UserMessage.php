<?php

namespace App\Entity\Mail;

use App\Entity\User\User;
use App\Repository\Mail\UserMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserMessageRepository::class)]
#[ORM\Table(name: 'user_messages')]
#[ORM\HasLifecycleCallbacks]
class UserMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $sender;

    #[ORM\Column(name: 'subject', type: 'string', length: 255)]
    private string $subject;

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'is_read', type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(name: 'admin_response', type: 'text', nullable: true)]
    private ?string $adminResponse = null;

    #[ORM\Column(name: 'admin_response_at', type: 'datetime', nullable: true)]
    private ?\DateTime $adminResponseAt = null;

    #[ORM\Column(name: 'priority', type: 'string', length: 20)]
    private string $priority = 'normal';

    #[ORM\Column(name: 'category', type: 'string', length: 50)]
    private string $category = 'general';

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

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
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

    public function getAdminResponse(): ?string
    {
        return $this->adminResponse;
    }

    public function setAdminResponse(?string $adminResponse): self
    {
        $this->adminResponse = $adminResponse;
        if ($adminResponse !== null) {
            $this->adminResponseAt = new \DateTime();
        }
        return $this;
    }

    public function getAdminResponseAt(): ?\DateTime
    {
        return $this->adminResponseAt;
    }

    public function setAdminResponseAt(?\DateTime $adminResponseAt): self
    {
        $this->adminResponseAt = $adminResponseAt;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function hasResponse(): bool
    {
        return $this->adminResponse !== null;
    }

    public function getSenderName(): string
    {
        return $this->sender->getFirstName() . ' ' . $this->sender->getLastName();
    }

    public function getSenderEmail(): string
    {
        return $this->sender->getEmail();
    }

    public function getFormattedCreatedAt(): string
    {
        return $this->createdAt->format('d/m/Y à H:i');
    }

    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            'low' => 'Faible',
            'normal' => 'Normale',
            'high' => 'Élevée',
            'urgent' => 'Urgente',
            default => 'Normale'
        };
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'general' => 'Général',
            'technical' => 'Technique',
            'investment' => 'Investissement',
            'account' => 'Compte',
            'kyc' => 'KYC',
            default => 'Général'
        };
    }

    public static function getCategoryOptions(): array
    {
        return [
            'general' => 'Général',
            'technical' => 'Problème technique',
            'investment' => 'Question investissement',
            'account' => 'Mon compte',
            'kyc' => 'Vérification KYC',
        ];
    }

    public static function getPriorityOptions(): array
    {
        return [
            'low' => 'Faible',
            'normal' => 'Normale',
            'high' => 'Élevée',
            'urgent' => 'Urgente',
        ];
    }
}
