<?php

declare(strict_types=1);

namespace App\Integration\O2S\Sync;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use App\Integration\O2S\DTO\Compte\CompteDTO;
use App\Integration\O2S\Service\CompteServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for synchronizing O2S comptes with ADN ProductAccounts.
 */
class ProductSyncService
{
    public function __construct(
        private readonly CompteServiceInterface $compteService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronizes all products for a user from O2S.
     */
    public function syncProductsForUser(User $user): SyncResult
    {
        $contactId = $user->getO2sContactId();
        if (!$contactId) {
            return SyncResult::failure(['User is not linked to an O2S contact']);
        }

        $this->logger->info('Syncing products for user from O2S', [
            'userId' => $user->getId(),
            'o2sContactId' => $contactId,
        ]);

        try {
            $comptes = $this->compteService->getActiveComptesForContact($contactId);

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($comptes as $compte) {
                try {
                    $result = $this->syncProductFromCompte($user, $compte);
                    
                    if ($result['action'] === 'created') {
                        $created++;
                    } elseif ($result['action'] === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = sprintf('Compte %s: %s', $compte->getId(), $e->getMessage());
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Product sync completed', [
                'userId' => $user->getId(),
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ]);

            return new SyncResult(
                success: empty($errors),
                created: $created,
                updated: $updated,
                skipped: $skipped,
                errors: $errors,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync products from O2S', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return SyncResult::failure([$e->getMessage()]);
        }
    }

    /**
     * Synchronizes a single product from O2S compte.
     * 
     * @return array{action: string, product: ProductAccount}
     */
    public function syncProductFromCompte(User $user, CompteDTO $compte): array
    {
        $productRepo = $this->entityManager->getRepository(ProductAccount::class);

        // Check if product already exists
        $existing = $productRepo->findOneBy([
            'user' => $user,
            'o2sCompteId' => $compte->getId(),
        ]);

        if ($existing) {
            $this->updateProductFromCompte($existing, $compte);
            return ['action' => 'updated', 'product' => $existing];
        }

        // Create new product
        $product = $this->createProductFromCompte($user, $compte);
        $this->entityManager->persist($product);

        return ['action' => 'created', 'product' => $product];
    }

    /**
     * Gets O2S comptes for a user (without syncing).
     * 
     * @return CompteDTO[]
     */
    public function getO2SComptesForUser(User $user): array
    {
        $contactId = $user->getO2sContactId();
        if (!$contactId) {
            return [];
        }

        try {
            return $this->compteService->getActiveComptesForContact($contactId);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch O2S comptes for user', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Refreshes valuation for a specific product from O2S.
     */
    public function refreshProductValuation(ProductAccount $product): bool
    {
        $compteId = $product->getO2sCompteId();
        if (!$compteId) {
            return false;
        }

        try {
            $compte = $this->compteService->getCompte($compteId);
            $this->updateProductFromCompte($product, $compte);
            $this->entityManager->flush();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to refresh product valuation', [
                'productId' => $product->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Creates a new ProductAccount from O2S CompteDTO.
     */
    private function createProductFromCompte(User $user, CompteDTO $compte): ProductAccount
    {
        $product = new ProductAccount();
        $product->setUser($user);
        $product->setO2sCompteId($compte->getId());

        $this->updateProductFromCompte($product, $compte);

        // Set fiscal date from compte opening date or default to now
        $fiscalDate = $compte->getDateOuverture() ?? new \DateTimeImmutable();
        $product->setFiscalDate(\DateTime::createFromImmutable($fiscalDate));

        return $product;
    }

    /**
     * Updates ProductAccount fields from O2S CompteDTO.
     */
    private function updateProductFromCompte(ProductAccount $product, CompteDTO $compte): void
    {
        // Map O2S type to ADN product type
        $product->setProductType($compte->getProductType());

        // Set display name
        $displayAlias = $compte->getLibelle() ?? $compte->getDisplayName();
        $product->setDisplayAlias($displayAlias);

        // Set internal name (numero)
        if ($compte->getNumero()) {
            $product->setInternalName($compte->getNumero());
        }

        // Update valuation if available
        if ($compte->getMontant() !== null) {
            // Note: This stores O2S valuation - actual holding updates 
            // would require additional O2S Account Details API integration
            $product->setInitialAmount((string) $compte->getMontant());
        }

        // Set distributor based on product info (could be enhanced with O2S product catalog)
        // For now, keep existing or set default
        if (empty($product->getDistributor())) {
            $product->setDistributor('O2S');
        }

        // Update sync timestamp
        $product->setO2sSyncedAt(new \DateTimeImmutable());
    }

    /**
     * Calculates portfolio summary from O2S for a user.
     * 
     * @return array{
     *     total_valuation: float,
     *     compte_count: int,
     *     by_type: array<string, array{count: int, total: float}>
     * }
     */
    public function getPortfolioSummaryFromO2S(User $user): array
    {
        $comptes = $this->getO2SComptesForUser($user);
        
        return $this->compteService->calculateSummary($comptes);
    }
}


