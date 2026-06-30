<?php

namespace App\Service\MarketData;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwelveDataClient
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private FilesystemAdapter $cache;
    private FilesystemAdapter $seriesCache;
    private FilesystemAdapter $quoteCache;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->logger = $logger;
        // Bump namespace pour invalider les anciennes entrées (évite de conserver des résultats vides)
        $this->cache = new FilesystemAdapter('twelvedata_search_v2', 900);
        // Caches courts pour limiter les appels externes (durée ~1 minute)
        $this->seriesCache = new FilesystemAdapter('twelvedata_series_v1', 90);
        $this->quoteCache = new FilesystemAdapter('twelvedata_quote_v1', 60);
    }

    private function getApiKey(): ?string
    {
        // 0) Fichier local prioritaire (dev/test): config/api_keys.local.json
        try {
            $path = dirname(__DIR__, 3) . '/config/api_keys.local.json';
            if (is_file($path)) {
                $json = json_decode((string) file_get_contents($path), true);
                if (is_array($json)) {
                    $val = $json['TWELVEDATA_API_KEY'] ?? null;
                    if (is_string($val) && trim($val) !== '') {
                        return trim($val);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore et continuer
        }
        // 1) Paramètre Symfony
        $key = (string) ($this->params->get('app.twelvedata_api_key') ?? '');
        if ($key !== '') {
            return $key;
        }
        // 2) Variable d'environnement
        $env = getenv('TWELVEDATA_API_KEY');
        return $env && $env !== '' ? (string) $env : null;
    }

    /**
     * Recherche de symboles côté TwelveData (Euronext supporté via exchange code)
     * Retourne un tableau normalisé: [ { symbol, name, isin|null, exchange, currency } ]
     */
    public function searchSymbols(string $query, ?string $exchange = null): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            $this->logger->warning('TwelveDataClient.searchSymbols: API key missing');
            return [];
        }

        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        $isIsin = (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($trimmed));
        $symbolSuffixMap = [
            'XPAR' => '.PA',
            'XAMS' => '.AS',
            'XBRU' => '.BR',
            'XLIS' => '.LS',
            'XDUB' => '.IR',
        ];
        $suffix = $exchange && isset($symbolSuffixMap[$exchange]) ? $symbolSuffixMap[$exchange] : null;
        $queryVariants = [];
        $upper = strtoupper($trimmed);
        $queryVariants[] = $upper;
        // Si l'utilisateur tape MC.PA → tester "MC" aussi
        if ($suffix && str_ends_with($upper, $suffix)) {
            $queryVariants[] = substr($upper, 0, -strlen($suffix));
        }
        // Raccourcis courants CAC40
        $synonyms = [
            'LVMH' => 'MC', 'KERING' => 'KER', 'LOREAL' => 'OR', 'L’OREAL' => 'OR', 'L\'OREAL' => 'OR',
            'AIRBUS' => 'AIR', 'DANONE' => 'BN', 'BNP' => 'BNP', 'SANOFI' => 'SAN', 'TOTAL' => 'TTE', 'VINCI' => 'DG',
        ];
        if (isset($synonyms[$upper])) {
            $queryVariants[] = $synonyms[$upper];
        }
        $cacheKey = sprintf('symbol_search_%s_%s', md5($trimmed), $exchange ?: 'any');

        try {
            $raw = $this->cache->get($cacheKey, function (ItemInterface $item) use ($apiKey, $exchange, $queryVariants) {
                $item->expiresAfter(900); // 15 minutes

                $url = 'https://api.twelvedata.com/symbol_search';
                $aggregated = [];
                foreach ($queryVariants as $qv) {
                    $params = [
                        'apikey' => $apiKey,
                        'symbol' => $qv,
                        'outputsize' => 50,
                    ];
                    if ($exchange) {
                        $params['exchange'] = $exchange;
                    }
                    $response = $this->httpClient->request('GET', $url, [
                        'query' => $params,
                        'timeout' => 5,
                    ]);
                    if ($response->getStatusCode() === 200) {
                        $arr = $response->toArray(false);
                        if (!empty($arr['data'])) {
                            $aggregated = array_merge($aggregated, $arr['data']);
                        }
                    }
                }
                return ['data' => $aggregated];
            });

            $data = $raw['data'] ?? [];
            if (!is_array($data)) {
                $data = [];
            }

            // Normalisation
            $normalized = [];
            $matchedByIsin = [];
            foreach ($data as $row) {
                if (!isset($row['symbol']) || (!isset($row['name']) && !isset($row['instrument_name']))) {
                    continue;
                }
                $item = [
                    'symbol' => (string) $row['symbol'],
                    'name' => (string) ($row['instrument_name'] ?? $row['name']),
                    'exchange' => (string) ($row['exchange'] ?? ''),
                    'currency' => (string) ($row['currency'] ?? ''),
                    'isin' => $row['isin'] ?? null,
                    'type' => (string) ($row['instrument_type'] ?? ''),
                ];
                // Filtrage ISIN si la requête est un ISIN
                if ($isIsin) {
                    if (isset($row['isin']) && strtoupper($row['isin']) === strtoupper($trimmed)) {
                        $matchedByIsin[] = $item;
                    } else {
                        $normalized[] = $item; // conservé pour fallback s'il n'y a aucun match exact
                    }
                } else {
                    $normalized[] = $item;
                }
            }

            if ($isIsin && !empty($matchedByIsin)) {
                $normalized = $matchedByIsin;
            }

            // Si on a un exchange demandé, filtrer par exchange ou suffixe correspondant
            if ($exchange && !empty($normalized)) {
                $suffix = $symbolSuffixMap[$exchange] ?? null;
                $normalized = array_values(array_filter($normalized, function ($it) use ($exchange, $suffix) {
                    if (!empty($it['exchange']) && strtoupper($it['exchange']) === $exchange) return true;
                    if ($suffix && isset($it['symbol']) && str_ends_with(strtoupper($it['symbol']), strtoupper($suffix))) return true;
                    return false;
                }));
            }

            return $normalized;
        } catch (\Throwable $e) {
            $this->logger->error('TwelveDataClient.searchSymbols error', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Renvoie une petite liste de valeurs populaires par bourse (curation locale),
     * puis tente de les résoudre via symbol_search pour obtenir les métadonnées.
     */
    public function getCuratedForExchange(string $exchange): array
    {
        // Valeurs par défaut (top liquidités par place)
        $curated = [
            'XPAR' => ['MC.PA','OR.PA','AIR.PA','BNP.PA','SAN.PA','DG.PA','KER.PA','AI.PA','GLE.PA','SU.PA'],
            'XAMS' => ['ASML.AS','ADYEN.AS','RAND.AS','KPN.AS','PHIA.AS','HEIA.AS','INGA.AS','SHELL.AS','ASRNL.AS'],
            'XBRU' => ['ABI.BR','KBC.BR','ACKB.BR','UCB.BR','SOLB.BR','PROX.BR'],
            'XLIS' => ['EDP.LS','GALP.LS','BCP.LS','JMT.LS','SON.LS','NOS.LS'],
            'XDUB' => ['CRH.IR','RYA.IR','AIBG.IR','BKIR.IR','GL9.IR'],
        ];

        // Surcharge via fichier config/curated_symbols.json si présent
        try {
            $configPath = dirname(__DIR__, 3) . '/config/curated_symbols.json';
            if (is_file($configPath)) {
                $json = json_decode((string) file_get_contents($configPath), true);
                if (is_array($json) && isset($json[$exchange]) && is_array($json[$exchange])) {
                    $curated[$exchange] = $json[$exchange];
                }
            }
        } catch (\Throwable $e) {
            // no-op
        }
        $symbols = $curated[$exchange] ?? [];
        $results = [];
        foreach ($symbols as $sym) {
            // 1) Essayer avec exchange demandé
            $items = $this->searchSymbols($sym, $exchange);
            // 2) Si rien trouvé, élargir sans filtre d'exchange (permet FSX pour ISIN de fonds)
            if (empty($items)) {
                $items = $this->searchSymbols($sym, null);
            }
            if (!empty($items)) {
                // Garder le premier match par symbole (souvent le plus pertinent)
                $first = $items[0];
                // Si la requête de départ est un ISIN et que TwelveData ne fournit pas l'ISIN, le recopier
                $isIsin = (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($sym));
                if ($isIsin && empty($first['isin'])) {
                    $first['isin'] = $sym;
                }
                $results[] = $first;
                continue;
            }
            // 3) Fallback minimal si toujours non trouvé
            $isIsin = (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($sym));
            $results[] = [
                'symbol' => $sym,
                'name' => $sym,
                'exchange' => $exchange,
                'currency' => 'EUR',
                'isin' => $isIsin ? $sym : null,
                // ne pas deviner le type ici
            ];
        }
        return $results;
    }

    /**
     * Variante légère: renvoie uniquement la liste des symboles "curatés" (sans résolution),
     * avec surcharge éventuelle via config/curated_symbols.json.
     *
     * @return string[]
     */
    public function getCuratedSymbols(string $exchange): array
    {
        $curated = [
            'XPAR' => ['FR0000987950','FR0007371703','FR0010077461','FR0010097642','LU1876459212','FR001400YMD8','MC.PA','OR.PA','AIR.PA','BNP.PA','SAN.PA','DG.PA','KER.PA','AI.PA','GLE.PA','SU.PA'],
            'XAMS' => ['ASML.AS','ADYEN.AS','PHIA.AS','HEIA.AS','KPN.AS','INGA.AS','SHELL.AS','ASRNL.AS','DSM.AS','AKZA.AS'],
            'XBRU' => ['ABI.BR','KBC.BR','ACKB.BR','UCB.BR','SOLB.BR','PROX.BR','ELI.BR'],
            'XLIS' => ['EDP.LS','GALP.LS','BCP.LS','JMT.LS','SON.LS','NOS.LS'],
            'XDUB' => ['CRH.IR','RYA.IR','AIBG.IR','BKIR.IR','GL9.IR'],
        ];
        try {
            $configPath = dirname(__DIR__, 3) . '/config/curated_symbols.json';
            if (is_file($configPath)) {
                $json = json_decode((string) file_get_contents($configPath), true);
                if (is_array($json) && isset($json[$exchange]) && is_array($json[$exchange])) {
                    $curated[$exchange] = $json[$exchange];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return array_values($curated[$exchange] ?? []);
    }

    /**
     * Récupération d'un dernier cours (quote) d'un symbole
     */
    public function getQuote(string $symbol): ?array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return null;
        }
        try {
            $cacheKey = sprintf('q_%s', md5($symbol));
            return $this->quoteCache->get($cacheKey, function () use ($symbol, $apiKey) {
                $response = $this->httpClient->request('GET', 'https://api.twelvedata.com/quote', [
                    'query' => [
                        'symbol' => $symbol,
                        'apikey' => $apiKey,
                    ],
                    'timeout' => 5,
                ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $data = $response->toArray(false);
            if (!isset($data['symbol'])) {
                return null;
            }
            return $data;
            });
        } catch (\Throwable $e) {
            $this->logger->error('TwelveDataClient.getQuote error', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Time series EOD compact
     */
    public function getTimeSeries(string $symbol, string $interval = '1day', string $outputsize = '1'): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return [];
        }
        try {
            $cacheKey = sprintf('ts_%s_%s_%s', md5($symbol), $interval, $outputsize);
            return $this->seriesCache->get($cacheKey, function () use ($symbol, $interval, $outputsize, $apiKey) {
                $response = $this->httpClient->request('GET', 'https://api.twelvedata.com/time_series', [
                    'query' => [
                        'symbol' => $symbol,
                        'interval' => $interval,
                        'outputsize' => $outputsize,
                        'apikey' => $apiKey,
                    ],
                    'timeout' => 5,
                ]);
            if ($response->getStatusCode() !== 200) {
                return [];
            }
            $data = $response->toArray(false);
            $values = $data['values'] ?? [];
                return is_array($values) ? $values : [];
            });
        } catch (\Throwable $e) {
            $this->logger->error('TwelveDataClient.getTimeSeries error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Enrichit les lignes avec dernière VL et date (pour actions/ETF on utilise close du dernier point)
     * et champs potentiels (société de gestion/SRI si disponibles dans d'autres sources).
     */
    public function enrichWithNav(array $items): array
    {
        foreach ($items as &$it) {
            $series = $this->getTimeSeries($it['symbol']);
            if (!empty($series)) {
                $first = $series[0];
                $it['nav'] = $first['close'] ?? null;
                $it['navDate'] = $first['datetime'] ?? null;
            }
            // Placeholders (TwelveData ne fournit pas SRI/management company)
            $it['managementCompany'] = $it['managementCompany'] ?? '';
            $it['sri'] = $it['sri'] ?? '';
        }
        return $items;
    }
}


