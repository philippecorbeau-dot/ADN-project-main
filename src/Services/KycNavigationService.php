<?php

namespace App\Services;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;

class KycNavigationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Détermine les étapes accessibles pour un utilisateur
     * Si l'utilisateur était à l'étape 5 et qu'il est redescendu, on lui permet de refaire le parcours
     */
    public function getAccessibleSteps(User $user): array
    {
        $currentStep = $user->getStepKyc() ?? 0;
        $accessibleSteps = [];

        // Si l'utilisateur était à l'étape 5 et qu'il est redescendu, on lui permet de refaire le parcours
        $canRestartKyc = $this->canRestartKyc($user);

        // Étape 1 : Toujours accessible
        $accessibleSteps[1] = [
            'accessible' => true,
            'completed' => $currentStep >= 1,
            'current' => $currentStep === 1,
            'url' => $this->generateStepUrl(1),
            'canRestart' => $canRestartKyc
        ];

        // Étape 2 : Accessible si étape 1 complétée OU si on peut refaire le parcours
        $accessibleSteps[2] = [
            'accessible' => $currentStep >= 1 || $canRestartKyc,
            'completed' => $currentStep >= 2,
            'current' => $currentStep === 2,
            'url' => $this->generateStepUrl(2),
            'canRestart' => $canRestartKyc
        ];

        // Étape 3 : Accessible si étape 2 complétée OU si on peut refaire le parcours
        $accessibleSteps[3] = [
            'accessible' => $currentStep >= 2 || $canRestartKyc,
            'completed' => $currentStep >= 3,
            'current' => $currentStep === 3,
            'url' => $this->generateStepUrl(3),
            'canRestart' => $canRestartKyc
        ];

        // Étape 4 : Accessible si étape 3 complétée OU si on peut refaire le parcours
        $accessibleSteps[4] = [
            'accessible' => $currentStep >= 3 || $canRestartKyc,
            'completed' => $currentStep >= 4,
            'current' => $currentStep === 4,
            'url' => $this->generateStepUrl(4),
            'canRestart' => $canRestartKyc
        ];

        // Étape 5 : Accessible si étape 4 complétée OU si on peut refaire le parcours
        $accessibleSteps[5] = [
            'accessible' => $currentStep >= 4 || $canRestartKyc,
            'completed' => $currentStep >= 5,
            'current' => $currentStep === 5,
            'url' => $this->generateStepUrl(5),
            'canRestart' => $canRestartKyc
        ];

        return $accessibleSteps;
    }

    /**
     * Vérifie si l'utilisateur peut refaire le parcours KYC
     * (était à l'étape 5 et a été redescendu)
     */
    public function canRestartKyc(User $user): bool
    {
        // Autoriser la relance dès lors que l'utilisateur a déjà engagé un KYC
        // - soit il a déjà des documents (il a donc atteint l'étape 5 au moins une fois)
        // - soit il a au moins commencé le parcours (stepKyc >= 1)
        $kycDocuments = $user->getKycDocuments();
        $hasDocuments = method_exists($kycDocuments, 'count') ? $kycDocuments->count() > 0 : !empty($kycDocuments);
        $currentStep = $user->getStepKyc() ?? 0;

        return $hasDocuments || $currentStep >= 1;
    }

    /**
     * Vérifie si une étape est accessible
     */
    public function isStepAccessible(User $user, int $step): bool
    {
        $accessibleSteps = $this->getAccessibleSteps($user);
        return isset($accessibleSteps[$step]) && $accessibleSteps[$step]['accessible'];
    }

    /**
     * Génère l'URL d'une étape
     */
    private function generateStepUrl(int $step): string
    {
        return "/register/kyc/step/{$step}";
    }

    /**
     * Sauvegarde les données du formulaire avant navigation
     */
    public function saveFormData(User $user, array $formData): void
    {
        // Sauvegarder les données dans la session ou en base
        // Cette méthode peut être étendue selon les besoins
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Valide qu'un utilisateur peut naviguer vers une étape
     */
    public function validateNavigation(User $user, int $targetStep): bool
    {
        $currentStep = $user->getStepKyc() ?? 0;
        
        // Si on peut refaire le parcours, on autorise la navigation
        if ($this->canRestartKyc($user)) {
            return $targetStep >= 1 && $targetStep <= 5;
        }
        
        // On ne peut aller qu'aux étapes déjà accessibles
        if (!$this->isStepAccessible($user, $targetStep)) {
            return false;
        }

        // On ne peut pas sauter d'étapes
        if ($targetStep > $currentStep + 1) {
            return false;
        }

        return true;
    }

    /**
     * Obtient la prochaine étape accessible
     */
    public function getNextStep(User $user): ?int
    {
        $currentStep = $user->getStepKyc() ?? 0;
        $nextStep = $currentStep + 1;
        
        if ($nextStep <= 5 && $this->isStepAccessible($user, $nextStep)) {
            return $nextStep;
        }
        
        return null;
    }

    /**
     * Obtient l'étape précédente
     */
    public function getPreviousStep(User $user): ?int
    {
        $currentStep = $user->getStepKyc() ?? 1;
        $previousStep = $currentStep - 1;
        
        if ($previousStep >= 1) {
            return $previousStep;
        }
        
        return null;
    }

    /**
     * Obtient la première étape accessible pour la reprise du parcours
     */
    public function getFirstAccessibleStep(User $user): int
    {
        if ($this->canRestartKyc($user)) {
            return 1; // Commencer par l'étape 1 si on peut refaire le parcours
        }
        
        $currentStep = $user->getStepKyc() ?? 0;
        return max(1, $currentStep);
    }

    /**
     * Vérifie si l'utilisateur doit refaire le parcours KYC
     */
    public function shouldRestartKyc(User $user): bool
    {
        return $this->canRestartKyc($user);
    }

    /**
     * Obtient le message d'information pour la reprise du parcours
     */
    public function getRestartMessage(User $user): ?string
    {
        if (!$this->canRestartKyc($user)) {
            return null;
        }

        return "Votre profil KYC a été mis à jour. Vous devez refaire le parcours de validation pour mettre à jour vos documents.";
    }
} 