<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'user_mailing')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]

class Mailing
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: 'User', inversedBy: 'mailing', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]

    private $user;

    #[ORM\Column(name: 'newsletter', type: 'smallint', options: ['default' => 1])]
    private $newsletter = 1;

    #[ORM\Column(name: 'events', type: 'smallint', options: ['default' => 1])]
    private $events = 1;

    #[ORM\Column(name: 'projects', type: 'smallint', options: ['default' => 1])]
    private $projects = 1;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
    public function __construct() {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getNewsletter(): ?bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(bool $newsletter): self
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    public function getEvents(): ?bool
    {
        return $this->events;
    }

    public function setEvents(bool $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function getProjects(): ?bool
    {
        return $this->projects;
    }

    public function setProjects(bool $projects): self
    {
        $this->projects = $projects;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

}
