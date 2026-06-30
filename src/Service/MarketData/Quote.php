<?php

declare(strict_types=1);

namespace App\Service\MarketData;

/**
 * Représentation immuable d'une cotation (VL) pour un ISIN, indépendante du fournisseur.
 *
 * Toujours en devise native du support. La conversion FX est appliquée plus haut dans
 * la chaîne (cf. {@see FxConverter}, {@see MarketQuoteResolver}).
 */
final class Quote
{
    public function __construct(
        public readonly string $isin,
        public readonly float $nav,
        public readonly string $currency,
        public readonly ?\DateTimeImmutable $navDate,
        public readonly string $source,
        public readonly ?string $sourceUrl = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isin' => $this->isin,
            'nav' => $this->nav,
            'currency' => $this->currency,
            'navDate' => $this->navDate?->format('Y-m-d'),
            'source' => $this->source,
            'sourceUrl' => $this->sourceUrl,
        ];
    }
}
