<?php

declare(strict_types=1);

namespace App\Integration\O2S\Sync;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use App\Integration\O2S\DTO\Compte\CompteDTO;
use App\Integration\O2S\DTO\Contact\ContactDTO;
use App\Integration\O2S\DTO\Product\ProductDTO;
use App\Integration\O2S\Service\CompteService;
use App\Integration\O2S\Service\ContactServiceInterface;
use App\Integration\O2S\Service\InstitutionServiceInterface;
use App\Integration\O2S\Service\ProductServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Main synchronization service for O2S data.
 * 
 * Handles synchronization of contacts and comptes from O2S to local database.
 */
final class O2SSyncService
{
    private EntityManagerInterface $entityManager;
    
    /** @var array<string, ProductDTO> Pre-fetched products map for type mapping */
    private array $productsMap = [];

    /** @var array<string, string>|null Pre-fetched institutions map (institutionId => label) */
    private ?array $institutionsMap = null;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly ContactServiceInterface $contactService,
        private readonly CompteService $compteService,
        private readonly ProductServiceInterface $productService,
        private readonly InstitutionServiceInterface $institutionService,
        private readonly LoggerInterface $logger,
    ) {
        $this->entityManager = $doctrine->getManager();
    }
    
    /**
     * Pre-fetches the products map for fast type lookups during sync.
     */
    private function ensureProductsMapLoaded(): void
    {
        if (empty($this->productsMap)) {
            try {
                $this->productsMap = $this->productService->getProductsMap();
                $this->logger->info('Products map loaded', ['count' => count($this->productsMap)]);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to load products map, falling back to libelle parsing', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Pre-fetches the institutions map for label resolution during sync.
     */
    private function ensureInstitutionsMapLoaded(): void
    {
        if ($this->institutionsMap === null) {
            try {
                $this->institutionsMap = $this->institutionService->getInstitutionsMap();
                $this->logger->info('Institutions map loaded', ['count' => count($this->institutionsMap)]);
            } catch (\Throwable $e) {
                $this->institutionsMap = [];
                $this->logger->warning('Failed to load institutions map', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Resolves the actual institution label from O2S Products + Institutions APIs.
     * Returns null if unable to resolve.
     */
    private function resolveInstitutionLabel(CompteDTO $compte): ?string
    {
        $this->ensureProductsMapLoaded();
        $this->ensureInstitutionsMapLoaded();

        $produitId = $compte->getProduitId();
        if (!$produitId || !isset($this->productsMap[$produitId])) {
            return null;
        }

        $product = $this->productsMap[$produitId];
        $institutionId = $product->getInstitutionId();
        if (!$institutionId || !isset($this->institutionsMap[$institutionId])) {
            return null;
        }

        return $this->institutionsMap[$institutionId];
    }

    /**
     * Resets the EntityManager if it's closed (after a DB error).
     */
    private function resetEntityManagerIfNeeded(): void
    {
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->doctrine->resetManager();
            $this->logger->warning('EntityManager was reset after being closed');
        }
    }

    /**
     * Synchronizes all O2S contacts with local users.
     * 
     * @return SyncResult
     */
    public function syncAllContacts(): SyncResult
    {
        $result = new SyncResult();
        $this->logger->info('Starting O2S contacts synchronization');

        try {
            // Fetch all contacts from O2S
            $o2sContacts = $this->contactService->getAllContacts();
            $this->logger->info('Fetched O2S contacts', ['count' => count($o2sContacts)]);

            $batchSize = 50;
            $i = 0;

            foreach ($o2sContacts as $o2sContact) {
                try {
                    $this->resetEntityManagerIfNeeded();
                    $this->syncContact($o2sContact, $result);
                    $i++;
                    
                    // Flush every entity individually to isolate errors
                    $this->entityManager->flush();
                    
                    if (($i % $batchSize) === 0) {
                        $this->entityManager->clear();
                        $this->logger->debug('Cleared batch', ['processed' => $i]);
                    }
                } catch (\Throwable $e) {
                    // Reset the entity manager to recover from the error
                    $this->resetEntityManagerIfNeeded();
                    
                    $result->addError(sprintf(
                        'Contact %s: %s',
                        $o2sContact->getId(),
                        $e->getMessage()
                    ));
                    $this->logger->error('Failed to sync contact', [
                        'contactId' => $o2sContact->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Final flush for remaining entities
            $this->resetEntityManagerIfNeeded();
            $this->logger->info('O2S contacts synchronization completed', [
                'result' => $result->toArray(),
            ]);

        } catch (\Throwable $e) {
            $result->addError('Global sync error: ' . $e->getMessage());
            $this->logger->error('O2S contacts synchronization failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Synchronizes a single O2S contact with local user.
     */
    public function syncContact(ContactDTO $o2sContact, ?SyncResult $result = null): ?User
    {
        $result ??= new SyncResult();
        $userRepo = $this->entityManager->getRepository(User::class);

        // Try to find existing user by O2S contact ID (exact match)
        $user = $userRepo->findOneBy(['o2sContactId' => $o2sContact->getId()]);

        // If not found by O2S ID, try to match by email
        if (!$user && $o2sContact->getEmail()) {
            $candidate = $userRepo->findOneBy(['email' => $o2sContact->getEmail()]);
            // Only use email match if the user is NOT already linked to a different O2S contact
            if ($candidate && !$candidate->getO2sContactId()) {
                $user = $candidate;
            }
        }

        // If still not found, try to match by first name + last name (case-insensitive)
        if (!$user && $o2sContact->getNom() && $o2sContact->getPrenom()) {
            $candidates = $this->entityManager->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.o2sContactId IS NULL')
                ->andWhere('LOWER(TRIM(u.lastName)) = LOWER(TRIM(:lastName))')
                ->andWhere('LOWER(TRIM(u.firstName)) = LOWER(TRIM(:firstName))')
                ->setParameter('lastName', $o2sContact->getNom())
                ->setParameter('firstName', $o2sContact->getPrenom())
                ->getQuery()
                ->getResult();

            if (count($candidates) === 1) {
                $user = $candidates[0];
                $this->logger->info('Matched O2S contact to existing user by name', [
                    'o2sContactId' => $o2sContact->getId(),
                    'userId' => $user->getId(),
                    'name' => $o2sContact->getPrenom() . ' ' . $o2sContact->getNom(),
                ]);
            }
        }

        $isNew = $user === null;

        if ($isNew) {
            // Create new user from O2S contact
            $user = $this->createUserFromContact($o2sContact);
            $this->entityManager->persist($user);
            $result->addCreated();
            $this->logger->info('Created new user from O2S contact', [
                'o2sContactId' => $o2sContact->getId(),
                'email' => $user->getEmail(),
            ]);
        } else {
            // Update existing user with O2S data
            $this->updateUserFromContact($user, $o2sContact);
            $result->addUpdated();
            $this->logger->debug('Updated user from O2S contact', [
                'userId' => $user->getId(),
                'o2sContactId' => $o2sContact->getId(),
            ]);
        }

        // Link user to O2S contact
        $user->setO2sContactId($o2sContact->getId());
        $user->setO2sSyncedAt(new \DateTimeImmutable());

        // typeContact only available from individual endpoint, not list
        if ($o2sContact->getTypeContact() !== null) {
            $user->setO2sTypeContact($o2sContact->getTypeContact());
        }

        return $user;
    }

    /**
     * Returns the count of O2S-linked users (for progress tracking).
     */
    public function getLinkedUsersCount(): int
    {
        return (int) $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Synchronizes all comptes for all O2S-linked users.
     * Optimized: uses CompteDTO montant directly, no extra account-details API calls.
     * Uses user IDs to avoid detached entity issues after EntityManager clear.
     * 
     * @return SyncResult
     */
    public function syncAllComptes(): SyncResult
    {
        $result = new SyncResult();
        $this->logger->info('Starting O2S comptes synchronization');

        // Pre-fetch products map for type resolution (cached, fast)
        $this->ensureProductsMapLoaded();

        // Get all linked user IDs (lightweight query, no objects loaded)
        $userIds = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        $this->logger->info('Found O2S-linked users', ['count' => count($userIds)]);

        $batchSize = 30;
        $i = 0;

        foreach ($userIds as $userId) {
            try {
                $this->resetEntityManagerIfNeeded();
                
                // Re-fetch user fresh from DB (always managed, never detached)
                $user = $this->entityManager->getRepository(User::class)->find($userId);
                if (!$user) {
                    $this->logger->warning('User not found during comptes sync', ['userId' => $userId]);
                    continue;
                }

                $userResult = $this->syncComptesForUser($user);
                $result->merge($userResult);
                $this->entityManager->flush();
                
                $i++;
                if (($i % $batchSize) === 0) {
                    $this->entityManager->clear();
                    $this->logger->debug('Comptes batch cleared', ['processed' => $i]);
                }
            } catch (\Throwable $e) {
                $this->resetEntityManagerIfNeeded();
                $result->addError(sprintf(
                    'User o2s_%s (ID: %d): %s',
                    $userId,
                    $userId,
                    $e->getMessage()
                ));
                $this->logger->error('Failed to sync comptes for user', [
                    'userId' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->resetEntityManagerIfNeeded();
        $this->logger->info('O2S comptes synchronization completed', [
            'result' => $result->toArray(),
        ]);

        return $result;
    }

    /**
     * Synchronizes comptes for a batch of O2S-linked users.
     * Designed for AJAX calls with offset/limit to avoid PHP timeouts on shared hosting.
     * 
     * @param int $offset Starting offset in the list of O2S-linked users
     * @param int $batchSize Number of users to process in this batch
     * @return array{result: SyncResult, processed: int, total: int, hasMore: bool}
     */
    public function syncComptesBatch(int $offset = 0, int $batchSize = 10): array
    {
        $result = new SyncResult();

        $this->ensureProductsMapLoaded();

        // Get total count of linked users
        $total = (int) $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // Get batch of user IDs
        $userIds = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.o2sContactId IS NOT NULL')
            ->orderBy('u.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getSingleColumnResult();

        $this->logger->info('Syncing comptes batch', [
            'offset' => $offset,
            'batchSize' => $batchSize,
            'usersInBatch' => count($userIds),
            'total' => $total,
        ]);

        foreach ($userIds as $userId) {
            try {
                $this->resetEntityManagerIfNeeded();

                $user = $this->entityManager->getRepository(User::class)->find($userId);
                if (!$user) {
                    continue;
                }

                $userResult = $this->syncComptesForUser($user);
                $result->merge($userResult);
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $this->resetEntityManagerIfNeeded();
                $result->addError(sprintf('User %d: %s', $userId, $e->getMessage()));
                $this->logger->error('Failed to sync comptes for user in batch', [
                    'userId' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->clear();

        $processed = $offset + count($userIds);
        return [
            'result' => $result,
            'processed' => $processed,
            'total' => $total,
            'hasMore' => $processed < $total,
        ];
    }

    /**
     * Synchronizes comptes for a specific user.
     * Optimized: no account-details API calls during bulk sync.
     * 
     * @return SyncResult
     */
    public function syncComptesForUser(User $user): SyncResult
    {
        $result = new SyncResult();

        if (!$user->getO2sContactId()) {
            $result->addSkipped();
            return $result;
        }

        $this->logger->debug('Syncing comptes for user', [
            'userId' => $user->getId(),
            'o2sContactId' => $user->getO2sContactId(),
        ]);

        // Step 1: Get list of compte IDs for this contact (list API returns partial data)
        $o2sCompteSummaries = $this->compteService->getComptesForContact($user->getO2sContactId());

        // Get existing product accounts for this user
        $existingAccounts = $this->entityManager->getRepository(ProductAccount::class)
            ->findBy(['user' => $user]);

        // Index existing accounts by O2S compte ID
        $existingByO2sId = [];
        foreach ($existingAccounts as $account) {
            if ($account->getO2sCompteId()) {
                $existingByO2sId[$account->getO2sCompteId()] = $account;
            }
        }

        // Step 2: For each compte, fetch full details from individual endpoint
        $syncedO2sIds = [];
        foreach ($o2sCompteSummaries as $compteSummary) {
            $compteId = $compteSummary->getId();
            $syncedO2sIds[] = $compteId;

            try {
                // Fetch full details (produitLie, valeur, placement, etc.)
                $o2sCompte = $this->compteService->getCompte($compteId);

                if (isset($existingByO2sId[$compteId])) {
                    // Update existing with full data
                    $this->updateProductAccountFromCompte($existingByO2sId[$compteId], $o2sCompte);
                    $result->addUpdated();
                } else {
                    // Create new with full data
                    $account = $this->createProductAccountFromCompte($user, $o2sCompte);
                    $this->entityManager->persist($account);
                    $result->addCreated();
                }
            } catch (\Throwable $e) {
                // If individual fetch fails, use summary data as fallback
                $this->logger->warning('Failed to fetch full compte details, using summary', [
                    'compteId' => $compteId,
                    'error' => $e->getMessage(),
                ]);
                
                if (isset($existingByO2sId[$compteId])) {
                    $this->updateProductAccountFromCompte($existingByO2sId[$compteId], $compteSummary);
                    $result->addUpdated();
                } else {
                    $account = $this->createProductAccountFromCompte($user, $compteSummary);
                    $this->entityManager->persist($account);
                    $result->addCreated();
                }
            }
        }

        // Mark accounts no longer in O2S (but don't delete them)
        foreach ($existingAccounts as $account) {
            if ($account->getO2sCompteId() && !in_array($account->getO2sCompteId(), $syncedO2sIds)) {
                $this->logger->warning('Account no longer found in O2S', [
                    'accountId' => $account->getId(),
                    'o2sCompteId' => $account->getO2sCompteId(),
                ]);
            }
        }

        $user->setO2sSyncedAt(new \DateTimeImmutable());

        return $result;
    }

    /**
     * Full synchronization: contacts then comptes.
     * 
     * @return array{contacts: SyncResult, comptes: SyncResult}
     */
    public function syncAll(): array
    {
        $this->logger->info('Starting full O2S synchronization');

        $contactsResult = $this->syncAllContacts();
        $comptesResult = $this->syncAllComptes();

        $this->logger->info('Full O2S synchronization completed', [
            'contacts' => $contactsResult->toArray(),
            'comptes' => $comptesResult->toArray(),
        ]);

        return [
            'contacts' => $contactsResult,
            'comptes' => $comptesResult,
        ];
    }

    // =========================================================================
    // INCREMENTAL SYNC — Rapide, pour cron toutes les 15 min
    // =========================================================================

    /**
     * Incremental sync: only detect and sync NEW contacts (not already in our DB).
     * Ultra-fast: fetches the contact list (~3 API calls), compares with local DB,
     * creates/links only new ones. Does NOT sync comptes (handled by valuation batch).
     * 
     * @return SyncResult
     */
    public function syncNewContacts(): SyncResult
    {
        $result = new SyncResult();
        $this->logger->info('Starting incremental contacts sync');

        try {
            // Step 1: Get all O2S contacts (list is fast: ~3 paginated calls)
            $o2sContacts = $this->contactService->getAllContacts();
            
            // Step 2: Get all existing o2sContactIds from local DB (single fast query)
            $existingContactIds = $this->entityManager->getRepository(User::class)
                ->createQueryBuilder('u')
                ->select('u.o2sContactId')
                ->where('u.o2sContactId IS NOT NULL')
                ->getQuery()
                ->getSingleColumnResult();
            $existingSet = array_flip($existingContactIds);

            // Step 3: Filter to only truly new contacts
            $newContacts = [];
            foreach ($o2sContacts as $contact) {
                if (!isset($existingSet[$contact->getId()])) {
                    $newContacts[] = $contact;
                }
            }

            $this->logger->info('Incremental contacts: analysis', [
                'total_o2s' => count($o2sContacts),
                'existing_linked' => count($existingContactIds),
                'new_detected' => count($newContacts),
            ]);

            if (empty($newContacts)) {
                $result->addMetadata('message', 'No new contacts to sync');
                return $result;
            }

            // Step 4: Sync only new contacts (fast: just creates users, no API call per contact)
            $createdUserIds = [];
            foreach ($newContacts as $contact) {
                try {
                    $this->resetEntityManagerIfNeeded();
                    $user = $this->syncContact($contact, $result);
                    
                    if ($user) {
                        $this->entityManager->persist($user);
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        
                        $createdUserIds[] = $user->getEmail();
                    }
                } catch (\Throwable $e) {
                    $this->resetEntityManagerIfNeeded();
                    $result->addError(sprintf('Contact %s: %s', $contact->getId(), $e->getMessage()));
                    $this->logger->error('Failed to sync new contact', [
                        'contactId' => $contact->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result->addMetadata('created_user_ids', $createdUserIds);
            $result->addMetadata('info', sprintf(
                '%d nouveau(x) contact(s) détecté(s) sur %d total O2S. Comptes synchronisés automatiquement par le prochain cron.',
                count($newContacts),
                count($o2sContacts)
            ));

        } catch (\Throwable $e) {
            $result->addError('Incremental sync error: ' . $e->getMessage());
            $this->logger->error('Incremental contacts sync failed', ['error' => $e->getMessage()]);
        }

        $this->logger->info('Incremental contacts sync completed', ['result' => $result->toArray()]);
        return $result;
    }

    /**
     * Fixes placeholder emails by fetching individual contact details from O2S API.
     * The list endpoint may not include moyensContact (emails), so we fetch each contact individually.
     * 
     * Processes ALL placeholder users in a single pass (no batch limit for the query).
     * For email conflicts (e.g. couples sharing the same email), generates a unique variant.
     * 
     * @return SyncResult
     */
    public function fixPlaceholderEmails(): SyncResult
    {
        $result = new SyncResult();
        $this->logger->info('Starting placeholder email fix (full pass)');

        // Load ALL users with placeholder emails (typically < 100, manageable)
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere('u.email LIKE :placeholder')
            ->setParameter('placeholder', '%@placeholder.local')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        $totalToProcess = count($users);
        $this->logger->info('Placeholder email fix: found users to process', ['count' => $totalToProcess]);

        if ($totalToProcess === 0) {
            $result->addMetadata('fixed', 0);
            $result->addMetadata('noEmail', 0);
            $result->addMetadata('conflicts', 0);
            $result->addMetadata('conflictsResolved', 0);
            $result->addMetadata('remaining', 0);
            return $result;
        }

        $fixed = 0;
        $noEmail = 0;
        $conflicts = 0;
        $conflictsResolved = 0;

        foreach ($users as $user) {
            try {
                $this->resetEntityManagerIfNeeded();

                // Fetch individual contact from O2S API (includes full moyensContact)
                $contact = $this->contactService->getContact($user->getO2sContactId());
                $email = $contact->getEmail();

                if (!$email) {
                    $noEmail++;
                    $result->addSkipped();
                    $this->logger->debug('Placeholder email fix: no email in O2S for contact', [
                        'userId' => $user->getId(),
                        'o2sContactId' => $user->getO2sContactId(),
                    ]);
                    continue;
                }

                // Check email uniqueness
                $existing = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if (!$existing || $existing->getId() === $user->getId()) {
                    // No conflict: use the email directly
                    $oldEmail = $user->getEmail();
                    $user->setEmail($email);
                    $this->entityManager->flush();
                    $fixed++;
                    $result->addUpdated();
                    $this->logger->info('Fixed placeholder email', [
                        'userId' => $user->getId(),
                        'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                        'oldEmail' => $oldEmail,
                        'newEmail' => $email,
                    ]);
                } else {
                    // Conflict: email already used by another user.
                    // Before generating an alias variant, check if the existing user is a "ghost"
                    // (leftover from manual CSV imports without sync data). In that case, reclaim
                    // the email for our sync user and soft-delete the ghost — otherwise we'd
                    // accumulate aliased duplicates (e.g. `local+firstname@domain`).
                    if ($this->isGhostUser($existing)) {
                        $ghostId = $existing->getId();
                        $ghostEmail = $existing->getEmail();
                        $existing->setSuspendedAt(new \DateTimeImmutable());
                        $existing->setSuspendedReason(sprintf(
                            'Doublon détecté lors de la sync O2S : email réattribué au user #%d (contact %s).',
                            $user->getId(),
                            $user->getO2sContactId() ?? 'n/a'
                        ));
                        $this->entityManager->remove($existing); // Gedmo SoftDeleteable: sets deletedAt
                        $this->entityManager->flush();

                        $oldEmail = $user->getEmail();
                        $user->setEmail($email);
                        $this->entityManager->flush();
                        $fixed++;
                        $result->addUpdated();
                        $this->logger->info('Reclaimed email from ghost user during placeholder fix', [
                            'syncUserId' => $user->getId(),
                            'syncUserName' => $user->getFirstName() . ' ' . $user->getLastName(),
                            'ghostUserId' => $ghostId,
                            'ghostEmail' => $ghostEmail,
                            'reclaimedEmail' => $email,
                            'oldPlaceholderEmail' => $oldEmail,
                        ]);
                        continue;
                    }

                    // Real conflict (couple, family member with shared mailbox, etc.)
                    // → generate a unique variant
                    $uniqueEmail = $this->generateUniqueEmailVariant($email, $user);
                    if ($uniqueEmail) {
                        $oldEmail = $user->getEmail();
                        $user->setEmail($uniqueEmail);
                        $this->entityManager->flush();
                        $conflictsResolved++;
                        $fixed++;
                        $result->addUpdated();
                        $this->logger->info('Fixed placeholder email with unique variant (couple/family)', [
                            'userId' => $user->getId(),
                            'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                            'oldEmail' => $oldEmail,
                            'originalEmail' => $email,
                            'newEmail' => $uniqueEmail,
                        ]);
                    } else {
                        $conflicts++;
                        $result->addSkipped();
                        $this->logger->warning('Placeholder email fix: email conflict, could not generate variant', [
                            'userId' => $user->getId(),
                            'email' => $email,
                            'existingUserId' => $existing->getId(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $this->resetEntityManagerIfNeeded();
                $result->addError(sprintf('User %d: %s', $user->getId(), $e->getMessage()));
                $this->logger->error('Placeholder email fix failed for user', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $remaining = $this->countPlaceholderEmails();

        $result->addMetadata('fixed', $fixed);
        $result->addMetadata('noEmail', $noEmail);
        $result->addMetadata('conflicts', $conflicts);
        $result->addMetadata('conflictsResolved', $conflictsResolved);
        $result->addMetadata('remaining', $remaining);

        $this->logger->info('Placeholder email fix completed', [
            'fixed' => $fixed,
            'noEmail' => $noEmail,
            'conflicts' => $conflicts,
            'conflictsResolved' => $conflictsResolved,
            'remaining' => $remaining,
        ]);

        return $result;
    }

    /**
     * Detects a "ghost" user: a leftover from a manual CSV/admin import that the O2S
     * sync later duplicated by creating a new aliased user (e.g. `local+firstname@domain`).
     *
     * Criteria (all must be true to be considered a ghost):
     *  - No `o2sContactId` (never linked to a Harvest contact)
     *  - No `ProductAccount` rows (no real financial data)
     *  - No admin role (not a system / staff account)
     *
     * When `fixPlaceholderEmails()` encounters such a ghost holding the canonical email
     * we want for a sync user, we soft-delete the ghost and reclaim the email instead of
     * adding yet another `+alias` variant. See the cleanup performed on 2026-06-05 for
     * the 15 ghosts created by the manual import of 2026-01-15 16:09.
     */
    private function isGhostUser(User $candidate): bool
    {
        if ($candidate->getO2sContactId() !== null) {
            return false;
        }
        foreach ($candidate->getRoles() as $role) {
            $upper = strtoupper((string) $role);
            if ($upper === 'ROLE_ADMIN' || $upper === 'ROLE_SUPER_ADMIN') {
                return false;
            }
        }
        $nbAccounts = (int) $this->entityManager
            ->getRepository(ProductAccount::class)
            ->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->where('pa.user = :user')
            ->setParameter('user', $candidate)
            ->getQuery()
            ->getSingleScalarResult();
        return $nbAccounts === 0;
    }

    /**
     * Generates a unique email variant for a user when the original email is already taken.
     * Uses the user's first name as a prefix (e.g. "jean.dupont@gmail.com" → "marie+jean.dupont@gmail.com").
     * Falls back to user ID if first name is not available.
     */
    private function generateUniqueEmailVariant(string $email, User $user): ?string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        [$localPart, $domain] = $parts;

        // Use first name (normalized) as distinguishing prefix
        $firstName = $user->getFirstName();
        if ($firstName) {
            $normalized = mb_strtolower(trim($firstName));
            $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);
        }

        // If no first name or it's empty, use user ID
        if (empty($normalized)) {
            $normalized = (string) $user->getId();
        }

        // Try: localpart+firstname@domain (most email providers support + addressing)
        $candidate = $localPart . '+' . $normalized . '@' . $domain;
        $existing = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $candidate]);

        if (!$existing || $existing->getId() === $user->getId()) {
            return $candidate;
        }

        // Try: localpart+firstname.userid@domain
        $candidate = $localPart . '+' . $normalized . '.' . $user->getId() . '@' . $domain;
        $existing = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $candidate]);

        if (!$existing || $existing->getId() === $user->getId()) {
            return $candidate;
        }

        // Give up (extremely unlikely)
        return null;
    }

    /**
     * Returns the count of users with placeholder emails.
     */
    public function countPlaceholderEmails(): int
    {
        return (int) $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere('u.email LIKE :placeholder')
            ->setParameter('placeholder', '%@placeholder.local')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Batch valuation update: updates valuations for a batch of accounts.
     * Fetches individual account details for accounts with stale or missing valuations.
     * Designed to be called frequently via cron (processes N accounts per run).
     * 
     * @param int $batchSize Number of accounts to process per run
     * @return SyncResult
     */
    public function syncValuationsBatch(int $batchSize = 50): SyncResult
    {
        $result = new SyncResult();
        $this->logger->info('Starting batch valuation update', ['batchSize' => $batchSize]);

        $this->ensureProductsMapLoaded();

        // Get accounts that need valuation updates, prioritizing:
        // 1. Accounts with no valuation at all
        // 2. Accounts with oldest sync date
        $accounts = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->orderBy('CASE WHEN p.o2sValuation IS NULL OR p.o2sValuation = \'0\' THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('p.o2sSyncedAt', 'ASC')
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();

        $this->logger->info('Batch valuation: processing accounts', ['count' => count($accounts)]);

        foreach ($accounts as $account) {
            try {
                $this->resetEntityManagerIfNeeded();

                $compteId = $account->getO2sCompteId();

                // 1. Fetch from /comptes/{id} → montant (valeur titres/UC) + dateValeur
                $compte = $this->compteService->getCompte($compteId);
                $this->updateProductAccountFromCompte($account, $compte);

                // 2. Fetch /accounts/{id}/account-details → totalValue (UC) + liquidity (espèces)
                //    Le portail O2S affiche : evaluation = totalValue + liquidity
                //    ⚠ On utilise UNIQUEMENT le montant API (/comptes), PAS la valeur stockée en BDD,
                //    sinon la liquidité est comptée 2 fois à chaque sync.
                $apiMontant = $compte->getMontant(); // Montant brut de /comptes/{id} (peut être null)
                    try {
                        $details = $this->compteService->getAccountDetails($compteId);

                    if ($details->hasValuation()) {
                        $liquidity = $details->getLiquidity() ?? 0.0;
                        $detailTotalValue = $details->getTotalValue() ?? 0.0;

                        // ⚠ montant de /comptes/{id} = valorisation TOTALE (UC + fonds euros + liquidité)
                        //   NE PAS ajouter liquidity dessus → sinon double comptage !
                        //   On utilise account-details uniquement si montant n'est pas dispo.
                        if ($apiMontant !== null && $apiMontant > 0) {
                            // Cas principal : montant API = valeur totale du contrat
                            $account->setO2sValuation((string) $apiMontant);
                            $this->logger->info('Valuation = API montant (total, inclut liquidité)', [
                                'compteId' => $compteId,
                                'apiMontant' => $apiMontant,
                                'detailTotalValue' => $detailTotalValue,
                                'detailLiquidity' => $liquidity,
                            ]);
                        } else {
                            // Pas de montant dans /comptes → reconstruire depuis account-details
                            $effectiveVal = $detailTotalValue + $liquidity;
                            if ($effectiveVal > 0) {
                                $account->setO2sValuation((string) $effectiveVal);
                                if ($details->getValuationDate()) {
                                    $account->setO2sValuationDate($details->getValuationDate());
                                }
                                $this->logger->info('Valuation from account-details (no API montant)', [
                                    'compteId' => $compteId,
                                    'totalValue' => $detailTotalValue,
                                    'liquidity' => $liquidity,
                                    'evaluation' => $effectiveVal,
                                ]);
                            }
                        }
                    }
                    } catch (\Throwable $detailsError) {
                    // account-details might not be available for all accounts (ex: 400 blocage)
                    $this->logger->debug('account-details not available for liquidity', [
                            'compteId' => $compteId,
                            'error' => $detailsError->getMessage(),
                        ]);
                }

                $this->entityManager->flush();
                $result->addUpdated();
            } catch (\Throwable $e) {
                $this->resetEntityManagerIfNeeded();
                $result->addError(sprintf(
                    'Account %s: %s',
                    $account->getO2sCompteId(),
                    $e->getMessage()
                ));
                $this->logger->error('Failed to update valuation', [
                    'o2sCompteId' => $account->getO2sCompteId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Batch valuation update completed', ['result' => $result->toArray()]);
        return $result;
    }

    /**
     * Sync comptes for O2S-linked users who have no ProductAccount yet.
     * This ensures that all O2S contacts created by syncNewContacts() 
     * eventually get their accounts synced.
     * 
     * @param int $batchSize Number of users to process per run
     * @return SyncResult
     */
    public function syncMissingComptes(int $batchSize = 20): SyncResult
    {
        $result = new SyncResult();
        $this->logger->info('Starting sync for users missing comptes', ['batchSize' => $batchSize]);

        $this->ensureProductsMapLoaded();

        // Phase 1: Users with NO product accounts at all
        $sub = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('pa')
            ->select('IDENTITY(pa.user)')
            ->where('pa.o2sCompteId IS NOT NULL')
            ->getDQL();

        $userIdsNoAccounts = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere('u.id NOT IN (' . $sub . ')')
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getSingleColumnResult();

        // Phase 2: Recently synced users who might have new accounts added in O2S
        // Check users whose last sync was > 1 hour ago (rotating through all users)
        $userIdsStale = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere('u.o2sSyncedAt < :staleDate OR u.o2sSyncedAt IS NULL')
            ->setParameter('staleDate', new \DateTimeImmutable('-1 hour'))
            ->orderBy('u.o2sSyncedAt', 'ASC')
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getSingleColumnResult();

        $userIds = array_unique(array_merge($userIdsNoAccounts, $userIdsStale));
        $userIds = array_slice($userIds, 0, $batchSize);

        $this->logger->info('Users to sync comptes', [
            'no_accounts' => count($userIdsNoAccounts),
            'stale' => count($userIdsStale),
            'total' => count($userIds),
        ]);

        if (empty($userIds)) {
            return $result;
        }

        foreach ($userIds as $userId) {
            try {
                $this->resetEntityManagerIfNeeded();

                $user = $this->entityManager->getRepository(User::class)->find($userId);
                if (!$user || !$user->getO2sContactId()) {
                    continue;
                }

                $userResult = $this->syncComptesForUser($user);
                $result->merge($userResult);
                $this->entityManager->flush();

                $this->logger->debug('Synced comptes for user', [
                    'userId' => $userId,
                    'created' => $userResult->getCreated(),
                ]);
            } catch (\Throwable $e) {
                $this->resetEntityManagerIfNeeded();
                $result->addError(sprintf('User %d: %s', $userId, $e->getMessage()));
                $this->logger->error('Failed to sync comptes for user', [
                    'userId' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->clear();
        $this->logger->info('Missing comptes sync completed', ['result' => $result->toArray()]);
        return $result;
    }

    /**
     * Sync comptes for a specific user by user ID.
     * Useful for on-demand sync when viewing a user's page.
     * 
     * @return SyncResult
     */
    public function syncComptesForUserId(int $userId): SyncResult
    {
        $this->resetEntityManagerIfNeeded();
        $this->ensureProductsMapLoaded();
        
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user || !$user->getO2sContactId()) {
            $result = new SyncResult();
            $result->addSkipped();
            return $result;
        }

        $result = $this->syncComptesForUser($user);
        $this->entityManager->flush();
        return $result;
    }

    /**
     * Backfill o2s_type_contact for O2S users by fetching individual contacts.
     * Supports batch mode for shared hosting with limited execution time.
     * 
     * @param int $limit Max contacts to process (0 = all)
     * @return array{updated: int, errors: int, skipped: int, remaining: int, total: int}
     */
    public function backfillTypeContacts(int $limit = 0, callable $onProgress = null): array
    {
        $qb = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere('u.o2sTypeContact IS NULL')
            ->orderBy('u.id', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        $users = $qb->getQuery()->getResult();

        $totalRemaining = (int) $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere('u.o2sTypeContact IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $totalAll = (int) $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $stats = ['updated' => 0, 'errors' => 0, 'skipped' => 0, 'remaining' => $totalRemaining, 'total' => $totalAll];
        $i = 0;

        foreach ($users as $user) {
            try {
                $this->resetEntityManagerIfNeeded();
                $contact = $this->contactService->getContact($user->getO2sContactId());
                $typeContact = $contact->getTypeContact();

                if ($typeContact !== null) {
                    $user->setO2sTypeContact($typeContact);
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->logger->debug('Failed to fetch typeContact', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $i++;
            if (($i % 20) === 0) {
                $this->entityManager->flush();
            }
            if ($onProgress) {
                $onProgress($i, count($users));
            }
        }

        $this->resetEntityManagerIfNeeded();
        $this->entityManager->flush();

        $stats['remaining'] = $totalRemaining - $stats['updated'] - $stats['skipped'];
        if ($stats['remaining'] < 0) {
            $stats['remaining'] = 0;
        }

        return $stats;
    }

    /**
     * Creates a new User from O2S contact data.
     */
    private function createUserFromContact(ContactDTO $contact): User
    {
        $user = new User();

        // Generate a unique email if not provided
        $email = $contact->getEmail();
        if (!$email) {
            // Create a placeholder email based on contact ID
            $email = sprintf('o2s_%s@placeholder.local', $contact->getId());
        }

        $user->setEmail($email);
        $user->setFirstName($this->truncateString($contact->getPrenom() ?? '', 45));
        $user->setLastName($this->truncateString($contact->getNom() ?? '', 45));
        
        // Set phone if available
        $phone = $contact->getTelephone() ?? $contact->getTelephoneMobile();
        if ($phone) {
            $user->setPhone($phone);
        }

        // Set birth date if available
        if ($contact->getDateNaissance()) {
            $user->setBirthday($contact->getDateNaissance());
        }

        // Set address if available
        if ($address = $contact->getAdresse()) {
            $user->setAddress($address->getVoie());
            $user->setCity($address->getLocalite());
            $user->setPostalCode($address->getCodePostal());
            $user->setCountry($address->getCodePays() ?? 'FR');
        }

        // Set gender from civilité
        if ($contact->getCivilite()) {
            $gender = match($contact->getCivilite()) {
                'M' => User::GENDER_MAN,
                'MME', 'MLLE' => User::GENDER_WOMAN,
                default => null,
            };
            if ($gender) {
                $user->setGender($gender);
            }
        }

        // Set default password (should be changed on first login)
        $user->setPassword(bin2hex(random_bytes(16)));

        // Set default role
        $user->setRoles([User::ROLE_USER]);

        return $user;
    }

    /**
     * Updates an existing User with O2S contact data.
     */
    private function updateUserFromContact(User $user, ContactDTO $contact): void
    {
        // Update email if user has a placeholder and O2S provides a real one
        if ($contact->getEmail() && $this->isPlaceholderEmail($user->getEmail())) {
            $realEmail = $contact->getEmail();
            // Vérifier qu'aucun autre utilisateur n'a déjà cet email
            $existing = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $realEmail]);
            if (!$existing || $existing->getId() === $user->getId()) {
                $user->setEmail($realEmail);
                $this->logger->info('Updated placeholder email with real O2S email', [
                    'userId' => $user->getId(),
                    'oldEmail' => 'placeholder',
                    'newEmail' => $realEmail,
                ]);
            } else {
                $this->logger->warning('Cannot update placeholder email: email already used by another user', [
                    'userId' => $user->getId(),
                    'email' => $realEmail,
                    'existingUserId' => $existing->getId(),
                ]);
            }
        }

        // Update name if available and user has no name
        if (!$user->getFirstName() && $contact->getPrenom()) {
            $user->setFirstName($this->truncateString($contact->getPrenom(), 45));
        }
        if (!$user->getLastName() && $contact->getNom()) {
            $user->setLastName($this->truncateString($contact->getNom(), 45));
        }

        // Update phone if not set
        $phone = $contact->getTelephone() ?? $contact->getTelephoneMobile();
        if (!$user->getPhone() && $phone) {
            $user->setPhone($phone);
        }

        // Update birth date if not set
        if (!$user->getBirthday() && $contact->getDateNaissance()) {
            $user->setBirthday($contact->getDateNaissance());
        }

        // Update address if not set
        if (!$user->getAddress() && ($address = $contact->getAdresse())) {
            $user->setAddress($address->getVoie());
            $user->setCity($address->getLocalite());
            $user->setPostalCode($address->getCodePostal());
            $user->setCountry($address->getCodePays() ?? 'FR');
        }
    }

    /**
     * Checks if an email is a placeholder generated during O2S sync.
     */
    private function isPlaceholderEmail(?string $email): bool
    {
        if (!$email) {
            return true;
        }
        return str_contains($email, '@placeholder.local');
    }

    /**
     * Creates a new ProductAccount from O2S compte data.
     */
    private function createProductAccountFromCompte(User $user, CompteDTO $compte): ProductAccount
    {
        $account = new ProductAccount();
        $account->setUser($user);
        $account->setO2sCompteId($compte->getId());

        $this->updateProductAccountFromCompte($account, $compte);

        return $account;
    }

    /**
     * Updates a ProductAccount from O2S compte data.
     * Uses CompteDTO montant directly (fast, no extra API call).
     */
    private function updateProductAccountFromCompte(ProductAccount $account, CompteDTO $compte): void
    {
        $account->setInternalName($compte->getDisplayName());
        $account->setDisplayAlias($compte->getLibelle());

        // Resolve actual institution label (instead of generic "O2S - Harvest")
        $distributorLabel = $this->resolveInstitutionLabel($compte);
        $account->setDistributor($distributorLabel ?? 'O2S - Harvest');
        
        // Resolve product type from Products API (cached) or libelle fallback
        $productType = $this->resolveProductType($compte);
        $account->setProductType($productType);

        // Use CompteDTO montant for valuation (fast)
        if ($compte->getMontant() !== null) {
            $account->setO2sValuation((string) $compte->getMontant());
            if ($compte->getDateValeur()) {
                $account->setO2sValuationDate($compte->getDateValeur());
            }
        }

        // Update initial amount if not set
        if ($account->getInitialAmount() === '0.00' && $compte->getMontant() !== null) {
            $account->setInitialAmount((string) $compte->getMontant());
        }

        // Update fiscal date if we have opening date
        if ($compte->getDateOuverture()) {
            $account->setFiscalDate($compte->getDateOuverture());
        }

        $account->setO2sSyncedAt(new \DateTimeImmutable());
    }

    /**
     * Truncates a string to a maximum length.
     */
    private function truncateString(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }
        return mb_substr($value, 0, $maxLength);
    }

    /**
     * Resolves product type from O2S Products API (cached) or libelle parsing.
     */
    private function resolveProductType(CompteDTO $compte): string
    {
        $this->ensureProductsMapLoaded();
        
        // 1. Try to resolve from Products API using produitId
        $produitId = $compte->getProduitId();
        if ($produitId && isset($this->productsMap[$produitId])) {
            $product = $this->productsMap[$produitId];
            $apiType = $product->getType();
            if ($apiType) {
                return $this->mapProductType($apiType);
            }
        }
        
        // 2. Try modeleFinancier if present (some API versions)
        $modeleFinancier = $compte->getModeleFinancier();
        if ($modeleFinancier) {
            return $this->mapProductType($modeleFinancier);
        }
        
        // 3. Fallback: parse product type from libelle
        $libelle = strtoupper($compte->getLibelle() ?? '');
        return $this->parseTypeFromLibelle($libelle);
    }

    /**
     * Maps O2S product type (from Products API) to local product type.
     * Covers all 47 types known from the O2S Products API.
     */
    private function mapProductType(string $o2sType): string
    {
        return match (strtoupper($o2sType)) {
            // Assurance vie
            'ASSURANCE_UC', 'ASSURANCE_EURO', 'ASSURANCE_EURO_CROISSANCE', 'ASSURANCE_BONUS_FIDELITE' => 'ASSURANCE_VIE',
            'ASSURANCE_VIE', 'ASSVIE' => 'ASSURANCE_VIE',
            
            // Capitalisation
            'BON_CAPI_UC', 'BON_CAPI_EURO' => 'CAPITALISATION',
            
            // PEA
            'PEA', 'PEA_NUMERAIRE' => 'PEA',
            'PEA_PME', 'PEA-PME', 'PEA_PME_NUMERAIRE' => 'PEA_PME',
            
            // PER / Retraite
            'PER', 'PER_UC', 'PERIN_ASSURANTIEL', 'PERIN_COMPTE_TITRES' => 'PER',
            'PERP' => 'PERP',
            'PERCO' => 'PERCO',
            'ARTICLE_83' => 'ARTICLE_83',
            'ARTICLE_82' => 'ARTICLE_82',
            'MADELIN', 'MADELIN_UC' => 'MADELIN',
            'RETRAITE_ENTREPRISE' => 'RETRAITE_ENTREPRISE',
            
            // Épargne salariale
            'PEE', 'PEI', 'EPARGNE_SALARIALE' => 'EPARGNE_SALARIALE',
            
            // Comptes titres
            'COMPTE_TITRE', 'COMPTE_TITRES', 'CTO', 'COMPTE_EN_NOMINATIF' => 'COMPTE_TITRES',
            
            // Épargne bancaire
            'LIVRET', 'LIVRET_A', 'LIVRET_JEUNE', 'LIVRET_DEV_DURABLE', 'LDDS', 'LEP' => 'LIVRET',
            'CEL', 'PEL' => 'EPARGNE_LOGEMENT',
            'PEP_UC', 'PEP_BANCAIRE' => 'PEP',
            'COMPTE_COURANT' => 'COMPTE_COURANT',
            'COMPTE_A_TERME' => 'COMPTE_A_TERME',
            'CPT_ESPECES' => 'COMPTE_COURANT',
            
            // Immobilier / Pierre
            'SCPI', 'PART_SCI_GIRARDIN' => 'SCPI',
            'PART_SNC_GIRARDIN', 'PART_GF' => 'DEFISCALISATION',
            'COMPTE_DEFISCALISATION' => 'DEFISCALISATION',
            
            // Prévoyance / Assurance
            'PREVOYANCE' => 'PREVOYANCE',
            'IARD' => 'IARD',
            'SANTE' => 'SANTE',
            'COMPTE_EMPRUNTEUR' => 'EMPRUNTEUR',
            
            // Divers
            'TITRE_STE_NON_COTEE' => 'NON_COTE',
            'AUTRE_PLACEMENT' => 'AUTRE',
            'IFC' => 'AUTRE',
            'SOCIETE_EN_PARTICIPATION' => 'AUTRE',
            'TCN' => 'AUTRE',
            'CAVE_PATRIMONIALE' => 'AUTRE',
            'OBJET_ART' => 'AUTRE',
            'TONTINE' => 'AUTRE',
            
            default => 'AUTRE',
        };
    }

    /**
     * Parses product type from the compte libelle as a last resort.
     */
    private function parseTypeFromLibelle(string $libelle): string
    {
        // Order matters: check more specific patterns first
        $patterns = [
            '/\bPEA[\s_-]PME\b/' => 'PEA_PME',
            '/\bASSURANCE[\s_-]VIE\b/' => 'ASSURANCE_VIE',
            '/\bASS(?:URANCE)?[\s_-]?VIE\b/' => 'ASSURANCE_VIE',
            '/\bPERIN\b/' => 'PER',
            '/\bPERI\b/' => 'PER',
            '/\bPERCO\b/' => 'PERCO',
            '/\bPERP\b/' => 'PERP',
            '/\bPER\b/' => 'PER',
            '/\bMADELIN\b/' => 'MADELIN',
            '/\bARTICLE[\s_]?83\b/' => 'ARTICLE_83',
            '/\bPEI\b/' => 'PEI',
            '/\bPEA\b/' => 'PEA',
            '/\bCOMPTE[\s_-]TITRE[S]?\b/' => 'COMPTE_TITRES',
            '/\bCTO\b/' => 'COMPTE_TITRES',
            '/\bSCPI\b/' => 'SCPI',
            '/\bLIVRET\b/' => 'LIVRET',
            '/\bLDDS\b/' => 'LIVRET',
            '/\bCEL\b/' => 'EPARGNE_LOGEMENT',
            '/\bPEL\b/' => 'EPARGNE_LOGEMENT',
            '/\bCAPITALISATION\b/' => 'CAPITALISATION',
            '/\bRETRAITE\b/' => 'PER',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $libelle)) {
                return $type;
            }
        }

        return 'AUTRE';
    }
}

