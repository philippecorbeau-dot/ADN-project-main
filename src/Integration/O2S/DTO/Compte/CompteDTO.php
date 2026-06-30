<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Compte;

/**
 * Data Transfer Object for O2S Compte (Account/Contract).
 */
final class CompteDTO
{
    /**
     * @param array<DetenteurDTO> $detenteurs
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $libelle,
        private readonly ?string $numero,
        private readonly ?string $type,
        private readonly ?string $modeleFinancier,
        private readonly ?string $produitId,
        private readonly ?string $statut,
        private readonly ?\DateTimeImmutable $dateOuverture,
        private readonly ?\DateTimeImmutable $dateTerme,
        private readonly ?float $montant,
        private readonly ?string $devise,
        private readonly ?\DateTimeImmutable $dateValeur,
        private readonly array $detenteurs,
        private readonly ?\DateTimeImmutable $dateCreation,
        private readonly ?\DateTimeImmutable $dateMaj,
        private readonly array $rawData,
    ) {
    }

    /**
     * Creates a CompteDTO from O2S API response.
     * 
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $identification = $data['identification'] ?? [];
        $produitLie = $data['produitLie'] ?? [];
        $placement = $data['placement'] ?? [];
        $valeur = $data['valeur'] ?? [];
        $detenteursData = $data['detenteurs'] ?? [];

        // Parse detenteurs
        $detenteurs = [];
        if (!empty($detenteursData['detenteurs'])) {
            foreach ($detenteursData['detenteurs'] as $det) {
                $detenteurs[] = DetenteurDTO::fromApiResponse($det);
            }
        }

        // Parse dates
        $dateOuverture = isset($placement['dateOuverture'])
            ? self::parseDate($placement['dateOuverture'])
            : null;

        $dateTerme = isset($placement['dateTerme'])
            ? self::parseDate($placement['dateTerme'])
            : null;

        $dateValeur = isset($valeur['dateValeur'])
            ? self::parseDate($valeur['dateValeur'])
            : null;

        $dateCreation = isset($data['dateCreation'])
            ? self::parseDateTime($data['dateCreation'])
            : null;

        $dateMaj = isset($data['dateMaj'])
            ? self::parseDateTime($data['dateMaj'])
            : null;

        return new self(
            id: $data['id'] ?? '',
            libelle: $data['libelle'] ?? null,
            numero: $identification['numero'] ?? null,
            type: $data['type'] ?? null,
            modeleFinancier: $data['modeleFinancier'] ?? null,
            produitId: $produitLie['produitId'] ?? null,
            statut: $placement['statut'] ?? null,
            dateOuverture: $dateOuverture,
            dateTerme: $dateTerme,
            montant: isset($valeur['montant']) ? (float) $valeur['montant'] : null,
            devise: $valeur['devise'] ?? 'EUR',
            dateValeur: $dateValeur,
            detenteurs: $detenteurs,
            dateCreation: $dateCreation,
            dateMaj: $dateMaj,
            rawData: $data,
        );
    }

    private static function parseDate(string $date): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseDateTime(string $datetime): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($datetime);
        } catch (\Throwable) {
            return null;
        }
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getModeleFinancier(): ?string
    {
        return $this->modeleFinancier;
    }

    public function getProduitId(): ?string
    {
        return $this->produitId;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function isActif(): bool
    {
        return $this->statut === 'ACTIF';
    }

    public function getDateOuverture(): ?\DateTimeImmutable
    {
        return $this->dateOuverture;
    }

    public function getDateTerme(): ?\DateTimeImmutable
    {
        return $this->dateTerme;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function getDateValeur(): ?\DateTimeImmutable
    {
        return $this->dateValeur;
    }

    /**
     * @return array<DetenteurDTO>
     */
    public function getDetenteurs(): array
    {
        return $this->detenteurs;
    }

    /**
     * Returns the primary detenteur (first one).
     */
    public function getPrimaryDetenteur(): ?DetenteurDTO
    {
        return $this->detenteurs[0] ?? null;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getDateMaj(): ?\DateTimeImmutable
    {
        return $this->dateMaj;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Maps O2S modeleFinancier to ADN product type.
     */
    public function getProductType(): string
    {
        // Mapping des modèles financiers O2S vers les types ADN
        return match ($this->modeleFinancier) {
            'ASSURANCE_UC', 'ASSURANCE_EURO', 'ASSURANCE_EURO_CROISSANCE' => 'ASSURANCE_VIE',
            'PER', 'PERIN_ASSURANTIEL', 'PERIN_COMPTE_TITRES', 'PERP_EURO', 'PERP_UC' => 'PER',
            'PEA', 'PEA_PME' => 'PEA_PME',
            'SCPI' => 'SCPI',
            'COMPTE_TITRE' => 'COMPTE_TITRES',
            default => $this->modeleFinancier ?? 'AUTRE',
        };
    }

    /**
     * Gets the versements (total payments) from raw API data.
     * The field may appear in different locations depending on API version.
     */
    public function getVersements(): ?float
    {
        // Check valeur.versements
        $valeur = $this->rawData['valeur'] ?? [];
        if (isset($valeur['versements']) && is_numeric($valeur['versements'])) {
            return (float) $valeur['versements'];
        }

        // Check placement.versements or placement.totalVersements
        $placement = $this->rawData['placement'] ?? [];
        foreach (['versements', 'totalVersements', 'payments'] as $key) {
            if (isset($placement[$key]) && is_numeric($placement[$key])) {
                return (float) $placement[$key];
            }
        }

        // Check top-level versements
        foreach (['versements', 'totalVersements'] as $key) {
            if (isset($this->rawData[$key]) && is_numeric($this->rawData[$key])) {
                return (float) $this->rawData[$key];
            }
        }

        return null;
    }

    /**
     * Gets the retraits (total withdrawals) from raw API data.
     */
    public function getRetraits(): ?float
    {
        $valeur = $this->rawData['valeur'] ?? [];
        if (isset($valeur['retraits']) && is_numeric($valeur['retraits'])) {
            return (float) $valeur['retraits'];
        }

        $placement = $this->rawData['placement'] ?? [];
        foreach (['retraits', 'totalRetraits', 'withdrawals'] as $key) {
            if (isset($placement[$key]) && is_numeric($placement[$key])) {
                return (float) $placement[$key];
            }
        }

        return null;
    }

    /**
     * Gets formatted display name.
     */
    public function getDisplayName(): string
    {
        if ($this->libelle) {
            return $this->libelle;
        }

        $parts = array_filter([
            $this->getProductType(),
            $this->numero,
        ]);

        return implode(' - ', $parts) ?: sprintf('Compte %s', $this->id);
    }
}


