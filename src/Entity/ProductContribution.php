<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_contributions')]
class ProductContribution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductAccount::class, inversedBy: 'contributions')]
    #[ORM\JoinColumn(name: 'product_account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductAccount $productAccount = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $contributionDate = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductAccount(): ?ProductAccount
    {
        return $this->productAccount;
    }

    public function setProductAccount(?ProductAccount $productAccount): self
    {
        $this->productAccount = $productAccount;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getContributionDate(): ?\DateTimeInterface
    {
        return $this->contributionDate;
    }

    public function setContributionDate(?\DateTimeInterface $contributionDate): self
    {
        $this->contributionDate = $contributionDate;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }
}


