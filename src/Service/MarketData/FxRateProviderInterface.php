<?php

declare(strict_types=1);

namespace App\Service\MarketData;

/**
 * Contrat d'un fournisseur de taux de change avec EUR comme base.
 *
 * Implémentation par défaut : {@see EcbFxRateProvider} (taux officiels BCE, gratuits).
 * Alternatives possibles : ExchangeRate.host, OpenExchangeRates, Fixer.io…
 */
interface FxRateProviderInterface
{
    /**
     * Retourne tous les taux EUR → X disponibles ce jour, ou null si indisponible.
     *
     * @return array{date: \DateTimeImmutable, rates: array<string, float>}|null
     */
    public function getRates(): ?array;

    /**
     * Taux 1 EUR = X {currency}. Null si devise non couverte ou source indisponible.
     */
    public function getRate(string $currency): ?float;
}
