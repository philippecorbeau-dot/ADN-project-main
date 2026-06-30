<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'holdings')]
class Holding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductAccount::class)]
    #[ORM\JoinColumn(name: 'product_account_id', referencedColumnName: 'id', nullable: false)]
    private ?ProductAccount $productAccount = null;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(name: 'instrument_id', referencedColumnName: 'id', nullable: false)]
    private ?Instrument $instrument = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6, nullable: true)]
    private ?string $units = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6, nullable: true)]
    private ?string $lastPrice = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $lastPriceDate = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6, nullable: true)]
    private ?string $buyPrice = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $buyDate = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $buyCost = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductAccount(): ?ProductAccount
    {
        return $this->productAccount;
    }

    public function setProductAccount(ProductAccount $productAccount): self
    {
        $this->productAccount = $productAccount;
        return $this;
    }

    public function getInstrument(): ?Instrument
    {
        return $this->instrument;
    }

    public function setInstrument(Instrument $instrument): self
    {
        $this->instrument = $instrument;
        return $this;
    }

    public function getUnits(): ?string
    {
        return $this->units;
    }

    public function setUnits(?string $units): self
    {
        $this->units = $units;
        return $this;
    }

    public function getLastPrice(): ?string
    {
        return $this->lastPrice;
    }

    public function setLastPrice(?string $lastPrice): self
    {
        $this->lastPrice = $lastPrice;
        return $this;
    }

    public function getLastPriceDate(): ?\DateTimeInterface
    {
        return $this->lastPriceDate;
    }

    public function setLastPriceDate(?\DateTimeInterface $lastPriceDate): self
    {
        $this->lastPriceDate = $lastPriceDate;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getBuyPrice(): ?string
    {
        return $this->buyPrice;
    }

    public function setBuyPrice(?string $buyPrice): self
    {
        $this->buyPrice = $buyPrice;
        return $this;
    }

    public function getBuyDate(): ?\DateTimeInterface
    {
        return $this->buyDate;
    }

    public function setBuyDate(?\DateTimeInterface $buyDate): self
    {
        $this->buyDate = $buyDate;
        return $this;
    }

    public function getBuyCost(): ?string
    {
        return $this->buyCost;
    }

    public function setBuyCost(?string $buyCost): self
    {
        $this->buyCost = $buyCost;
        return $this;
    }
}


