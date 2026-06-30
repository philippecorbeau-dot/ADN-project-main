<?php

namespace App\Entity\User;

use App\Repository\User\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
#[ORM\Table(name: 'user_config')]

class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'crowdfunding', type: 'boolean')]
    private $crowdfunding = true;

    #[ORM\Column(name: 'vefa', type: 'boolean')]
    private $vefa = false;

    #[ORM\Column(name: 'scpi', type: 'boolean')]
    private $scpi = false;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'config')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $user;
    

    public function getId()
    {
        return $this->id;
    }

    public function setCrowdfunding(bool $crowdfunding): Config
    {
        $this->crowdfunding = $crowdfunding;
        return $this;
    }

    public function isCrowdfunding(): bool
    {
        return $this->crowdfunding;
    }

    public function setVefa(bool $vefa): Config
    {
        $this->vefa = $vefa;
        return $this;
    }

    public function isVefa(): bool
    {
        return $this->vefa;
    }

    public function setScpi(bool $scpi): Config
    {
        $this->scpi = $scpi;
        return $this;
    }

    public function isScpi(): bool
    {
        return $this->scpi;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getUser()
    {
        return $this->user;
    }

}
