<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\DTO\Product\ProductDTO;

interface ProductServiceInterface
{
    public function getProduct(string $productId): ProductDTO;

    /**
     * @return ProductDTO[]
     */
    public function getAllProducts(): array;

    /**
     * @return array<string, ProductDTO> Map of productId => ProductDTO
     */
    public function getProductsMap(): array;

    /**
     * @return ProductDTO[]
     */
    public function getProductsByInstitution(string $institutionId): array;
}

