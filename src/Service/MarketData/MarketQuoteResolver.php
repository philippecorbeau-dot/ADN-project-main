<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Orchestre la récupération d'une VL "utilisable" en EUR pour un ISIN donné.
 *
 * Étapes (dans l'ordre, court-circuit dès qu'une étape réussit) :
 *  1. Provider primaire (Boursorama aujourd'hui)
 *     - si devise = EUR → on renvoie directement
 *     - si devise ≠ EUR → conversion FX via BCE
 *  2. Providers secondaires (à venir : Quantalys API, FE fundinfo…)
 *  3. Échec → null (l'appelant fait son fallback, typiquement la VL O2S figée)
 *
 * Le résultat est encapsulé dans un {@see ResolvedQuote} qui trace la chaîne
 * (source réelle, taux FX appliqué, devise originale) pour audit et UI.
 */
final class MarketQuoteResolver
{
    /** @var MarketQuoteProviderInterface[] */
    private array $providers;

    /**
     * @param iterable<MarketQuoteProviderInterface> $providers
     */
    public function __construct(
        #[TaggedIterator('app.market_quote_provider')]
        iterable $providers,
        private readonly FxConverter $fxConverter,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->providers = $providers instanceof \Traversable ? iterator_to_array($providers) : (array) $providers;
    }

    /**
     * Résout la VL en EUR pour un ISIN. Null si aucun provider ne réussit.
     */
    public function resolveEur(string $isin): ?ResolvedQuote
    {
        return $this->resolve($isin, 'EUR');
    }

    public function resolve(string $isin, string $targetCurrency = 'EUR'): ?ResolvedQuote
    {
        $isin = strtoupper(trim($isin));
        $targetCurrency = strtoupper($targetCurrency);

        foreach ($this->providers as $provider) {
            $quote = $provider->getQuote($isin);
            if ($quote === null) {
                continue;
            }

            // Cas 1 : déjà dans la bonne devise
            if (strtoupper($quote->currency) === $targetCurrency) {
                return new ResolvedQuote(
                    quote: $quote,
                    nativeQuote: $quote,
                    fxRate: null,
                );
            }

            // Cas 2 : conversion requise
            $converted = $this->fxConverter->convertQuote($quote, $targetCurrency);
            if ($converted === null) {
                $this->logger->info('Resolver: FX conversion impossible, on tente le provider suivant', [
                    'isin' => $isin,
                    'provider' => $provider->getSourceName(),
                    'nativeCurrency' => $quote->currency,
                    'target' => $targetCurrency,
                ]);
                continue;
            }

            $fxRate = $quote->nav != 0.0 ? ($converted->nav / $quote->nav) : null;

            return new ResolvedQuote(
                quote: $converted,
                nativeQuote: $quote,
                fxRate: $fxRate,
            );
        }

        return null;
    }
}
