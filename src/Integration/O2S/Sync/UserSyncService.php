<?php

declare(strict_types=1);

namespace App\Integration\O2S\Sync;

use App\Entity\User\User;
use App\Integration\O2S\DTO\Contact\ContactDTO;
use App\Integration\O2S\Service\ContactServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for synchronizing O2S contacts with ADN users.
 * 
 * This service handles the mapping between O2S contacts and local User entities.
 * It can either link existing users to O2S contacts or update user data from O2S.
 */
class UserSyncService
{
    public function __construct(
        private readonly ContactServiceInterface $contactService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Links an existing ADN user to an O2S contact by email.
     */
    public function linkUserByEmail(User $user): ?string
    {
        $email = $user->getEmail();
        if (!$email) {
            return null;
        }

        $this->logger->info('Attempting to link user to O2S contact', [
            'userId' => $user->getId(),
            'email' => $email,
        ]);

        try {
            $contact = $this->contactService->findByEmail($email);

            if ($contact) {
                $user->setO2sContactId($contact->getId());
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->logger->info('User linked to O2S contact', [
                    'userId' => $user->getId(),
                    'o2sContactId' => $contact->getId(),
                ]);

                return $contact->getId();
            }

            $this->logger->info('No O2S contact found for email', ['email' => $email]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to link user to O2S', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Synchronizes user data from O2S contact.
     * 
     * Updates the user entity with data from the linked O2S contact.
     */
    public function syncUserFromO2S(User $user): SyncResult
    {
        $contactId = $user->getO2sContactId();
        if (!$contactId) {
            return SyncResult::failure(['User is not linked to an O2S contact']);
        }

        $this->logger->info('Syncing user from O2S', [
            'userId' => $user->getId(),
            'o2sContactId' => $contactId,
        ]);

        try {
            $contact = $this->contactService->getContact($contactId);

            $this->updateUserFromContact($user, $contact);
            $this->entityManager->flush();

            $this->logger->info('User synced from O2S', ['userId' => $user->getId()]);

            return SyncResult::success(0, 1, 0, [
                'contactId' => $contactId,
                'lastSync' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync user from O2S', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return SyncResult::failure([$e->getMessage()]);
        }
    }

    /**
     * Fetches the O2S contact data for a user without updating.
     */
    public function getO2SContactForUser(User $user): ?ContactDTO
    {
        $contactId = $user->getO2sContactId();
        if (!$contactId) {
            return null;
        }

        try {
            return $this->contactService->getContact($contactId);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch O2S contact for user', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Batch sync: links all unlinked users to O2S by email.
     * 
     * @return SyncResult
     */
    public function batchLinkUsers(): SyncResult
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        
        // Find users without O2S contact ID
        $qb = $userRepo->createQueryBuilder('u')
            ->where('u.o2sContactId IS NULL')
            ->andWhere('u.email IS NOT NULL');
        
        $users = $qb->getQuery()->getResult();

        $linked = 0;
        $skipped = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                $contactId = $this->linkUserByEmail($user);
                if ($contactId) {
                    $linked++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = sprintf('User %d: %s', $user->getId(), $e->getMessage());
            }
        }

        $this->logger->info('Batch user linking completed', [
            'linked' => $linked,
            'skipped' => $skipped,
            'errors' => count($errors),
        ]);

        return new SyncResult(
            success: empty($errors),
            created: $linked,
            updated: 0,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * Updates user entity fields from O2S contact.
     * 
     * Note: This method is intentionally selective about which fields to update
     * to avoid overwriting user-modified data.
     */
    private function updateUserFromContact(User $user, ContactDTO $contact): void
    {
        // Update basic info if empty in ADN
        if (!$user->getFirstName() && $contact->getPrenom()) {
            $user->setFirstName($contact->getPrenom());
        }

        if (!$user->getLastName() && $contact->getNom()) {
            $user->setLastName($contact->getNom());
        }

        // Update phone if empty
        if (!$user->getPhone()) {
            $phone = $contact->getTelephoneMobile() ?? $contact->getTelephone();
            if ($phone) {
                $user->setPhone($phone);
            }
        }

        // Update address if empty
        $adresse = $contact->getAdresse();
        if ($adresse) {
            if (!$user->getAddress() && $adresse->getVoie()) {
                $user->setAddress($adresse->getVoie());
            }
            if (!$user->getPostalCode() && $adresse->getCodePostal()) {
                $user->setPostalCode($adresse->getCodePostal());
            }
            if (!$user->getCity() && $adresse->getLocalite()) {
                $user->setCity($adresse->getLocalite());
            }
            if (!$user->getCountry() && $adresse->getCodePays()) {
                $user->setCountry($adresse->getCodePays());
            }
        }

        // Always update sync timestamp
        $user->setO2sSyncedAt(new \DateTimeImmutable());
    }
}

