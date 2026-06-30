<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\DTO\Product\ProductDTO;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for managing O2S Products (contract types).
 */
final class ProductService implements ProductServiceInterface
{
    private const ENDPOINT = '/products';

    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $o2sCache,
    ) {
    }

    private function getBaseUrl(): string
    {
        return $this->client->getConfiguration()->getApiUrl();
    }

    public function getProduct(string $productId): ProductDTO
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'product_' . $productId;

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($productId) {
            $item->expiresAfter(86400); // Cache for 24 hours (reference data)

            $this->logger->debug('Fetching O2S product', ['productId' => $productId]);
            $data = $this->client->get(self::ENDPOINT . '/' . $productId, [], $this->getBaseUrl());

            return ProductDTO::fromApiResponse($data);
        });
    }

    public function getAllProducts(): array
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'products_all';

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(86400); // Cache for 24 hours

            $this->logger->debug('Fetching all O2S products');
            
            $allProducts = [];
            $offset = 0;
            $limit = 100;

            do {
                $data = $this->client->get(self::ENDPOINT, [
                    'limit' => $limit,
                    'offset' => $offset,
                ], $this->getBaseUrl());

                if (!is_array($data)) {
                    break;
                }

                foreach ($data as $item) {
                    $allProducts[] = ProductDTO::fromApiResponse($item);
                }

                $offset += $limit;
            } while (count($data) === $limit);

            $this->logger->info('Retrieved all O2S products', ['count' => count($allProducts)]);

            return $allProducts;
        });
    }

    public function getProductsMap(): array
    {
        $products = $this->getAllProducts();
        $map = [];

        foreach ($products as $product) {
            $map[$product->getProductId()] = $product;
        }

        return $map;
    }

    public function getProductsByInstitution(string $institutionId): array
    {
        $products = $this->getAllProducts();

        return array_values(array_filter(
            $products,
            fn(ProductDTO $product) => $product->getInstitutionId() === $institutionId
        ));
    }
}

