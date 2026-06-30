<?php

namespace App\Service\MarketData;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Agrégateur de quotes robuste: tente Twelve Data en priorité (aligné sur l'UI Twelve Data),
 * puis retombe sur Yahoo Finance si nécessaire. Ne fournit QUE dernière VL + date.
 */
class QuoteAggregator
{
    /** cache 15 minutes */
    private const CACHE_TTL = 900;
    private array $memoryCache = [];
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly TwelveDataClient $twelve,
        private readonly LoggerInterface $logger,
    ) {}

    private function tryTwelve(string $symbol, ?string $exchange, ?string $hintType = null): array
    {
        // Heuristique: 0P… = Mutual Fund Twelve Data -> time_series
        $looksLikeFund = ($hintType && stripos($hintType, 'fund') !== false) || preg_match('/^0P/i', $symbol);
        try {
            if ($looksLikeFund) {
                $series = $this->twelve->getTimeSeries($symbol, '1day', '1');
                if (!empty($series)) {
                    $first = $series[0];
                    return [
                        'nav' => isset($first['close']) ? (float)$first['close'] : null,
                        'navDate' => $first['datetime'] ?? null,
                    ];
                }
            } else {
                // Équity: quote puis fallback time_series (avec puis sans exchange)
                $q = $this->twelve->getQuote($symbol);
                if (!empty($q) && isset($q['close'])) {
                    return [
                        'nav' => (float) $q['close'],
                        'navDate' => date('Y-m-d'),
                    ];
                }
                $series = $this->twelve->getTimeSeries($symbol, '1day', '1');
                if (empty($series)) {
                    $series = $this->twelve->getTimeSeries($symbol, '1day', '1');
                }
                if (!empty($series)) {
                    $first = $series[0];
                    return [
                        'nav' => isset($first['close']) ? (float)$first['close'] : null,
                        'navDate' => $first['datetime'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->info('tryTwelve failed', ['symbol' => $symbol, 'message' => $e->getMessage()]);
        }
        return ['nav' => null, 'navDate' => null];
    }

    private function tryYahoo(string $symbol, ?string $exchange): array
    {
        $candidates = [];
        // Si le symbole contient déjà un suffixe, essayer tel quel
        if (str_contains($symbol, '.')) {
            $candidates[] = $symbol;
        } else {
            // Pour actions Euronext
            $map = ['XPAR'=>'.PA','XAMS'=>'.AS','XBRU'=>'.BR','XLIS'=>'.LS','XDUB'=>'.IR'];
            if ($exchange && isset($map[$exchange])) {
                $candidates[] = $symbol.$map[$exchange];
            }
            // Fonds: suffixe .F (Frankfurt) souvent utilisé par Yahoo pour mutual funds européens
            $candidates[] = $symbol.'.F';
            // Essai brut
            $candidates[] = $symbol;
        }

        foreach ($candidates as $ySymbol) {
            try {
                $url = 'https://query1.finance.yahoo.com/v8/finance/chart/'.rawurlencode($ySymbol).'?region=FR&lang=fr-FR&range=1mo&interval=1d';
                $res = $this->http->request('GET', $url, ['timeout' => 6]);
                if ($res->getStatusCode() !== 200) {
                    continue;
                }
                $json = $res->toArray(false);
                $result = $json['chart']['result'][0] ?? null;
                if ($result && !empty($result['timestamp']) && !empty($result['indicators']['quote'][0]['close'])) {
                    $idx = array_key_last($result['timestamp']);
                    $ts = $result['timestamp'][$idx] ?? null;
                    $close = $result['indicators']['quote'][0]['close'][$idx] ?? null;
                    if ($ts && $close !== null) {
                        return ['nav' => (float)$close, 'navDate' => gmdate('Y-m-d', (int)$ts)];
                    }
                }
            } catch (\Throwable $e) {
                // 429/erreurs possibles: on tente la suivante
                $this->logger->info('Yahoo candidate failed', ['symbol' => $ySymbol, 'message' => $e->getMessage()]);
            }
        }
        return ['nav' => null, 'navDate' => null];
    }

    /**
     * Retourne ['nav' => float|null, 'navDate' => string|null]
     */
    public function getLast(string $symbol, ?string $exchange = null): array
    {
        $cacheKey = strtoupper(($exchange ?: 'any').':'.$symbol);
        if (isset($this->memoryCache[$cacheKey]) && (time() - $this->memoryCache[$cacheKey]['t'] < self::CACHE_TTL)) {
            return $this->memoryCache[$cacheKey]['v'];
        }
        // 1) Twelve Data direct (préférence pour alignement des valeurs avec la source attendue)
        $td = $this->tryTwelve($symbol, $exchange, null);
        if ($td['nav'] !== null) {
            $this->memoryCache[$cacheKey] = ['v' => $td, 't' => time()];
            return $td;
        }

        // 2) Si le symbole ressemble à un ISIN, tenter de le résoudre via TwelveData, puis retenter TwelveData sur le symbole
        $isIsin = (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($symbol));
        if ($isIsin) {
            try {
                $matches = $this->twelve->searchSymbols($symbol, null);
                if (!empty($matches)) {
                    // privilégier Mutual Fund s'il existe
                    usort($matches, function($a,$b){
                        $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                        $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                        return $aw <=> $bw;
                    });
                    $alt = $matches[0]['symbol'] ?? null;
                    $altType = $matches[0]['type'] ?? null;
                    if ($alt) {
                        $td2 = $this->tryTwelve($alt, $exchange, $altType ?: null);
                        if ($td2['nav'] !== null) {
                            $this->memoryCache[$cacheKey] = ['v' => $td2, 't' => time()];
                            return $td2;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->info('ISIN resolve via TwelveData failed', ['message' => $e->getMessage()]);
            }
        }

        // 3) Yahoo Finance en dernier recours
        $y = $this->tryYahoo($symbol, $exchange);
        if ($y['nav'] !== null) {
            $this->memoryCache[$cacheKey] = ['v' => $y, 't' => time()];
            return $y;
        }

        $null = ['nav' => null, 'navDate' => null];
        $this->memoryCache[$cacheKey] = ['v' => $null, 't' => time()];
        return $null;
    }
}



