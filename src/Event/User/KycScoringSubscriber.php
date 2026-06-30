<?php

namespace App\Event\User;

use App\Entity\User\User;
use App\Services\User\InvestorProfileScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class KycScoringSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private InvestorProfileScorer $profileScorer
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser() instanceof User) {
            return;
        }

        /** @var User $user */
        $user = $token->getUser();

        // Vérifier si c'est une requête KYC et si le profil doit être recalculé
        if ($this->shouldRecalculateProfile($request, $user)) {
            $this->recalculateProfile($user);
        }
    }

    private function shouldRecalculateProfile($request, User $user): bool
    {
        $path = $request->getPathInfo();
        
        // Vérifier si c'est une requête KYC
        if (!str_contains($path, '/register/kyc/step/')) {
            return false;
        }

        // Vérifier si c'est une soumission de formulaire
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        // Vérifier si l'utilisateur a des données KYC
        if (!$user->getInfo()) {
            return false;
        }

        // Recalculer à chaque soumission KYC pour refléter immédiatement les changements
        return true;
    }

    private function recalculateProfile(User $user): void
    {
        try {
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas interrompre le processus
            error_log('Erreur lors du recalcul du profil investisseur: ' . $e->getMessage());
        }
    }
} 