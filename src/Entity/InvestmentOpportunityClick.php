<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\User;
use App\Repository\InvestmentOpportunityClickRepository;

#[ORM\Entity(repositoryClass: InvestmentOpportunityClickRepository::class)]
#[ORM\Table(name: 'investment_opportunity_clicks')]
#[ORM\HasLifecycleCallbacks]
class InvestmentOpportunityClick
{
    const PRODUCT_SCPI = 'SCPI';
    const PRODUCT_PEA_PME = 'PEA-PME';
    const PRODUCT_ASSURANCE_VIE = 'Assurance-vie';
    const PRODUCT_PER = 'PER';
    
    const PRODUCT_TYPES = [
        self::PRODUCT_SCPI => 'SCPI - Sociétés Civiles de Placement Immobilier',
        self::PRODUCT_PEA_PME => 'PEA-PME - Plan d\'Épargne en Actions PME',
        self::PRODUCT_ASSURANCE_VIE => 'Assurance-vie - Contrat d\'assurance-vie',
        self::PRODUCT_PER => 'PER - Plan d\'Épargne Retraite',
    ];
    
    const ACTION_DISCOVER = 'discover';
    const ACTION_DOCUMENTS = 'documents';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private $user;

    #[ORM\Column(type: 'string', length: 50)]
    private $productType;

    #[ORM\Column(type: 'string', length: 20)]
    private $action;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private $ipAddress;

    #[ORM\Column(type: 'text', nullable: true)]
    private $userAgent;

    #[ORM\Column(type: 'datetime_immutable')]
    private $clickedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $referrer;

    public function __construct()
    {
        $this->clickedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getProductType(): ?string
    {
        return $this->productType;
    }

    public function setProductType(string $productType): self
    {
        $this->productType = $productType;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getClickedAt(): ?\DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function setClickedAt(\DateTimeImmutable $clickedAt): self
    {
        $this->clickedAt = $clickedAt;
        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;
        return $this;
    }

    public function getProductTypeLabel(): string
    {
        return self::PRODUCT_TYPES[$this->productType] ?? $this->productType;
    }
}
