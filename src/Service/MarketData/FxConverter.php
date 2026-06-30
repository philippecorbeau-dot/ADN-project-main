<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Convertit un montant d'une devise vers une autre via les taux BCE.
 *
 * Logique simple : 1 EUR = X devise (taux BCE).
 * Donc pour convertir A {src} → ? {dst} :
 *   value_eur = A / rate_src
 *   value_dst = value_eur * rate_dst
 *
 * Utilisation typique côté ADN : USD/GBP/CHF/JPY/CAD/AUD → EUR pour aligner sur l'extranet assureur.
 */
final class FxConverter
{
    public function __construct(
        private readonly FxRateProviderInterface $rateProvider,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Convertit $amount de $from vers $to. Retourne null si taux indisponible.
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $bag = $this->rateProvider->getRates();
        if ($bag === null) {
            $this->logger->warning('FX conversion failed: rates unavailable', ['from' => $from, 'to' => $to]);
            return null;
        }

        $rates = $bag['rates'];
        $rateFrom = $rates[$from] ?? null;
        $rateTo = $rates[$to] ?? null;

        if ($rateFrom === null || $rateTo === null) {
            $this->logger->warning('FX conversion failed: currency unknown', [
                'from' => $from,
                'to' => $to,
                'rateFrom' => $rateFrom,
                'rateTo' => $rateTo,
            ]);
            return null;
        }

        $eur = $amount / $rateFrom;
        return $eur * $rateTo;
    }

    /**
     * Convertit une Quote dans sa devise native vers la devise cible.
     * Retourne null si conversion impossible.
     */
    public function convertQuote(Quote $quote, string $targetCurrency): ?Quote
    {
        if (strtoupper($quote->currency) === strtoupper($targetCurrency)) {
            return $quote;
        }
        $converted = $this->convert($quote->nav, $quote->currency, $targetCurrency);
        if ($converted === null) {
            return null;
        }
        return new Quote(
            isin: $quote->isin,
            nav: $converted,
            currency: strtoupper($targetCurrency),
            navDate: $quote->navDate,
            source: $quote->source . '+fx',
            sourceUrl: $quote->sourceUrl,
        );
    }
}
