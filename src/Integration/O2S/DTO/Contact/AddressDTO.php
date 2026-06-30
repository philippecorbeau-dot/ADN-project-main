<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Contact;

/**
 * Data Transfer Object for O2S Address.
 */
final class AddressDTO
{
    public function __construct(
        private readonly ?string $voie,
        private readonly ?string $codePostal,
        private readonly ?string $localite,
        private readonly ?string $codePays,
        private readonly ?string $immeuble,
        private readonly ?string $identification,
        private readonly ?string $lieuDit,
    ) {
    }

    /**
     * Creates an AddressDTO from O2S API response.
     * 
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            voie: $data['voie'] ?? null,
            codePostal: $data['codePostal'] ?? null,
            localite: $data['localite'] ?? null,
            codePays: $data['codePays'] ?? null,
            immeuble: $data['immeuble'] ?? null,
            identification: $data['identification'] ?? null,
            lieuDit: $data['lieuDit'] ?? null,
        );
    }

    public function getVoie(): ?string
    {
        return $this->voie;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function getLocalite(): ?string
    {
        return $this->localite;
    }

    public function getCodePays(): ?string
    {
        return $this->codePays;
    }

    public function getImmeuble(): ?string
    {
        return $this->immeuble;
    }

    public function getIdentification(): ?string
    {
        return $this->identification;
    }

    public function getLieuDit(): ?string
    {
        return $this->lieuDit;
    }

    /**
     * Returns formatted address string.
     */
    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->identification,
            $this->immeuble,
            $this->voie,
            $this->lieuDit,
            trim(sprintf('%s %s', $this->codePostal ?? '', $this->localite ?? '')),
            $this->codePays,
        ]);

        return implode(', ', $parts);
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'voie' => $this->voie,
            'codePostal' => $this->codePostal,
            'localite' => $this->localite,
            'codePays' => $this->codePays,
            'immeuble' => $this->immeuble,
            'identification' => $this->identification,
            'lieuDit' => $this->lieuDit,
        ];
    }
}


