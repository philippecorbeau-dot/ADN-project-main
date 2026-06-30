<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Product;

/**
 * DTO for O2S Product (contract type).
 */
final class ProductDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly ?string $label,
        public readonly ?string $type,
        public readonly ?string $institutionId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            productId: (string) ($data['productId'] ?? ''),
            label: $data['label'] ?? null,
            type: $data['type'] ?? null,
            institutionId: $data['institutionId'] ?? null,
        );
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getInstitutionId(): ?string
    {
        return $this->institutionId;
    }

    /**
     * Returns a human-readable type label.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'ASSURANCE_UC' => 'Assurance Vie UC',
            'ASSURANCE_EURO' => 'Assurance Vie Euros',
            'BON_CAPI_UC' => 'Bon de Capitalisation UC',
            'BON_CAPI_EURO' => 'Bon de Capitalisation Euros',
            'PEA' => 'PEA',
            'PEA_PME' => 'PEA-PME',
            'COMPTE_TITRE' => 'Compte-Titres',
            'PER' => 'PER',
            'PERP' => 'PERP',
            'PERCO' => 'PERCO',
            'ARTICLE_83' => 'Article 83',
            'MADELIN' => 'Madelin',
            'LIVRET' => 'Livret',
            default => $this->type ?? 'Autre',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'label' => $this->label,
            'type' => $this->type,
            'typeLabel' => $this->getTypeLabel(),
            'institutionId' => $this->institutionId,
        ];
    }
}

