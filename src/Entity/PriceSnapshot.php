<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_snapshots')]
class PriceSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(name: 'instrument_id', referencedColumnName: 'id', nullable: false)]
    private ?Instrument $instrument = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 6)]
    private string $close = '0';

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getClose(): string
    {
        return $this->close;
    }

    public function setClose(string $close): self
    {
        $this->close = $close;
        return $this;
    }
}


