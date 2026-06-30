<?php

declare(strict_types=1);

namespace App\Integration\O2S;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\DTO\Compte\CompteDTO;
use App\Integration\O2S\DTO\Contact\ContactDTO;
use App\Integration\O2S\Service\CompteServiceInterface;
use App\Integration\O2S\Service\ContactServiceInterface;
use App\Integration\O2S\Sync\ProductSyncService;
use App\Integration\O2S\Sync\SyncResult;
use App\Integration\O2S\Sync\UserSyncService;
use Psr\Log\LoggerInterface;

/**
 * O2S Integration Manager - Main entry point for O2S operations.
 * 
 * This service provides a unified interface for all O2S operations,
 * combining contact management, compte management, and synchronization.
 * 
 * Use this service in controllers and other application services
 * rather than accessing the underlying services directly.
 */
class O2SManager
{
    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly ContactServiceInterface $contactService,
        private readonly CompteServiceInterface $compteService,
        private readonly UserSyncService $userSyncService,
        private readonly ProductSyncService $productSyncService,
        private readonly LoggerInterface $logger,
    ) {
    }

    // ==================== CONNECTION ====================

    /**
     * Check if O2S is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Test the O2S API connection.
     */
    public function testConnection(): bool
    {
        return $this->client->testConnection();
    }

    // ==================== USER OPERATIONS ====================

    /**
     * Link a user to their O2S contact by email.
     * 
     * @return string|null The O2S contact ID if found, null otherwise
     */
    public function linkUserToO2S(User $user): ?string
    {
        return $this->userSyncService->linkUserByEmail($user);
    }

    /**
     * Synchronize a user's data from O2S.
     */
    public function syncUser(User $user): SyncResult
    {
        return $this->userSyncService->syncUserFromO2S($user);
    }

    /**
     * Get the O2S contact data for a user.
     */
    public function getContactForUser(User $user): ?ContactDTO
    {
        return $this->userSyncService->getO2SContactForUser($user);
    }

    // ==================== PRODUCT OPERATIONS ====================

    /**
     * Synchronize all products for a user from O2S.
     */
    public function syncProducts(User $user): SyncResult
    {
        return $this->productSyncService->syncProductsForUser($user);
    }

    /**
     * Get O2S comptes for a user (without syncing to local DB).
     * 
     * @return CompteDTO[]
     */
    public function getComptesForUser(User $user): array
    {
        return $this->productSyncService->getO2SComptesForUser($user);
    }

    /**
     * Refresh valuation for a specific product from O2S.
     */
    public function refreshProductValuation(ProductAccount $product): bool
    {
        return $this->productSyncService->refreshProductValuation($product);
    }

    /**
     * Get portfolio summary from O2S for a user.
     * 
     * @return array{
     *     total_valuation: float,
     *     compte_count: int,
     *     by_type: array<string, array{count: int, total: float}>
     * }
     */
    public function getPortfolioSummary(User $user): array
    {
        return $this->productSyncService->getPortfolioSummaryFromO2S($user);
    }

    // ==================== FULL SYNC ====================

    /**
     * Perform a full synchronization for a user (link + user data + products).
     * 
     * @return array{
     *     linked: bool,
     *     user_synced: bool,
     *     products_result: SyncResult
     * }
     */
    public function fullSync(User $user): array
    {
        $result = [
            'linked' => false,
            'user_synced' => false,
            'products_result' => SyncResult::failure(['Not synced']),
        ];

        // Step 1: Link to O2S if needed
        if (!$user->isLinkedToO2S()) {
            $contactId = $this->linkUserToO2S($user);
            $result['linked'] = $contactId !== null;
            
            if (!$result['linked']) {
                $this->logger->info('User not found in O2S', [
                    'userId' => $user->getId(),
                    'email' => $user->getEmail(),
                ]);
                return $result;
            }
        } else {
            $result['linked'] = true;
        }

        // Step 2: Sync user data
        $userSyncResult = $this->syncUser($user);
        $result['user_synced'] = $userSyncResult->isSuccess();

        // Step 3: Sync products
        $result['products_result'] = $this->syncProducts($user);

        return $result;
    }

    // ==================== DIRECT API ACCESS ====================

    /**
     * Get a contact directly from O2S by ID.
     */
    public function getContact(string $contactId): ContactDTO
    {
        return $this->contactService->getContact($contactId);
    }

    /**
     * Get a compte directly from O2S by ID.
     */
    public function getCompte(string $compteId): CompteDTO
    {
        return $this->compteService->getCompte($compteId);
    }

    /**
     * Get total valuation for a contact from O2S.
     */
    public function getTotalValuation(string $contactId): float
    {
        return $this->compteService->getTotalValuationForContact($contactId);
    }

    // ==================== BATCH OPERATIONS ====================

    /**
     * Link all unlinked users to O2S contacts.
     */
    public function batchLinkUsers(): SyncResult
    {
        return $this->userSyncService->batchLinkUsers();
    }
}


