<?php

declare(strict_types=1);

namespace App\Service\MarketData;

/**
 * Quote "finale" prête à l'usage (en devise cible), accompagnée de sa traçabilité
 * (devise native, taux FX appliqué) pour audit et affichage UI.
 */
final class ResolvedQuote
{
    public function __construct(
        public readonly Quote $quote,         // VL convertie dans la devise cible
        public readonly Quote $nativeQuote,   // VL d'origine (devise native du support)
        public readonly ?float $fxRate,       // taux appliqué (= quote.nav / nativeQuote.nav), null si pas de conversion
    ) {
    }

    public function isConverted(): bool
    {
        return $this->fxRate !== null;
    }

    public function toArray(): array
    {
        return [
            'quote' => $this->quote->toArray(),
            'nativeQuote' => $this->isConverted() ? $this->nativeQuote->toArray() : null,
            'fxRate' => $this->fxRate,
        ];
    }
}
