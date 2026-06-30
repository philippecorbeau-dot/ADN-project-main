<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'instruments')]
class Instrument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 50)]
    private string $symbol = '';

    #[ORM\Column(length: 10)]
    private string $exchange = '';

    #[ORM\Column(length: 10)]
    private string $currency = '';

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $isin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getExchange(): string
    {
        return $this->exchange;
    }

    public function setExchange(string $exchange): self
    {
        $this->exchange = $exchange;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getIsin(): ?string
    {
        return $this->isin;
    }

    public function setIsin(?string $isin): self
    {
        $this->isin = $isin;
        return $this;
    }
}



