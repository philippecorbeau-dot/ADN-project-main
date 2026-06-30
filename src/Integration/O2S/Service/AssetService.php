<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\DTO\Asset\AssetDTO;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing O2S Assets (Fonds, Titres, UC).
 * 
 * Assets are cached to avoid repeated API calls as they rarely change.
 */
final class AssetService implements AssetServiceInterface
{
    private const ENDPOINT = '/assets';
    private const CACHE_TTL = 86400; // 24 hours - assets rarely change

    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function getAsset(string $assetId): AssetDTO
    {
        // Try cache first
        $cacheKey = $this->getCacheKey($assetId);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            $this->logger->debug('Asset cache hit', ['assetId' => $assetId]);
            return AssetDTO::fromApiResponse($cacheItem->get());
        }

        $this->logger->debug('Fetching O2S asset', ['assetId' => $assetId]);

        try {
            $data = $this->client->get(self::ENDPOINT . '/' . $assetId);
            
            // Handle response wrapped in data array
            if (isset($data['data']) && is_array($data['data'])) {
                $data = $data['data'][0] ?? $data['data'];
            }
            
            // Cache the response
            $cacheItem->set($data);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
            
            $this->logger->info('O2S asset fetched and cached', [
                'assetId' => $assetId,
                'label' => $data['label'] ?? $data['libelle'] ?? 'N/A',
            ]);

            return AssetDTO::fromApiResponse($data);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch O2S asset', [
                'assetId' => $assetId,
                'error' => $e->getMessage(),
            ]);
            
            // Return a minimal DTO with just the ID
            return new AssetDTO(
                assetId: $assetId,
                label: null,
                isin: null,
                currency: null,
                assetType: null,
                assetClass: null,
                managementCompany: null,
            );
        }
    }

    public function getAssets(array $assetIds): array
    {
        $result = [];
        $missingIds = [];

        // Check cache for each asset
        foreach ($assetIds as $assetId) {
            $cacheKey = $this->getCacheKey($assetId);
            $cacheItem = $this->cache->getItem($cacheKey);
            
            if ($cacheItem->isHit()) {
                $result[$assetId] = AssetDTO::fromApiResponse($cacheItem->get());
            } else {
                $missingIds[] = $assetId;
            }
        }

        $this->logger->debug('Asset batch lookup', [
            'requested' => count($assetIds),
            'cached' => count($result),
            'missing' => count($missingIds),
        ]);

        // Fetch missing assets individually
        // Note: If O2S supports batch lookup, this could be optimized
        foreach ($missingIds as $assetId) {
            $result[$assetId] = $this->getAsset($assetId);
        }

        return $result;
    }

    public function clearCache(): void
    {
        // Clear all cached assets by deleting items with our prefix
        // Note: This is a simple implementation; for production, consider using cache tags
        $this->logger->info('Clearing O2S asset cache');
        $this->cache->clear();
    }

    private function getCacheKey(string $assetId): string
    {
        return 'o2s_asset_' . $assetId;
    }
}

