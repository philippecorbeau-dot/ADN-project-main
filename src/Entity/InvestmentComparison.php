<?php

namespace App\Entity;

use App\Repository\InvestmentComparisonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvestmentComparisonRepository::class)]
#[ORM\Table(name: 'investment_comparison')]
class InvestmentComparison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $product;

    #[ORM\Column(type: 'string', length: 50)]
    private string $criterion;

    #[ORM\Column(type: 'string', length: 255)]
    private string $value;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function setProduct(string $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getCriterion(): string
    {
        return $this->criterion;
    }

    public function setCriterion(string $criterion): self
    {
        $this->criterion = $criterion;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
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
}
