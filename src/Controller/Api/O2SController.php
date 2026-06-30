<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User\User;
use App\Integration\O2S\O2SManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Controller for O2S data operations.
 * 
 * Provides endpoints for fetching and syncing O2S data for the logged-in user.
 */
#[Route('/api/o2s', name: 'api_o2s_')]
#[IsGranted('ROLE_USER')]
class O2SController extends AbstractController
{
    public function __construct(
        private readonly O2SManager $o2sManager,
    ) {
    }

    /**
     * Get O2S connection status.
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'configured' => $this->o2sManager->isConfigured(),
            'user_linked' => $user->isLinkedToO2S(),
            'o2s_contact_id' => $user->getO2sContactId(),
            'last_sync' => $user->getO2sSyncedAt()?->format(\DateTimeInterface::ATOM),
            'needs_refresh' => $user->needsO2SRefresh(),
        ]);
    }

    /**
     * Get portfolio summary from O2S.
     */
    #[Route('/portfolio', name: 'portfolio', methods: ['GET'])]
    public function portfolio(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isLinkedToO2S()) {
            return $this->json([
                'error' => 'User is not linked to O2S',
                'linked' => false,
            ], 400);
        }

        try {
            $summary = $this->o2sManager->getPortfolioSummary($user);

            return $this->json([
                'success' => true,
                'data' => [
                    'total_valuation' => $summary['total_valuation'],
                    'compte_count' => $summary['compte_count'],
                    'by_type' => $summary['by_type'],
                    'currency' => 'EUR',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to fetch portfolio data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed comptes from O2S.
     */
    #[Route('/comptes', name: 'comptes', methods: ['GET'])]
    public function comptes(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isLinkedToO2S()) {
            return $this->json([
                'error' => 'User is not linked to O2S',
                'linked' => false,
            ], 400);
        }

        try {
            $comptes = $this->o2sManager->getComptesForUser($user);

            $data = array_map(function ($compte) {
                return [
                    'id' => $compte->getId(),
                    'name' => $compte->getDisplayName(),
                    'type' => $compte->getProductType(),
                    'numero' => $compte->getNumero(),
                    'statut' => $compte->getStatut(),
                    'is_active' => $compte->isActif(),
                    'valuation' => [
                        'amount' => $compte->getMontant(),
                        'currency' => $compte->getDevise() ?? 'EUR',
                        'date' => $compte->getDateValeur()?->format('Y-m-d'),
                    ],
                    'dates' => [
                        'opened' => $compte->getDateOuverture()?->format('Y-m-d'),
                        'term' => $compte->getDateTerme()?->format('Y-m-d'),
                    ],
                ];
            }, $comptes);

            return $this->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to fetch comptes',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync user data from O2S.
     */
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function sync(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            // Perform full sync
            $result = $this->o2sManager->fullSync($user);

            return $this->json([
                'success' => true,
                'linked' => $result['linked'],
                'user_synced' => $result['user_synced'],
                'products' => [
                    'success' => $result['products_result']->isSuccess(),
                    'created' => $result['products_result']->getCreated(),
                    'updated' => $result['products_result']->getUpdated(),
                    'skipped' => $result['products_result']->getSkipped(),
                    'errors' => $result['products_result']->getErrors(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Sync failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Link user to O2S contact.
     */
    #[Route('/link', name: 'link', methods: ['POST'])]
    public function link(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isLinkedToO2S()) {
            return $this->json([
                'success' => true,
                'already_linked' => true,
                'o2s_contact_id' => $user->getO2sContactId(),
            ]);
        }

        try {
            $contactId = $this->o2sManager->linkUserToO2S($user);

            if ($contactId) {
                return $this->json([
                    'success' => true,
                    'linked' => true,
                    'o2s_contact_id' => $contactId,
                ]);
            }

            return $this->json([
                'success' => false,
                'linked' => false,
                'message' => 'No matching O2S contact found for your email',
            ], 404);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Linking failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get O2S contact details for the current user.
     */
    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isLinkedToO2S()) {
            return $this->json([
                'error' => 'User is not linked to O2S',
                'linked' => false,
            ], 400);
        }

        try {
            $contact = $this->o2sManager->getContactForUser($user);

            if (!$contact) {
                return $this->json([
                    'error' => 'Contact not found in O2S',
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $contact->getId(),
                    'civilite' => $contact->getCivilite(),
                    'nom' => $contact->getNom(),
                    'prenom' => $contact->getPrenom(),
                    'full_name' => $contact->getFullName(),
                    'email' => $contact->getEmail(),
                    'telephone' => $contact->getTelephone(),
                    'mobile' => $contact->getTelephoneMobile(),
                    'date_naissance' => $contact->getDateNaissance()?->format('Y-m-d'),
                    'profession' => $contact->getProfession(),
                    'adresse' => $contact->getAdresse()?->getFormattedAddress(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to fetch contact',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}


