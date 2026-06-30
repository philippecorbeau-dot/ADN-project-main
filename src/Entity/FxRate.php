<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'fx_rates')]
class FxRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 3)]
    private string $base = 'EUR';

    #[ORM\Column(length: 3)]
    private string $counter = 'EUR';

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8)]
    private string $rate = '1';

    public function __construct()
    {
        $this->date = new \DateTime();
    }
}


