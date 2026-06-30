<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\DTO\Asset\AssetDTO;

/**
 * Interface for O2S Asset Service.
 */
interface AssetServiceInterface
{
    /**
     * Retrieves a single asset by ID.
     */
    public function getAsset(string $assetId): AssetDTO;

    /**
     * Retrieves multiple assets by IDs (batch).
     * 
     * @param string[] $assetIds
     * @return array<string, AssetDTO> Indexed by assetId
     */
    public function getAssets(array $assetIds): array;

    /**
     * Clears the asset cache.
     */
    public function clearCache(): void;
}

