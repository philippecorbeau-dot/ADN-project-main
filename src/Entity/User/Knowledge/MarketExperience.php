<?php

namespace App\Entity\User\Knowledge;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_knowledge_market_experience')]
class MarketExperience
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'has_stocks_experience', type: 'boolean', nullable: true)]
    private ?bool $hasStocksExperience = null;

    #[ORM\Column(name: 'stocks_operations_count', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['1 fois', '2-10 fois', '> 10 fois'])]
    private ?string $stocksOperationsCount = null;

    #[ORM\Column(name: 'stocks_volume', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['< 50 K€', 'de 50 K€ à 150 K€', '> 150 K€'])]
    private ?string $stocksVolume = null;

    #[ORM\Column(name: 'has_bonds_experience', type: 'boolean', nullable: true)]
    private ?bool $hasBondsExperience = null;

    #[ORM\Column(name: 'bonds_operations_count', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['1 fois', '2-10 fois', '> 10 fois'])]
    private ?string $bondsOperationsCount = null;

    #[ORM\Column(name: 'bonds_volume', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['< 50 K€', 'de 50 K€ à 150 K€', '> 150 K€'])]
    private ?string $bondsVolume = null;

    #[ORM\Column(name: 'has_ucits_experience', type: 'boolean', nullable: true)]
    private ?bool $hasUcitsExperience = null;

    #[ORM\Column(name: 'ucits_operations_count', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['1 fois', '2-10 fois', '> 10 fois'])]
    private ?string $ucitsOperationsCount = null;

    #[ORM\Column(name: 'ucits_volume', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['< 50 K€', 'de 50 K€ à 150 K€', '> 150 K€'])]
    private ?string $ucitsVolume = null;

    #[ORM\Column(name: 'has_real_estate_experience', type: 'boolean', nullable: true)]
    private ?bool $hasRealEstateExperience = null;

    #[ORM\Column(name: 'real_estate_operations_count', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['1 fois', '2-10 fois', '> 10 fois'])]
    private ?string $realEstateOperationsCount = null;

    #[ORM\Column(name: 'real_estate_volume', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['< 50 K€', 'de 50 K€ à 150 K€', '> 150 K€'])]
    private ?string $realEstateVolume = null;

    #[ORM\Column(name: 'has_complex_instruments_experience', type: 'boolean', nullable: true)]
    private ?bool $hasComplexInstrumentsExperience = null;

    #[ORM\Column(name: 'complex_instruments_operations_count', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['1 fois', '2-10 fois', '> 10 fois'])]
    private ?string $complexInstrumentsOperationsCount = null;

    #[ORM\Column(name: 'complex_instruments_volume', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['< 50 K€', 'de 50 K€ à 150 K€', '> 150 K€'])]
    private ?string $complexInstrumentsVolume = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHasStocksExperience(): ?bool
    {
        return $this->hasStocksExperience;
    }

    public function setHasStocksExperience(?bool $hasStocksExperience): self
    {
        $this->hasStocksExperience = $hasStocksExperience;
        return $this;
    }

    public function getStocksOperationsCount(): ?string
    {
        return $this->stocksOperationsCount;
    }

    public function setStocksOperationsCount(?string $stocksOperationsCount): self
    {
        $this->stocksOperationsCount = $stocksOperationsCount;
        return $this;
    }

    public function getStocksVolume(): ?string
    {
        return $this->stocksVolume;
    }

    public function setStocksVolume(?string $stocksVolume): self
    {
        $this->stocksVolume = $stocksVolume;
        return $this;
    }

    public function getHasBondsExperience(): ?bool
    {
        return $this->hasBondsExperience;
    }

    public function setHasBondsExperience(?bool $hasBondsExperience): self
    {
        $this->hasBondsExperience = $hasBondsExperience;
        return $this;
    }

    public function getBondsOperationsCount(): ?string
    {
        return $this->bondsOperationsCount;
    }

    public function setBondsOperationsCount(?string $bondsOperationsCount): self
    {
        $this->bondsOperationsCount = $bondsOperationsCount;
        return $this;
    }

    public function getBondsVolume(): ?string
    {
        return $this->bondsVolume;
    }

    public function setBondsVolume(?string $bondsVolume): self
    {
        $this->bondsVolume = $bondsVolume;
        return $this;
    }

    public function getHasUcitsExperience(): ?bool
    {
        return $this->hasUcitsExperience;
    }

    public function setHasUcitsExperience(?bool $hasUcitsExperience): self
    {
        $this->hasUcitsExperience = $hasUcitsExperience;
        return $this;
    }

    public function getUcitsOperationsCount(): ?string
    {
        return $this->ucitsOperationsCount;
    }

    public function setUcitsOperationsCount(?string $ucitsOperationsCount): self
    {
        $this->ucitsOperationsCount = $ucitsOperationsCount;
        return $this;
    }

    public function getUcitsVolume(): ?string
    {
        return $this->ucitsVolume;
    }

    public function setUcitsVolume(?string $ucitsVolume): self
    {
        $this->ucitsVolume = $ucitsVolume;
        return $this;
    }

    public function getHasRealEstateExperience(): ?bool
    {
        return $this->hasRealEstateExperience;
    }

    public function setHasRealEstateExperience(?bool $hasRealEstateExperience): self
    {
        $this->hasRealEstateExperience = $hasRealEstateExperience;
        return $this;
    }

    public function getRealEstateOperationsCount(): ?string
    {
        return $this->realEstateOperationsCount;
    }

    public function setRealEstateOperationsCount(?string $realEstateOperationsCount): self
    {
        $this->realEstateOperationsCount = $realEstateOperationsCount;
        return $this;
    }

    public function getRealEstateVolume(): ?string
    {
        return $this->realEstateVolume;
    }

    public function setRealEstateVolume(?string $realEstateVolume): self
    {
        $this->realEstateVolume = $realEstateVolume;
        return $this;
    }

    public function getHasComplexInstrumentsExperience(): ?bool
    {
        return $this->hasComplexInstrumentsExperience;
    }

    public function setHasComplexInstrumentsExperience(?bool $hasComplexInstrumentsExperience): self
    {
        $this->hasComplexInstrumentsExperience = $hasComplexInstrumentsExperience;
        return $this;
    }

    public function getComplexInstrumentsOperationsCount(): ?string
    {
        return $this->complexInstrumentsOperationsCount;
    }

    public function setComplexInstrumentsOperationsCount(?string $complexInstrumentsOperationsCount): self
    {
        $this->complexInstrumentsOperationsCount = $complexInstrumentsOperationsCount;
        return $this;
    }

    public function getComplexInstrumentsVolume(): ?string
    {
        return $this->complexInstrumentsVolume;
    }

    public function setComplexInstrumentsVolume(?string $complexInstrumentsVolume): self
    {
        $this->complexInstrumentsVolume = $complexInstrumentsVolume;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->hasStocksExperience !== null && 
               $this->hasBondsExperience !== null && 
               $this->hasUcitsExperience !== null && 
               $this->hasRealEstateExperience !== null && 
               $this->hasComplexInstrumentsExperience !== null;
    }

    public function getTotalExperienceScore(): int
    {
        $score = 0;
        
        // Expérience actions
        if ($this->hasStocksExperience && $this->stocksOperationsCount && $this->stocksVolume) {
            $score += $this->getExperienceScore($this->stocksOperationsCount, $this->stocksVolume);
        }
        
        // Expérience obligations
        if ($this->hasBondsExperience && $this->bondsOperationsCount && $this->bondsVolume) {
            $score += $this->getExperienceScore($this->bondsOperationsCount, $this->bondsVolume);
        }
        
        // Expérience OPCVM
        if ($this->hasUcitsExperience && $this->ucitsOperationsCount && $this->ucitsVolume) {
            $score += $this->getExperienceScore($this->ucitsOperationsCount, $this->ucitsVolume);
        }
        
        // Expérience immobilier
        if ($this->hasRealEstateExperience && $this->realEstateOperationsCount && $this->realEstateVolume) {
            $score += $this->getExperienceScore($this->realEstateOperationsCount, $this->realEstateVolume);
        }
        
        // Expérience instruments complexes
        if ($this->hasComplexInstrumentsExperience && $this->complexInstrumentsOperationsCount && $this->complexInstrumentsVolume) {
            $score += $this->getExperienceScore($this->complexInstrumentsOperationsCount, $this->complexInstrumentsVolume);
        }
        
        return $score;
    }

    private function getExperienceScore(string $operationsCount, string $volume): int
    {
        $score = 0;
        
        // Score basé sur le nombre d'opérations
        switch ($operationsCount) {
            case '1 fois':
                $score += 1;
                break;
            case '2-10 fois':
                $score += 3;
                break;
            case '> 10 fois':
                $score += 5;
                break;
        }
        
        // Score basé sur le volume
        switch ($volume) {
            case '< 50 K€':
                $score += 1;
                break;
            case 'de 50 K€ à 150 K€':
                $score += 3;
                break;
            case '> 150 K€':
                $score += 5;
                break;
        }
        
        return $score;
    }
} 