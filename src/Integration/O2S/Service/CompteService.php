<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\DTO\Compte\AccountDetailsDTO;
use App\Integration\O2S\DTO\Compte\CompteDTO;
use App\Integration\O2S\Exception\O2SApiException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for managing O2S Comptes (Accounts/Contracts).
 */
final class CompteService implements CompteServiceInterface
{
    private const ENDPOINT = '/comptes';

    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $o2sCache,
    ) {
    }

    private function getBaseUrl(): string
    {
        return $this->client->getConfiguration()->getComptesApiUrl();
    }

    public function getCompte(string $compteId): CompteDTO
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'compte_' . md5($compteId);

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($compteId) {
            $item->expiresAfter(86400); // Cache 24h (rafraîchi par le cron o2s_warm_cache.php à 6h)

            $this->logger->debug('Fetching O2S compte (cache miss)', ['id' => $compteId]);
        $data = $this->client->get(self::ENDPOINT . '/' . $compteId, [], $this->getBaseUrl());

        return CompteDTO::fromApiResponse($data);
        });
    }

    public function getComptes(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $this->logger->debug('Fetching O2S comptes', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $query = array_merge($filters, [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $data = $this->client->get(self::ENDPOINT, $query, $this->getBaseUrl());

        // Handle both single item and array responses
        if (!isset($data[0]) && !empty($data)) {
            return [CompteDTO::fromApiResponse($data)];
        }

        return array_map(
            fn(array $item) => CompteDTO::fromApiResponse($item),
            $data
        );
    }

    public function getComptesForContact(string $contactId): array
    {
        $this->logger->debug('Fetching O2S comptes for contact', ['contactId' => $contactId]);

        return $this->getAllComptes(['contactId' => $contactId]);
    }

    public function getActiveComptesForContact(string $contactId): array
    {
        $comptes = $this->getComptesForContact($contactId);

        return array_values(array_filter(
            $comptes,
            fn(CompteDTO $compte) => $compte->isActif()
        ));
    }

    public function getComptesByProduit(string $produitId): array
    {
        return $this->getAllComptes(['produitId' => $produitId]);
    }

    public function createCompte(array $data): string
    {
        $this->logger->info('Creating O2S compte', [
            'produitId' => $data['produitLie']['produitId'] ?? 'N/A',
        ]);

        $response = $this->client->post(self::ENDPOINT, $data, $this->getBaseUrl());

        if (!isset($response['id'])) {
            throw O2SApiException::invalidResponse(self::ENDPOINT, 'Missing id in response');
        }

        return $response['id'];
    }

    public function updateCompte(string $compteId, array $data): void
    {
        $this->logger->info('Updating O2S compte', ['id' => $compteId]);

        $this->client->put(self::ENDPOINT . '/' . $compteId, $data, $this->getBaseUrl());
    }

    public function updateCompteSituation(string $compteId, array $situationData): void
    {
        $this->logger->info('Updating O2S compte situation', ['id' => $compteId]);

        $this->client->put(self::ENDPOINT . '/' . $compteId . '/situations', $situationData, $this->getBaseUrl());
    }

    public function getTotalValuationForContact(string $contactId): float
    {
        $comptes = $this->getActiveComptesForContact($contactId);

        $total = 0.0;
        foreach ($comptes as $compte) {
            $total += $compte->getMontant() ?? 0.0;
        }

        return $total;
    }

    /**
     * Retrieves all comptes matching filters (paginated internally).
     * 
     * @param array<string, mixed> $filters
     * @return CompteDTO[]
     */
    public function getAllComptes(array $filters = []): array
    {
        $allComptes = [];
        $offset = 0;
        $limit = 100;

        do {
            $comptes = $this->getComptes($filters, $limit, $offset);
            $allComptes = array_merge($allComptes, $comptes);
            $offset += $limit;
        } while (count($comptes) === $limit);

        $this->logger->info('Retrieved all O2S comptes', [
            'filters' => $filters,
            'count' => count($allComptes),
        ]);

        return $allComptes;
    }

    /**
     * Groups comptes by type.
     * 
     * @param CompteDTO[] $comptes
     * @return array<string, CompteDTO[]>
     */
    public function groupByType(array $comptes): array
    {
        $grouped = [];

        foreach ($comptes as $compte) {
            $type = $compte->getProductType();
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $compte;
        }

        return $grouped;
    }

    /**
     * Calculates summary statistics for a set of comptes.
     * 
     * @param CompteDTO[] $comptes
     * @return array{
     *     total_valuation: float,
     *     compte_count: int,
     *     by_type: array<string, array{count: int, total: float}>
     * }
     */
    public function calculateSummary(array $comptes): array
    {
        $summary = [
            'total_valuation' => 0.0,
            'compte_count' => count($comptes),
            'by_type' => [],
        ];

        foreach ($comptes as $compte) {
            $montant = $compte->getMontant() ?? 0.0;
            $type = $compte->getProductType();

            $summary['total_valuation'] += $montant;

            if (!isset($summary['by_type'][$type])) {
                $summary['by_type'][$type] = ['count' => 0, 'total' => 0.0];
            }

            $summary['by_type'][$type]['count']++;
            $summary['by_type'][$type]['total'] += $montant;
        }

        return $summary;
    }

    /**
     * Retrieves account details with valuation (totalValue, liquidity, situation).
     * Uses the /accounts/{accountId}/account-details endpoint.
     */
    public function getAccountDetails(string $accountId): AccountDetailsDTO
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'acct_details_' . md5($accountId);

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($accountId) {
            $item->expiresAfter(86400); // Cache 24h (rafraîchi par le cron o2s_warm_cache.php à 6h)

            $this->logger->debug('Fetching O2S account details (cache miss)', ['accountId' => $accountId]);

        try {
            $endpoint = '/accounts/' . $accountId . '/account-details';
            $data = $this->client->get($endpoint, [], $this->getBaseUrl());

            return AccountDetailsDTO::fromApiResponse($accountId, $data);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch account details', [
                'accountId' => $accountId,
                'error' => $e->getMessage(),
            ]);

                // Retourne un DTO vide si l'endpoint échoue — on le cache quand même 5 min
                // pour éviter de re-tenter immédiatement les comptes en erreur
                $item->expiresAfter(300);
            return new AccountDetailsDTO($accountId, null, null);
        }
        });
    }

    /**
     * Retrieves account details for multiple accounts.
     * 
     * @param string[] $accountIds
     * @return array<string, AccountDetailsDTO> Map of accountId => AccountDetailsDTO
     */
    public function getAccountDetailsForMultiple(array $accountIds): array
    {
        $results = [];

        foreach ($accountIds as $accountId) {
            $results[$accountId] = $this->getAccountDetails($accountId);
        }

        return $results;
    }

    /**
     * Get total valuation for a contact using account-details endpoint.
     * This is more accurate than using the list endpoint.
     */
    /**
     * {@inheritdoc}
     */
    public function getAccountDetailsHistory(string $accountId, string $dateFrom, string $dateTo, int $limit = 100): array
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'acct_hist_' . md5($accountId . $dateFrom . $dateTo);

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($accountId, $dateFrom, $dateTo, $limit) {
            $item->expiresAfter(86400); // Cache 24h (rafraîchi par le cron o2s_warm_cache.php à 6h)

            $this->logger->debug('Fetching O2S account details history (cache miss)', [
                'accountId' => $accountId,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ]);

            try {
                $endpoint = '/accounts/' . $accountId . '/account-details';
                $data = $this->client->get($endpoint, [
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'limit' => $limit,
                ], $this->getBaseUrl());

                if (empty($data)) {
                    return [];
                }

                // Normalize: single item or array
                $items = isset($data[0]) ? $data : [$data];

                $history = [];
                foreach ($items as $item) {
                    if (!isset($item['referenceDate'])) {
                        continue;
                    }
                    $history[] = [
                        'date' => $item['referenceDate'],
                        'totalValue' => isset($item['totalValue']) ? (float) $item['totalValue'] : 0.0,
                        'liquidity' => isset($item['liquidity']) ? (float) $item['liquidity'] : 0.0,
                    ];
                }

                // Sort by date ascending
                usort($history, fn($a, $b) => strcmp($a['date'], $b['date']));

                return $history;
            } catch (\Throwable $e) {
                $this->logger->debug('Failed to fetch account details history', [
                    'accountId' => $accountId,
                    'error' => $e->getMessage(),
                ]);
                // Cache l'erreur 5 min pour éviter de re-tenter immédiatement
                $item->expiresAfter(300);
                return [];
            }
        });
    }

    public function getAccurateTotalValuationForContact(string $contactId): array
    {
        $comptes = $this->getComptesForContact($contactId);
        
        $totalValue = 0.0;
        $totalLiquidity = 0.0;
        $details = [];

        foreach ($comptes as $compte) {
            $accountDetails = $this->getAccountDetails($compte->getId());
            
            if ($accountDetails->hasValuation()) {
                $totalValue += $accountDetails->getTotalValue() ?? 0.0;
                $totalLiquidity += $accountDetails->getLiquidity() ?? 0.0;
            }

            $details[] = [
                'compte' => $compte,
                'details' => $accountDetails,
            ];
        }

        return [
            'totalValue' => $totalValue,
            'totalLiquidity' => $totalLiquidity,
            'compteCount' => count($comptes),
            'details' => $details,
        ];
    }
}


