<?php

namespace App\Entity\User\Knowledge;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_knowledge_investment_experience')]
class InvestmentExperience
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'has_lost_significant_amounts', type: 'boolean', nullable: true)]
    private ?bool $hasLostSignificantAmounts = null;

    #[ORM\Column(name: 'portfolio_loss_percentage', type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $portfolioLossPercentage = null;

    #[ORM\Column(name: 'manages_own_portfolio', type: 'boolean', nullable: true)]
    private ?bool $managesOwnPortfolio = null;

    #[ORM\Column(name: 'portfolio_securities_lines', type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 1000)]
    private ?int $portfolioSecuritiesLines = null;

    #[ORM\Column(name: 'concentrates_on_single_security', type: 'boolean', nullable: true)]
    private ?bool $concentratesOnSingleSecurity = null;

    #[ORM\Column(name: 'appropriateness_test_performed', type: 'boolean', nullable: true)]
    private ?bool $appropriatenessTestPerformed = null;

    #[ORM\Column(name: 'orders_through_cif', type: 'boolean', nullable: true)]
    private ?bool $ordersThroughCif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHasLostSignificantAmounts(): ?bool
    {
        return $this->hasLostSignificantAmounts;
    }

    public function setHasLostSignificantAmounts(?bool $hasLostSignificantAmounts): self
    {
        $this->hasLostSignificantAmounts = $hasLostSignificantAmounts;
        return $this;
    }

    public function getPortfolioLossPercentage(): ?int
    {
        return $this->portfolioLossPercentage;
    }

    public function setPortfolioLossPercentage(?int $portfolioLossPercentage): self
    {
        $this->portfolioLossPercentage = $portfolioLossPercentage;
        return $this;
    }

    public function getManagesOwnPortfolio(): ?bool
    {
        return $this->managesOwnPortfolio;
    }

    public function setManagesOwnPortfolio(?bool $managesOwnPortfolio): self
    {
        $this->managesOwnPortfolio = $managesOwnPortfolio;
        return $this;
    }

    public function getPortfolioSecuritiesLines(): ?int
    {
        return $this->portfolioSecuritiesLines;
    }

    public function setPortfolioSecuritiesLines(?int $portfolioSecuritiesLines): self
    {
        $this->portfolioSecuritiesLines = $portfolioSecuritiesLines;
        return $this;
    }

    public function getConcentratesOnSingleSecurity(): ?bool
    {
        return $this->concentratesOnSingleSecurity;
    }

    public function setConcentratesOnSingleSecurity(?bool $concentratesOnSingleSecurity): self
    {
        $this->concentratesOnSingleSecurity = $concentratesOnSingleSecurity;
        return $this;
    }

    public function getAppropriatenessTestPerformed(): ?bool
    {
        return $this->appropriatenessTestPerformed;
    }

    public function setAppropriatenessTestPerformed(?bool $appropriatenessTestPerformed): self
    {
        $this->appropriatenessTestPerformed = $appropriatenessTestPerformed;
        return $this;
    }

    public function getOrdersThroughCif(): ?bool
    {
        return $this->ordersThroughCif;
    }

    public function setOrdersThroughCif(?bool $ordersThroughCif): self
    {
        $this->ordersThroughCif = $ordersThroughCif;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->hasLostSignificantAmounts !== null && 
               $this->managesOwnPortfolio !== null && 
               $this->concentratesOnSingleSecurity !== null && 
               $this->appropriatenessTestPerformed !== null && 
               $this->ordersThroughCif !== null;
    }
} 