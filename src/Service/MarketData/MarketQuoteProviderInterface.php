<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contrat pluggable pour récupérer la VL d'un ISIN auprès d'une source externe.
 *
 * Implémentations actuelles :
 *  - {@see BoursoramaQuoteProvider} : scraping public Boursorama
 *
 * Implémentations futures envisagées (sans changer le code appelant) :
 *  - QuantalysApiQuoteProvider (API Data Quantalys / Harvest)
 *  - MorningstarApiQuoteProvider, FeFundinfoApiQuoteProvider
 *
 * Toute classe qui implémente cette interface est automatiquement enregistrée comme
 * service taggué `app.market_quote_provider` et collectée par {@see MarketQuoteResolver}.
 */
#[AutoconfigureTag('app.market_quote_provider')]
interface MarketQuoteProviderInterface
{
    /**
     * Retourne la cotation (VL) en devise native du support, ou null si indisponible.
     *
     * Doit être idempotente et silencieuse (pas d'exception sur réseau / parsing).
     */
    public function getQuote(string $isin): ?Quote;

    /**
     * Identifiant court de la source (logs, audit, UI). Ex: "boursorama", "quantalys".
     */
    public function getSourceName(): string;
}
