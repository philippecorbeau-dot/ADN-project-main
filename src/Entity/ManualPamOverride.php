<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'manual_pam_overrides')]
#[ORM\UniqueConstraint(name: 'uniq_pam_account_asset', columns: ['product_account_id', 'asset_id'])]
class ManualPamOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductAccount::class)]
    #[ORM\JoinColumn(name: 'product_account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductAccount $productAccount = null;

    #[ORM\Column(name: 'asset_id', type: 'string', length: 100)]
    private string $assetId = '';

    #[ORM\Column(name: 'asset_label', type: 'string', length: 255)]
    private string $assetLabel = '';

    #[ORM\Column(name: 'pam_value', type: 'decimal', precision: 18, scale: 6)]
    private string $pamValue = '0';

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function setAssetId(string $assetId): self
    {
        $this->assetId = $assetId;
        return $this;
    }

    public function getAssetLabel(): string
    {
        return $this->assetLabel;
    }

    public function setAssetLabel(string $assetLabel): self
    {
        $this->assetLabel = $assetLabel;
        return $this;
    }

    public function getPamValue(): string
    {
        return $this->pamValue;
    }

    public function setPamValue(string $pamValue): self
    {
        $this->pamValue = $pamValue;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
