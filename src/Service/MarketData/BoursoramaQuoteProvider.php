<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fournisseur de VL par scraping public de Boursorama.
 *
 * Stratégie :
 *  1. Si l'ISIN est listé dans le mapping {@see EurListingMapping},
 *     on utilise le symbole Boursorama interne (`0PXXXXXX` ou code Euronext) pour
 *     pointer la cotation EUR préférée (cas des ETF/Fonds multi-cotés).
 *  2. Sinon on utilise `/cours/{ISIN}/` qui suit la redirection par défaut.
 *  3. Garde-fou : si Boursorama redirige hors de `/cours/`, l'ISIN est inconnu → null
 *     (évite le faux positif où on attraperait le prix d'un widget de futures).
 *
 * Cache 6 h (les VL sont publiées une fois par jour, J+1 ouvré).
 *
 * Voir aussi le POC `tests/Poc/test-vl-sources.php` qui a validé ces patterns.
 */
final class BoursoramaQuoteProvider implements MarketQuoteProviderInterface
{
    public const SOURCE = 'boursorama';

    private const BASE_URL = 'https://www.boursorama.com/cours/';
    private const CACHE_TTL = 21600; // 6 h
    private const CACHE_PREFIX = 'bourso_quote_';

    private readonly HttpClientInterface $http;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ?EurListingMapping $eurListingMapping = null,
        ?HttpClientInterface $http = null,
        ?LoggerInterface $logger = null,
        private readonly ?CacheInterface $cache = null,
    ) {
        $this->http = $http ?? HttpClient::create([
            'timeout' => 12,
            'max_duration' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            ],
        ]);
        $this->logger = $logger ?? new NullLogger();
    }

    public function getSourceName(): string
    {
        return self::SOURCE;
    }

    public function getQuote(string $isin): ?Quote
    {
        $isin = strtoupper(trim($isin));
        if (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}\d$/', $isin)) {
            return null;
        }

        if ($this->cache !== null) {
            $cacheKey = self::CACHE_PREFIX . $isin;
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($isin) {
                $item->expiresAfter(self::CACHE_TTL);
                $quote = $this->fetchQuote($isin);
                if ($quote === null) {
                    $item->expiresAfter(900); // 15 min sur échec : on retentera vite
                }
                return $quote;
            });
        }

        return $this->fetchQuote($isin);
    }

    private function fetchQuote(string $isin): ?Quote
    {
        // 1) URL préférée via mapping multi-coté si applicable
        $url = $this->resolveUrl($isin);

        try {
            $response = $this->http->request('GET', $url);
            $status = $response->getStatusCode();
            $info = $response->getInfo();
            $finalUrl = (string) ($info['url'] ?? '');

            if ($status !== 200) {
                $this->logger->debug('Boursorama HTTP non 200', ['isin' => $isin, 'status' => $status, 'url' => $url]);
                return null;
            }

            // Garde-fou faux positif : si l'ISIN n'est pas reconnu, Boursorama redirige
            // vers /recherche/ ou home. La page contient alors des widgets de futures.
            if (!str_contains($finalUrl, '/cours/')) {
                $this->logger->debug('Boursorama ISIN inconnu (redirection)', ['isin' => $isin, 'finalUrl' => $finalUrl]);
                return null;
            }

            $html = $response->getContent(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Boursorama fetch failed', ['isin' => $isin, 'error' => $e->getMessage()]);
            return null;
        }

        return self::parseQuote($isin, $html, $finalUrl);
    }

    /**
     * Visible pour tests unitaires.
     */
    public static function parseQuote(string $isin, string $html, ?string $sourceUrl = null): ?Quote
    {
        $nav = null;
        $currency = null;
        $date = null;

        // Pattern strict : prix + devise dans le bloc faceplate
        if (preg_match(
            '#class="c-faceplate__price[^"]*".*?data-ist-last[^>]*>([0-9\s\xc2\xa0,\.]+)</span>.*?c-faceplate__price-currency[^>]*>\s*([A-Z]{3})#s',
            $html,
            $m
        )) {
            $nav = self::parseFrenchNumber($m[1]);
            $currency = $m[2];
        }

        // Pattern souple : faceplate sans devise collée
        if ($nav === null && preg_match('#class="c-faceplate__price[^"]*".*?data-ist-last[^>]*>([0-9\s\xc2\xa0,\.]+)</span>#s', $html, $m)) {
            $nav = self::parseFrenchNumber($m[1]);
            if (preg_match('#c-faceplate__price-currency[^>]*>\s*([A-Z]{3})#', $html, $m2)) {
                $currency = $m2[1];
            }
        }

        if ($nav === null) {
            return null;
        }

        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $html, $dm)) {
            $date = \DateTimeImmutable::createFromFormat('!d/m/Y', "{$dm[1]}/{$dm[2]}/{$dm[3]}") ?: null;
        }

        return new Quote(
            isin: $isin,
            nav: $nav,
            currency: $currency ?? 'EUR',
            navDate: $date,
            source: self::SOURCE,
            sourceUrl: $sourceUrl,
        );
    }

    /**
     * Résout l'URL Boursorama à interroger pour cet ISIN.
     * Tient compte d'un éventuel override de mapping multi-coté.
     */
    private function resolveUrl(string $isin): string
    {
        if ($this->eurListingMapping !== null) {
            $override = $this->eurListingMapping->getBoursoramaSymbol($isin);
            if ($override !== null) {
                return self::BASE_URL . urlencode($override) . '/';
            }
        }
        return self::BASE_URL . urlencode($isin) . '/';
    }

    public static function parseFrenchNumber(string $s): ?float
    {
        $s = trim($s);
        $s = str_replace(["\xc2\xa0", ' ', "\u{202f}"], '', $s);
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',') && !str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace(',', '', $s);
        }
        return is_numeric($s) ? (float) $s : null;
    }
}
