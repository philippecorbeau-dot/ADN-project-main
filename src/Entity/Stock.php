<?php

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\Table(name: 'stocks')]
#[ORM\HasLifecycleCallbacks]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $symbol = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(name: 'price_change', type: 'decimal', precision: 10, scale: 2)]
    private ?string $change = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $changePercent = null;

    #[ORM\Column]
    private ?int $volume = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $high = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $low = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $open = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $previousClose = null;

    #[ORM\Column]
    private ?bool $isPositive = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): static
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getChange(): ?string
    {
        return $this->change;
    }

    public function setChange(string $change): static
    {
        $this->change = $change;
        return $this;
    }

    public function getChangePercent(): ?string
    {
        return $this->changePercent;
    }

    public function setChangePercent(string $changePercent): static
    {
        $this->changePercent = $changePercent;
        return $this;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function setVolume(int $volume): static
    {
        $this->volume = $volume;
        return $this;
    }

    public function getHigh(): ?string
    {
        return $this->high;
    }

    public function setHigh(string $high): static
    {
        $this->high = $high;
        return $this;
    }

    public function getLow(): ?string
    {
        return $this->low;
    }

    public function setLow(string $low): static
    {
        $this->low = $low;
        return $this;
    }

    public function getOpen(): ?string
    {
        return $this->open;
    }

    public function setOpen(string $open): static
    {
        $this->open = $open;
        return $this;
    }

    public function getPreviousClose(): ?string
    {
        return $this->previousClose;
    }

    public function setPreviousClose(string $previousClose): static
    {
        $this->previousClose = $previousClose;
        return $this;
    }

    public function isPositive(): ?bool
    {
        return $this->isPositive;
    }

    public function setIsPositive(bool $isPositive): static
    {
        $this->isPositive = $isPositive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function __toString(): string
    {
        return $this->symbol ?? '';
    }
} 