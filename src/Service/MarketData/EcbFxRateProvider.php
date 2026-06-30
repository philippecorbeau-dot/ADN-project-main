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
 * Récupère les taux de change officiels publiés par la Banque Centrale Européenne.
 *
 * Source : https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
 *  - Gratuit, sans authentification, mis à jour vers 16h CET les jours ouvrés
 *  - Base = EUR (donc 1 EUR = X devise)
 *  - Couvre 31 devises majeures (USD, GBP, CHF, JPY, CAD, AUD, etc.)
 *
 * Format de retour : map ['USD' => 1.0824, 'GBP' => 0.8451, ...]
 *
 * Cache 24h en filesystem (taux quotidiens, pas la peine de refetch).
 */
final class EcbFxRateProvider implements FxRateProviderInterface
{
    private const ECB_DAILY_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    private const CACHE_TTL = 86400; // 24h
    private const CACHE_KEY = 'ecb_fx_rates_daily';

    private readonly HttpClientInterface $http;
    private readonly LoggerInterface $logger;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?LoggerInterface $logger = null,
        private readonly ?CacheInterface $cache = null,
    ) {
        $this->http = $http ?? HttpClient::create([
            'timeout' => 10,
            'max_duration' => 12,
        ]);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Retourne tous les taux EUR → X disponibles ce jour, ou null si la BCE est inaccessible.
     *
     * @return array{date: \DateTimeImmutable, rates: array<string, float>}|null
     */
    public function getRates(): ?array
    {
        if ($this->cache === null) {
            return $this->fetchRates();
        }

        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            $rates = $this->fetchRates();
            if ($rates === null) {
                // Évite de cacher un échec trop longtemps : retry plus rapidement
                $item->expiresAfter(300);
            }
            return $rates;
        });
    }

    /**
     * @return array{date: \DateTimeImmutable, rates: array<string, float>}|null
     */
    private function fetchRates(): ?array
    {
        try {
            $response = $this->http->request('GET', self::ECB_DAILY_URL);
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('ECB rates HTTP non 200', ['status' => $response->getStatusCode()]);
                return null;
            }
            $xml = $response->getContent(false);
        } catch (\Throwable $e) {
            $this->logger->warning('ECB rates fetch failed', ['error' => $e->getMessage()]);
            return null;
        }

        return self::parseEcbXml($xml);
    }

    /**
     * @return array{date: \DateTimeImmutable, rates: array<string, float>}|null
     */
    public static function parseEcbXml(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new \SimpleXMLElement($xml);
        } catch (\Throwable) {
            libxml_use_internal_errors($previous);
            return null;
        }
        libxml_use_internal_errors($previous);

        $doc->registerXPathNamespace('gesmes', 'http://www.gesmes.org/xml/2002-08-01');
        $doc->registerXPathNamespace('ecb', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

        $cubeOfDate = $doc->xpath('//ecb:Cube/ecb:Cube[@time]');
        if (!$cubeOfDate || empty($cubeOfDate)) {
            return null;
        }

        $cube = $cubeOfDate[0];
        $dateStr = (string) $cube['time'];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
        if (!$date) {
            return null;
        }

        $rates = ['EUR' => 1.0]; // base

        // On ré-enregistre le namespace sur l'élément retourné par xpath
        // (un xpath relatif sur la racine ne le voit pas autrement).
        $cube->registerXPathNamespace('ecb', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');
        $entries = $cube->xpath('./ecb:Cube[@currency]') ?: [];

        foreach ($entries as $entry) {
            $currency = (string) $entry['currency'];
            $rate = (float) $entry['rate'];
            if ($currency !== '' && $rate > 0) {
                $rates[$currency] = $rate;
            }
        }

        return ['date' => $date, 'rates' => $rates];
    }

    /**
     * Retourne le taux 1 EUR = X {currency}. Null si devise non couverte ou BCE inaccessible.
     */
    public function getRate(string $currency): ?float
    {
        $bag = $this->getRates();
        if ($bag === null) {
            return null;
        }
        return $bag['rates'][strtoupper($currency)] ?? null;
    }
}
