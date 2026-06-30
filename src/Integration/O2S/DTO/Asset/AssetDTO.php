<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Asset;

/**
 * DTO for O2S Asset (Fonds, Titres, UC).
 */
final class AssetDTO
{
    public function __construct(
        private readonly string $assetId,
        private readonly ?string $label,
        private readonly ?string $isin,
        private readonly ?string $currency,
        private readonly ?string $assetType,
        private readonly ?string $assetClass,
        private readonly ?string $managementCompany,
    ) {
    }

    public static function fromApiResponse(array $data): self
    {
        return new self(
            assetId: $data['assetId'] ?? $data['id'] ?? '',
            label: $data['label'] ?? $data['libelle'] ?? $data['name'] ?? null,
            isin: $data['isin'] ?? $data['codeIsin'] ?? null,
            currency: $data['currency'] ?? $data['devise'] ?? null,
            assetType: $data['assetType'] ?? $data['type'] ?? $data['typeActif'] ?? null,
            assetClass: $data['assetClass'] ?? $data['classeActif'] ?? null,
            managementCompany: $data['managementCompany'] ?? $data['societeGestion'] ?? null,
        );
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getIsin(): ?string
    {
        return $this->isin;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getAssetType(): ?string
    {
        return $this->assetType;
    }

    public function getAssetClass(): ?string
    {
        return $this->assetClass;
    }

    public function getManagementCompany(): ?string
    {
        return $this->managementCompany;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assetId' => $this->assetId,
            'label' => $this->label,
            'isin' => $this->isin,
            'currency' => $this->currency,
            'assetType' => $this->assetType,
            'assetClass' => $this->assetClass,
            'managementCompany' => $this->managementCompany,
        ];
    }
}

