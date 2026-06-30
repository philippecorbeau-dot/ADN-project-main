<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Compte;

/**
 * DTO for an asset line in account situation.
 */
final class AssetLineDTO
{
    public function __construct(
        private readonly ?string $assetId,
        private readonly ?string $assetName,
        private readonly ?string $isin,
        private readonly ?float $quantity,
        private readonly ?float $netAssetValue,
        private readonly ?\DateTimeImmutable $netAssetValueDate,
        private readonly ?float $value,
        private readonly ?string $assetType,
        private readonly ?float $averageBuyPrice = null,
        private readonly ?\DateTimeImmutable $averageBuyPriceDate = null,
        private readonly ?string $averageBuyPriceType = null,
        private readonly ?float $gainLoss = null,
        private readonly ?float $gainLossPercent = null,
        private readonly ?float $percentage = null,
        private readonly ?string $pocketId = null,
        private readonly ?string $currency = null,
    ) {
    }

    public static function fromApiResponse(array $data): self
    {
        // Parse la date de VL de l'actif
        $navDate = null;
        $navDateStr = $data['netAssetValueDate'] ?? $data['dateValeurLiquidative'] ?? null;
        if ($navDateStr) {
            try {
                $navDate = new \DateTimeImmutable($navDateStr);
            } catch (\Exception) {
                // Ignore invalid date
            }
        }

        // Parse averagePrice - l'API O2S retourne un objet imbriqué :
        // "averagePrice": { "averagePriceValue": 43.45, "date": "2024-02-12", "type": "PAMD" }
        $avgBuyPrice = null;
        $avgBuyPriceDate = null;
        $avgBuyPriceType = null;

        if (isset($data['averagePrice']) && is_array($data['averagePrice'])) {
            // Nested object format from the API
            $avgObj = $data['averagePrice'];
            $avgBuyPrice = isset($avgObj['averagePriceValue']) ? (float) $avgObj['averagePriceValue'] : null;
            if (isset($avgObj['date'])) {
                try { $avgBuyPriceDate = new \DateTimeImmutable($avgObj['date']); } catch (\Exception) {}
            }
            $avgBuyPriceType = $avgObj['type'] ?? null;
        } elseif (isset($data['averageBuyPrice'])) {
            $avgBuyPrice = (float) $data['averageBuyPrice'];
        } elseif (isset($data['prixMoyenAchat'])) {
            $avgBuyPrice = (float) $data['prixMoyenAchat'];
        } elseif (isset($data['prixMoyen'])) {
            $avgBuyPrice = (float) $data['prixMoyen'];
        }

        $quantity = isset($data['quantity']) ? (float) $data['quantity'] : (isset($data['quantite']) ? (float) $data['quantite'] : null);
        $value = isset($data['value']) ? (float) $data['value'] : (isset($data['valorisation']) ? (float) $data['valorisation'] : null);

        // Parse +/- value directe ou calculée depuis averagePrice
        $gainLoss = isset($data['gainLoss']) ? (float) $data['gainLoss']
            : (isset($data['plusMoinsValue']) ? (float) $data['plusMoinsValue']
            : (isset($data['plusValue']) ? (float) $data['plusValue'] : null));
        $gainLossPercent = isset($data['gainLossPercent']) ? (float) $data['gainLossPercent']
            : (isset($data['plusMoinsValuePercent']) ? (float) $data['plusMoinsValuePercent']
            : (isset($data['plusValuePercent']) ? (float) $data['plusValuePercent'] : null));

        // Calculer gainLoss depuis averagePrice si non fourni directement
        if ($gainLoss === null && $avgBuyPrice !== null && $avgBuyPrice > 0 && $quantity !== null && $value !== null) {
            $investedAmount = $quantity * $avgBuyPrice;
            $gainLoss = $value - $investedAmount;
            if ($investedAmount > 0) {
                $gainLossPercent = ($gainLoss / $investedAmount) * 100.0;
            }
        }

        return new self(
            assetId: $data['assetId'] ?? $data['actifId'] ?? $data['id'] ?? null,
            assetName: $data['assetName'] ?? $data['libelle'] ?? $data['name'] ?? null,
            isin: $data['isin'] ?? $data['codeIsin'] ?? null,
            quantity: $quantity,
            netAssetValue: isset($data['netAssetValue']) ? (float) $data['netAssetValue'] : (isset($data['valeurLiquidative']) ? (float) $data['valeurLiquidative'] : null),
            netAssetValueDate: $navDate,
            value: $value,
            assetType: $data['assetType'] ?? $data['typeActif'] ?? $data['type'] ?? null,
            averageBuyPrice: $avgBuyPrice,
            averageBuyPriceDate: $avgBuyPriceDate,
            averageBuyPriceType: $avgBuyPriceType,
            gainLoss: $gainLoss,
            gainLossPercent: $gainLossPercent,
            percentage: isset($data['percentage']) ? (float) $data['percentage']
                : (isset($data['pourcentage']) ? (float) $data['pourcentage']
                : (isset($data['poids']) ? (float) $data['poids'] : null)),
            pocketId: $data['pocketId'] ?? null,
            currency: $data['currency'] ?? $data['devise'] ?? null,
        );
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }

    public function getAssetName(): ?string
    {
        return $this->assetName;
    }

    public function getIsin(): ?string
    {
        return $this->isin;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function getNetAssetValue(): ?float
    {
        return $this->netAssetValue;
    }

    public function getNetAssetValueDate(): ?\DateTimeImmutable
    {
        return $this->netAssetValueDate;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function getAssetType(): ?string
    {
        return $this->assetType;
    }

    public function getAverageBuyPrice(): ?float
    {
        return $this->averageBuyPrice;
    }

    public function getAverageBuyPriceDate(): ?\DateTimeImmutable
    {
        return $this->averageBuyPriceDate;
    }

    public function getAverageBuyPriceType(): ?string
    {
        return $this->averageBuyPriceType;
    }

    public function getGainLoss(): ?float
    {
        return $this->gainLoss;
    }

    public function getGainLossPercent(): ?float
    {
        return $this->gainLossPercent;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function getPocketId(): ?string
    {
        return $this->pocketId;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'isin' => $this->isin,
            'quantity' => $this->quantity,
            'netAssetValue' => $this->netAssetValue,
            'netAssetValueDate' => $this->netAssetValueDate?->format('Y-m-d'),
            'value' => $this->value,
            'assetType' => $this->assetType,
            'averageBuyPrice' => $this->averageBuyPrice,
            'averageBuyPriceDate' => $this->averageBuyPriceDate?->format('Y-m-d'),
            'averageBuyPriceType' => $this->averageBuyPriceType,
            'gainLoss' => $this->gainLoss,
            'gainLossPercent' => $this->gainLossPercent,
            'percentage' => $this->percentage,
            'pocketId' => $this->pocketId,
            'currency' => $this->currency,
        ];
    }
}
