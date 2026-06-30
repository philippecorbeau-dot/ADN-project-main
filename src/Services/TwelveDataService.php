<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class TwelveDataService
{
    private $httpClient;
    private $params;
    private $logger;
    private $cache;

    public function __construct(
        HttpClientInterface $httpClient, 
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->logger = $logger;
        $this->cache = new FilesystemAdapter('twelvedata', 300); // 5 minutes cache
    }

    public function getApiKey(): ?string
    {
        return $this->params->get('app.twelvedata_api_key');
    }

    public function getStockQuote(string $symbol): ?array
    {
        $apiKey = $this->getApiKey();
        
        if (!$apiKey) {
            $this->logger->error('Clé API Twelve Data non configurée');
            return null;
        }

        // Utiliser le cache pour éviter les requêtes répétées
        $cacheKey = 'stock_' . strtolower($symbol);
        
        return $this->cache->get($cacheKey, function () use ($symbol, $apiKey) {
            try {
                $response = $this->httpClient->request('GET', 'https://api.twelvedata.com/quote', [
                    'query' => [
                        'symbol' => $symbol,
                        'apikey' => $apiKey
                    ],
                    'timeout' => 5 // Réduire le timeout
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    
                    if (isset($data['symbol']) && isset($data['close'])) {
                        return [
                            'symbol' => $data['symbol'],
                            'name' => $data['name'] ?? $data['symbol'],
                            'price' => (float) $data['close'],
                            'change' => (float) $data['change'],
                            'changePercent' => (float) $data['percent_change'],
                            'volume' => (int) $data['volume'],
                            'high' => (float) $data['high'],
                            'low' => (float) $data['low'],
                            'open' => (float) $data['open'],
                            'previousClose' => (float) $data['previous_close'],
                            'isPositive' => (float) $data['change'] >= 0,
                            'timestamp' => new \DateTime(),
                            'cached' => true
                        ];
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la récupération des données pour ' . $symbol . ': ' . $e->getMessage());
            }

            return null;
        });
    }

    public function getMultipleStocks(array $symbols): array
    {
        $stocks = [];
        
        // Récupération séquentielle pour éviter les problèmes de concurrence
        foreach ($symbols as $symbol) {
            $stockData = $this->getStockQuote($symbol);
            if ($stockData) {
                $stocks[] = $stockData;
            }
        }

        return $stocks;
    }

    public function getDefaultStocks(): array
    {
        $defaultSymbols = ['AAPL', 'TSLA', 'MSFT', 'GOOGL', 'AMZN'];
        return $this->getMultipleStocks($defaultSymbols);
    }

    // Nouvelles méthodes pour différents marchés
    public function getCAC40Stocks(): array
    {
        // Utiliser des ADR françaises qui fonctionnent
        $cac40Symbols = [
            'LVMUY', // LVMH ADR
            'ENGIY', // Engie ADR
            'VIVHY'  // Vivendi ADR
        ];
        
        return $this->getMultipleStocks($cac40Symbols);
    }

    public function getChineseStocks(): array
    {
        // Actions chinoises qui fonctionnent
        $chineseSymbols = [
            'BABA', // Alibaba
            'JD',   // JD.com
            'NIO'   // NIO Inc
        ];
        
        return $this->getMultipleStocks($chineseSymbols);
    }

    public function getGermanStocks(): array
    {
        // Actions allemandes qui fonctionnent
        $germanSymbols = [
            'SAP',   // SAP SE
            'VWAGY', // Volkswagen ADR
            'BAYRY'  // Bayer ADR
        ];
        
        return $this->getMultipleStocks($germanSymbols);
    }

    public function getIndices(): array
    {
        // Utiliser des ETF populaires au lieu d'indices directs (limitations API)
        $indices = [
            'SPY' => 'S&P 500 ETF',
            'QQQ' => 'NASDAQ-100 ETF',
            'IWM' => 'Russell 2000 ETF'
        ];

        $result = [];
        foreach ($indices as $symbol => $name) {
            $data = $this->getStockQuote($symbol);
            if ($data) {
                $data['name'] = $name;
                $result[] = $data;
            }
        }

        return $result;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }
} 