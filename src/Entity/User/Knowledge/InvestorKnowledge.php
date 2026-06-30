<?php

namespace App\Entity\User\Knowledge;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_knowledge_investor')]
#[ORM\HasLifecycleCallbacks]
class InvestorKnowledge
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investorKnowledge')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'is_completed', type: 'boolean', options: ['default' => false])]
    private bool $isCompleted = false;

    #[ORM\Column(name: 'score', type: 'integer', nullable: true)]
    private ?int $score = null;

    #[ORM\Column(name: 'profile_type', type: 'string', length: 50, nullable: true)]
    private ?string $profileType = null;

    #[ORM\OneToOne(targetEntity: MarketAbuse::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'market_abuse_id', referencedColumnName: 'id')]
    private ?MarketAbuse $marketAbuse = null;

    #[ORM\OneToOne(targetEntity: EducationLevel::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'education_level_id', referencedColumnName: 'id')]
    private ?EducationLevel $educationLevel = null;

    #[ORM\OneToOne(targetEntity: InvestmentExperience::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'investment_experience_id', referencedColumnName: 'id')]
    private ?InvestmentExperience $investmentExperience = null;

    #[ORM\OneToOne(targetEntity: FinancialProductsKnowledge::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'financial_products_knowledge_id', referencedColumnName: 'id')]
    private ?FinancialProductsKnowledge $financialProductsKnowledge = null;

    #[ORM\OneToOne(targetEntity: ComplexProductsKnowledge::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'complex_products_knowledge_id', referencedColumnName: 'id')]
    private ?ComplexProductsKnowledge $complexProductsKnowledge = null;

    #[ORM\OneToOne(targetEntity: MarketExperience::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'market_experience_id', referencedColumnName: 'id')]
    private ?MarketExperience $marketExperience = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getProfileType(): ?string
    {
        return $this->profileType;
    }

    public function setProfileType(?string $profileType): self
    {
        $this->profileType = $profileType;
        return $this;
    }

    public function getMarketAbuse(): ?MarketAbuse
    {
        return $this->marketAbuse;
    }

    public function setMarketAbuse(?MarketAbuse $marketAbuse): self
    {
        $this->marketAbuse = $marketAbuse;
        return $this;
    }

    public function getEducationLevel(): ?EducationLevel
    {
        return $this->educationLevel;
    }

    public function setEducationLevel(?EducationLevel $educationLevel): self
    {
        $this->educationLevel = $educationLevel;
        return $this;
    }

    public function getInvestmentExperience(): ?InvestmentExperience
    {
        return $this->investmentExperience;
    }

    public function setInvestmentExperience(?InvestmentExperience $investmentExperience): self
    {
        $this->investmentExperience = $investmentExperience;
        return $this;
    }

    public function getFinancialProductsKnowledge(): ?FinancialProductsKnowledge
    {
        return $this->financialProductsKnowledge;
    }

    public function setFinancialProductsKnowledge(?FinancialProductsKnowledge $financialProductsKnowledge): self
    {
        $this->financialProductsKnowledge = $financialProductsKnowledge;
        return $this;
    }

    public function getComplexProductsKnowledge(): ?ComplexProductsKnowledge
    {
        return $this->complexProductsKnowledge;
    }

    public function setComplexProductsKnowledge(?ComplexProductsKnowledge $complexProductsKnowledge): self
    {
        $this->complexProductsKnowledge = $complexProductsKnowledge;
        return $this;
    }

    public function getMarketExperience(): ?MarketExperience
    {
        return $this->marketExperience;
    }

    public function setMarketExperience(?MarketExperience $marketExperience): self
    {
        $this->marketExperience = $marketExperience;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->marketAbuse && 
               $this->educationLevel && 
               $this->investmentExperience && 
               $this->financialProductsKnowledge && 
               $this->complexProductsKnowledge && 
               $this->marketExperience;
    }
} 