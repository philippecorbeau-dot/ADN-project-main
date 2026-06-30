<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Compte;

/**
 * Data Transfer Object for O2S Detenteur (Account holder).
 */
final class DetenteurDTO
{
    public function __construct(
        private readonly ?string $personneId,
        private readonly ?string $proprietaire,
        private readonly ?float $partPleinePropriete,
        private readonly ?float $partNuePropriete,
        private readonly ?float $partUsufruit,
    ) {
    }

    /**
     * Creates a DetenteurDTO from O2S API response.
     * 
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $pleinePropriete = $data['pleinePropriete'] ?? [];
        $nuePropriete = $data['nuePropriete'] ?? [];
        $usufruit = $data['usufruit'] ?? [];

        return new self(
            personneId: $data['personneId'] ?? null,
            proprietaire: $data['proprietaire'] ?? null,
            partPleinePropriete: isset($pleinePropriete['part']) ? (float) $pleinePropriete['part'] : null,
            partNuePropriete: isset($nuePropriete['part']) ? (float) $nuePropriete['part'] : null,
            partUsufruit: isset($usufruit['part']) ? (float) $usufruit['part'] : null,
        );
    }

    public function getPersonneId(): ?string
    {
        return $this->personneId;
    }

    public function getProprietaire(): ?string
    {
        return $this->proprietaire;
    }

    public function getPartPleinePropriete(): ?float
    {
        return $this->partPleinePropriete;
    }

    public function getPartNuePropriete(): ?float
    {
        return $this->partNuePropriete;
    }

    public function getPartUsufruit(): ?float
    {
        return $this->partUsufruit;
    }

    /**
     * Returns the effective ownership percentage.
     */
    public function getEffectiveOwnership(): float
    {
        // Si pleine propriété est définie, c'est la part principale
        if ($this->partPleinePropriete !== null) {
            return $this->partPleinePropriete;
        }

        // Sinon, additionner nue-propriété et usufruit
        return ($this->partNuePropriete ?? 0.0) + ($this->partUsufruit ?? 0.0);
    }

    public function isTitulaire(): bool
    {
        return $this->proprietaire === 'TITULAIRE';
    }

    public function isCommunaute(): bool
    {
        return $this->proprietaire === 'COMMUNAUTE';
    }
}


