<?php

namespace App\Entity\User;

use App\Entity\User\User\SpamFields;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: 'spam')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]

class Spam
{
    use SpamFields;

    #[ORM\Column(name: 'id', type:'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private $updatedAt;

    #[ORM\Column(name: 'email', type: 'string', nullable: true)]
    private $email;

    #[ORM\Column(name: 'ip', type: 'string', nullable: true)]
    private $ip;

    #[ORM\Column(name: 'blocked', type: 'boolean', nullable: false)]
    private $blocked;
    
    public function getId(): int
    {
        return $this->id;
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

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): self
    {
        $this->blocked = $blocked;

        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function onCreate()
    {
        $this->setCreatedAt(new \DateTime('now'));
        $this->setUpdatedAt(new \DateTime('now'));
    }

    /**
     * @ORM\PreUpdate
     */
    public function onUpdate()
    {
        $this->setUpdatedAt(new \DateTime('now'));
    }
}
