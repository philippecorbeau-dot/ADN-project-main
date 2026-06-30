<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\DTO\Compte\CompteDTO;

/**
 * Interface for O2S Compte (Account) operations.
 */
interface CompteServiceInterface
{
    /**
     * Retrieves a single compte by ID.
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getCompte(string $compteId): CompteDTO;

    /**
     * Retrieves comptes with optional filtering.
     * 
     * @param array<string, mixed> $filters
     * @return CompteDTO[]
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getComptes(array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Retrieves all comptes for a specific contact.
     * 
     * @return CompteDTO[]
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getComptesForContact(string $contactId): array;

    /**
     * Retrieves only active comptes for a contact.
     * 
     * @return CompteDTO[]
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getActiveComptesForContact(string $contactId): array;

    /**
     * Retrieves comptes by product type.
     * 
     * @return CompteDTO[]
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getComptesByProduit(string $produitId): array;

    /**
     * Creates a new compte.
     * 
     * @param array<string, mixed> $data
     * @return string The new compte ID
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function createCompte(array $data): string;

    /**
     * Updates an existing compte.
     * 
     * @param array<string, mixed> $data
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function updateCompte(string $compteId, array $data): void;

    /**
     * Updates compte situations (valuation).
     * 
     * @param array<string, mixed> $situationData
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function updateCompteSituation(string $compteId, array $situationData): void;

    /**
     * Gets the total valuation for a contact across all comptes.
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getTotalValuationForContact(string $contactId): float;

    /**
     * Retrieves historical account details (totalValue + liquidity) over a date range.
     *
     * @return array<int, array{date: string, totalValue: float, liquidity: float}> Sorted by date ASC
     */
    public function getAccountDetailsHistory(string $accountId, string $dateFrom, string $dateTo, int $limit = 100): array;
}


