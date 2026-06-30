<?php

namespace App\Entity\User\Knowledge;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_knowledge_market_abuse')]
class MarketAbuse
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'has_other_securities_accounts', type: 'boolean', nullable: true)]
    private ?bool $hasOtherSecuritiesAccounts = null;

    #[ORM\Column(name: 'has_financial_profession', type: 'boolean', nullable: true)]
    private ?bool $hasFinancialProfession = null;

    #[ORM\Column(name: 'profession_details', type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $professionDetails = null;

    #[ORM\Column(name: 'is_listed_company_director', type: 'boolean', nullable: true)]
    private ?bool $isListedCompanyDirector = null;

    #[ORM\Column(name: 'listed_company_details', type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $listedCompanyDetails = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHasOtherSecuritiesAccounts(): ?bool
    {
        return $this->hasOtherSecuritiesAccounts;
    }

    public function setHasOtherSecuritiesAccounts(?bool $hasOtherSecuritiesAccounts): self
    {
        $this->hasOtherSecuritiesAccounts = $hasOtherSecuritiesAccounts;
        return $this;
    }

    public function getHasFinancialProfession(): ?bool
    {
        return $this->hasFinancialProfession;
    }

    public function setHasFinancialProfession(?bool $hasFinancialProfession): self
    {
        $this->hasFinancialProfession = $hasFinancialProfession;
        return $this;
    }

    public function getProfessionDetails(): ?string
    {
        return $this->professionDetails;
    }

    public function setProfessionDetails(?string $professionDetails): self
    {
        $this->professionDetails = $professionDetails;
        return $this;
    }

    public function getIsListedCompanyDirector(): ?bool
    {
        return $this->isListedCompanyDirector;
    }

    public function setIsListedCompanyDirector(?bool $isListedCompanyDirector): self
    {
        $this->isListedCompanyDirector = $isListedCompanyDirector;
        return $this;
    }

    public function getListedCompanyDetails(): ?string
    {
        return $this->listedCompanyDetails;
    }

    public function setListedCompanyDetails(?string $listedCompanyDetails): self
    {
        $this->listedCompanyDetails = $listedCompanyDetails;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->hasOtherSecuritiesAccounts !== null && 
               $this->hasFinancialProfession !== null && 
               $this->isListedCompanyDirector !== null;
    }
} 