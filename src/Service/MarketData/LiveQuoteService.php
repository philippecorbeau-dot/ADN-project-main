<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service de haut niveau pour récupérer la VL "live" d'un ISIN en EUR.
 *
 * Cascade :
 *  1. {@see MarketQuoteResolver} — Boursorama public + conversion FX BCE (priorité)
 *  2. {@see QuoteAggregator}     — Twelve Data / Yahoo (fallback rétrocompatible)
 *
 * C'est ce service qu'on injecte côté Controllers et services O2S qui ont besoin
 * d'une VL fraîche par ISIN. Le format de retour est stable (compatible avec
 * l'ancien usage de `QuoteAggregator::getLast()`), avec en plus les champs
 * `source`, `nativeCurrency`, `fxRate` pour traçabilité UI/audit.
 */
final class LiveQuoteService
{
    public function __construct(
        private readonly MarketQuoteResolver $resolver,
        private readonly QuoteAggregator $fallbackAggregator,
        private readonly IsinAliasResolver $aliasResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Récupère la VL en EUR d'un code (ISIN officiel OU code interne aliasable).
     *
     * Si `$code` est aliasé (cf. config/market_data/isin_aliases.php), on
     * interroge en réalité l'ISIN cible mais on conserve la traçabilité dans
     * les champs `originalCode` + `aliasedToIsin` + `aliasNote`.
     *
     * @return array{
     *   nav: float|null,
     *   navDate: string|null,
     *   source: string|null,
     *   currency: string,
     *   isConverted: bool,
     *   nativeCurrency: string|null,
     *   fxRate: float|null,
     *   originalCode?: string,
     *   aliasedToIsin?: string,
     *   aliasNote?: string,
     * }
     */
    public function getLiveNavEur(string $code): array
    {
        $alias = $this->aliasResolver->resolve($code);
        $effectiveIsin = $alias['isin'] ?? $code;

        $aliasFields = [];
        if ($alias !== null) {
            $aliasFields = [
                'originalCode' => $code,
                'aliasedToIsin' => $alias['isin'],
                'aliasNote' => $alias['note'] ?? null,
            ];
            $this->logger->debug('LiveQuoteService: alias applied', [
                'code' => $code,
                'isin' => $alias['isin'],
                'note' => $alias['note'] ?? null,
            ]);
        }

        // 1. Tentative résolveur Bourso (+ FX) sur l'ISIN effectif
        try {
            $resolved = $this->resolver->resolveEur($effectiveIsin);
            if ($resolved !== null) {
                return array_merge([
                    'nav' => $resolved->quote->nav,
                    'navDate' => $resolved->quote->navDate?->format('Y-m-d'),
                    'source' => $alias !== null
                        ? $resolved->quote->source . ' (via alias)'
                        : $resolved->quote->source,
                    'currency' => 'EUR',
                    'isConverted' => $resolved->isConverted(),
                    'nativeCurrency' => $resolved->isConverted() ? $resolved->nativeQuote->currency : null,
                    'fxRate' => $resolved->fxRate,
                ], $aliasFields);
            }
        } catch (\Throwable $e) {
            $this->logger->info('LiveQuoteService: resolver failed, falling back', [
                'code' => $code,
                'isin' => $effectiveIsin,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Fallback historique : TwelveData / Yahoo
        try {
            $alt = $this->fallbackAggregator->getLast($effectiveIsin);
            if (($alt['nav'] ?? null) !== null) {
                return array_merge([
                    'nav' => (float) $alt['nav'],
                    'navDate' => $alt['navDate'] ?? null,
                    'source' => $alias !== null ? 'twelvedata|yahoo (via alias)' : 'twelvedata|yahoo',
                    'currency' => 'EUR',
                    'isConverted' => false,
                    'nativeCurrency' => null,
                    'fxRate' => null,
                ], $aliasFields);
            }
        } catch (\Throwable $e) {
            $this->logger->info('LiveQuoteService: fallback aggregator failed', [
                'code' => $code,
                'isin' => $effectiveIsin,
                'error' => $e->getMessage(),
            ]);
        }

        return array_merge([
            'nav' => null,
            'navDate' => null,
            'source' => null,
            'currency' => 'EUR',
            'isConverted' => false,
            'nativeCurrency' => null,
            'fxRate' => null,
        ], $aliasFields);
    }
}
