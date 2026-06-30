<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Compte;

/**
 * DTO for O2S Account Details with valuation.
 */
final class AccountDetailsDTO
{
    /**
     * @param AssetLineDTO[] $situation
     */
    public function __construct(
        private readonly string $accountId,
        private readonly ?float $totalValue,
        private readonly ?float $liquidity,
        private readonly array $situation = [],
        private readonly ?\DateTimeImmutable $valuationDate = null,
        private readonly ?float $versements = null,
        private readonly ?float $totalGainLoss = null,
        private readonly ?float $totalGainLossPercent = null,
        private readonly ?float $retraits = null,
    ) {
    }

    public static function fromApiResponse(string $accountId, array $data): self
    {
        // L'API peut renvoyer les données dans un tableau (data[0])
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $situation = [];
        if (isset($data['situation']) && is_array($data['situation'])) {
            foreach ($data['situation'] as $asset) {
                $situation[] = AssetLineDTO::fromApiResponse($asset);
            }
        }

        $valuationDate = null;
        $dateStr = $data['referenceDate'] ?? $data['dateValeur'] ?? $data['valuationDate'] ?? null;
        if ($dateStr) {
            try {
                $valuationDate = new \DateTimeImmutable($dateStr);
            } catch (\Exception) {
                // Ignore invalid date
            }
        }

        // Extraire versements (payments) - l'API peut les fournir dans différents champs
        $versements = self::extractFloat($data, ['versements', 'totalVersements', 'payments', 'totalPayments']);
        $retraits = self::extractFloat($data, ['retraits', 'totalRetraits', 'withdrawals', 'totalWithdrawals']);

        // Extraire les plus/moins-values globales
        $totalGainLoss = self::extractFloat($data, ['gainLoss', 'plusMoinsValue', 'totalGainLoss', 'plusValue']);
        $totalGainLossPercent = self::extractFloat($data, ['gainLossPercent', 'plusMoinsValuePercent', 'totalGainLossPercent', 'plusValuePercent']);

        return new self(
            accountId: $accountId,
            totalValue: isset($data['totalValue']) ? (float) $data['totalValue'] : (isset($data['valeurTotale']) ? (float) $data['valeurTotale'] : null),
            liquidity: isset($data['liquidity']) ? (float) $data['liquidity'] : (isset($data['liquidite']) ? (float) $data['liquidite'] : null),
            situation: $situation,
            valuationDate: $valuationDate,
            versements: $versements,
            totalGainLoss: $totalGainLoss,
            totalGainLossPercent: $totalGainLossPercent,
            retraits: $retraits,
        );
    }

    /**
     * Extracts a float value from data trying multiple keys.
     */
    private static function extractFloat(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (float) $data[$key];
            }
        }
        return null;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getTotalValue(): ?float
    {
        return $this->totalValue;
    }

    public function getLiquidity(): ?float
    {
        return $this->liquidity;
    }

    /**
     * @return AssetLineDTO[]
     */
    public function getSituation(): array
    {
        return $this->situation;
    }

    public function getValuationDate(): ?\DateTimeImmutable
    {
        return $this->valuationDate;
    }

    public function getVersements(): ?float
    {
        return $this->versements;
    }

    public function getRetraits(): ?float
    {
        return $this->retraits;
    }

    public function getTotalGainLoss(): ?float
    {
        return $this->totalGainLoss;
    }

    public function getTotalGainLossPercent(): ?float
    {
        return $this->totalGainLossPercent;
    }

    public function hasValuation(): bool
    {
        return $this->totalValue !== null;
    }

    /**
     * Calculates gain/loss from totalValue and versements if not provided directly by API.
     */
    public function getComputedGainLoss(): ?float
    {
        if ($this->totalGainLoss !== null) {
            return $this->totalGainLoss;
        }
        if ($this->totalValue !== null && $this->versements !== null) {
            return $this->totalValue - $this->versements + ($this->retraits ?? 0.0);
        }
        return null;
    }

    /**
     * Calculates gain/loss percent from totalValue and versements if not provided directly by API.
     */
    public function getComputedGainLossPercent(): ?float
    {
        if ($this->totalGainLossPercent !== null) {
            return $this->totalGainLossPercent;
        }
        $gl = $this->getComputedGainLoss();
        if ($gl !== null && $this->versements !== null && $this->versements > 0) {
            return ($gl / $this->versements) * 100.0;
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId,
            'totalValue' => $this->totalValue,
            'liquidity' => $this->liquidity,
            'versements' => $this->versements,
            'retraits' => $this->retraits,
            'totalGainLoss' => $this->totalGainLoss,
            'totalGainLossPercent' => $this->totalGainLossPercent,
            'situation' => array_map(fn(AssetLineDTO $a) => $a->toArray(), $this->situation),
            'valuationDate' => $this->valuationDate?->format('Y-m-d'),
        ];
    }
}
