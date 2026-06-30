<?php

namespace App\Entity;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'product_accounts')]
#[ORM\Index(name: 'idx_o2s_compte_id', columns: ['o2s_compte_id'])]
class ProductAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $distributor = 'Generali';

    // internalName conservé pour compatibilité; non exposé dans le formulaire
    #[ORM\Column(length: 255)]
    private string $internalName = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayAlias = null;

    #[ORM\Column(length: 30)]
    private string $productType = 'ASSURANCE_VIE';

    // Fonds Euro (pour Assurance-vie / PER). Pour rester flexible, valeur numérique décimale.
    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $euroFund = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $fiscalDate;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $initialAmount = '0.00';

    #[ORM\OneToMany(mappedBy: 'productAccount', targetEntity: ProductContribution::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $contributions;

    // ==================== O2S INTEGRATION ====================

    #[ORM\Column(name: 'o2s_compte_id', type: 'string', length: 100, nullable: true)]
    private ?string $o2sCompteId = null;

    #[ORM\Column(name: 'o2s_synced_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $o2sSyncedAt = null;

    #[ORM\Column(name: 'o2s_valuation', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $o2sValuation = null;

    #[ORM\Column(name: 'o2s_valuation_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $o2sValuationDate = null;

    public function __construct()
    {
        $this->fiscalDate = new \DateTime();
        $this->contributions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getDistributor(): string
    {
        return $this->distributor;
    }

    public function setDistributor(string $distributor): self
    {
        $this->distributor = $distributor;
        return $this;
    }

    public function getInternalName(): string
    {
        return $this->internalName;
    }

    public function setInternalName(string $internalName): self
    {
        $this->internalName = $internalName;
        return $this;
    }

    public function getDisplayAlias(): ?string
    {
        return $this->displayAlias;
    }

    public function setDisplayAlias(?string $displayAlias): self
    {
        $this->displayAlias = $displayAlias;
        return $this;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    public function setProductType(string $productType): self
    {
        $this->productType = $productType;
        return $this;
    }

    public function getEuroFund(): ?string
    {
        return $this->euroFund;
    }

    public function setEuroFund(?string $euroFund): self
    {
        $this->euroFund = $euroFund;
        return $this;
    }

    public function getFiscalDate(): \DateTimeInterface
    {
        return $this->fiscalDate;
    }

    public function setFiscalDate(\DateTimeInterface $fiscalDate): self
    {
        $this->fiscalDate = $fiscalDate;
        return $this;
    }

    public function getInitialAmount(): string
    {
        return $this->initialAmount;
    }

    public function setInitialAmount(string $initialAmount): self
    {
        $this->initialAmount = $initialAmount;
        return $this;
    }

    /**
     * @return Collection<int, ProductContribution>
     */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }

    public function addContribution(ProductContribution $contribution): self
    {
        if (!$this->contributions->contains($contribution)) {
            $this->contributions->add($contribution);
            $contribution->setProductAccount($this);
        }
        return $this;
    }

    public function removeContribution(ProductContribution $contribution): self
    {
        if ($this->contributions->removeElement($contribution)) {
            if ($contribution->getProductAccount() === $this) {
                $contribution->setProductAccount(null);
            }
        }
        return $this;
    }

    /**
     * Retourne le total investi:
     * montant initial + somme des versements additionnels + fonds euro (si renseigné).
     */
    public function getTotalInvested(): float
    {
        $total = (float) $this->initialAmount;
        foreach ($this->contributions as $c) {
            $total += (float) $c->getAmount();
        }
        $total += $this->euroFund !== null ? (float) $this->euroFund : 0.0;
        return $total;
    }

    // ==================== O2S INTEGRATION METHODS ====================

    /**
     * Get the O2S Compte ID linked to this product.
     */
    public function getO2sCompteId(): ?string
    {
        return $this->o2sCompteId;
    }

    /**
     * Set the O2S Compte ID.
     */
    public function setO2sCompteId(?string $o2sCompteId): self
    {
        $this->o2sCompteId = $o2sCompteId;
        return $this;
    }

    /**
     * Get the last O2S synchronization timestamp.
     */
    public function getO2sSyncedAt(): ?\DateTimeImmutable
    {
        return $this->o2sSyncedAt;
    }

    /**
     * Set the last O2S synchronization timestamp.
     */
    public function setO2sSyncedAt(?\DateTimeImmutable $o2sSyncedAt): self
    {
        $this->o2sSyncedAt = $o2sSyncedAt;
        return $this;
    }

    /**
     * Get the O2S valuation amount.
     */
    public function getO2sValuation(): ?string
    {
        return $this->o2sValuation;
    }

    /**
     * Set the O2S valuation amount.
     */
    public function setO2sValuation(?string $o2sValuation): self
    {
        $this->o2sValuation = $o2sValuation;
        return $this;
    }

    /**
     * Get the O2S valuation date.
     */
    public function getO2sValuationDate(): ?\DateTimeInterface
    {
        return $this->o2sValuationDate;
    }

    /**
     * Set the O2S valuation date.
     */
    public function setO2sValuationDate(?\DateTimeInterface $o2sValuationDate): self
    {
        $this->o2sValuationDate = $o2sValuationDate;
        return $this;
    }

    /**
     * Check if product is linked to an O2S compte.
     */
    public function isLinkedToO2S(): bool
    {
        return $this->o2sCompteId !== null;
    }

    /**
     * Get the current valuation (O2S if available, otherwise calculated).
     */
    public function getCurrentValuation(): float
    {
        if ($this->o2sValuation !== null) {
            return (float) $this->o2sValuation;
        }

        return $this->getTotalInvested();
    }

    /**
     * Get the valuation source ('o2s' or 'calculated').
     */
    public function getValuationSource(): string
    {
        return $this->o2sValuation !== null ? 'o2s' : 'calculated';
    }

    /**
     * Check if O2S valuation needs refresh (older than given hours).
     */
    public function needsO2SRefresh(int $maxAgeHours = 24): bool
    {
        if (!$this->isLinkedToO2S()) {
            return false;
        }

        if ($this->o2sSyncedAt === null) {
            return true;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d hours', $maxAgeHours));
        return $this->o2sSyncedAt < $threshold;
    }
}


