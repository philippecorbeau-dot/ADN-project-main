<?php

namespace App\Services;

use App\Entity\User\User;
use App\Entity\User\KycDocument;
use App\Repository\User\KycDocumentRepository;

class KycNotificationService
{
    public function __construct(
        private readonly KycDocumentRepository $kycDocumentRepository
    ) {}

    /**
     * Analyse le statut KYC de l'utilisateur et retourne les informations de notification
     */
    public function getKycNotificationStatus(User $user): array
    {
        $status = [
            'hasDocuments' => false,
            'hasPendingDocuments' => false,
            'hasValidatedDocuments' => false,
            'hasRefusedDocuments' => false,
            'allDocumentsValidated' => false,
            'message' => '',
            'type' => 'info', // info, success, warning, error
            'icon' => 'fa-info-circle',
            'color' => 'blue',
            'action' => null,
            // Détails pour un affichage plus riche côté front
            'refusedDocuments' => [], // [['type' => string, 'reason' => string, 'reasonLabel' => string]]
            'pendingDocuments' => [], // [['type' => string]]
        ];

        // Vérifier si l'utilisateur a des documents
        $kycDocs = $user->getKycDocuments();
        if (method_exists($kycDocs, 'count')) {
            $hasAnyDoc = $kycDocs->count() > 0;
        } else {
            $hasAnyDoc = !empty($kycDocs);
        }
        if (!$hasAnyDoc) {
            $status['message'] = 'Aucun document KYC soumis';
            $status['type'] = 'info';
            $status['icon'] = 'fa-upload';
            $status['color'] = 'purple';
            $status['action'] = [
                'text' => 'Soumettre mes documents',
                'url' => 'user_create_profile',
                'params' => ['step' => 5]
            ];
            return $status;
        }

        $status['hasDocuments'] = true;

        // Analyser les statuts des documents
        $pendingCount = 0;
        $validatedCount = 0;
        $refusedCount = 0;
        $totalRequiredDocs = 0;
        $validatedTypes = [];

        // Déterminer les documents requis selon le type d'utilisateur
        $requiredDocTypes = $this->getRequiredDocumentTypes($user);
        $totalRequiredDocs = count($requiredDocTypes);

        // Préparer un mapping lisible des raisons de refus
        $docHelper = new KycDocument();
        $reasonLabels = $docHelper->getRefusedReasonMessageList();
        $typeLabels = $docHelper->getTypeList();

        // Compter les documents par statut + collecter des détails
        foreach ($kycDocs as $doc) {
            switch ($doc->getStatus()) {
                case KycDocument::STATUS_PENDING:
                case KycDocument::STATUS_VALIDATION_ASKED:
                    $pendingCount++;
                    $status['hasPendingDocuments'] = true;
                    $status['pendingDocuments'][] = [
                        'id' => $doc->getId(),
                        'type' => $doc->getType(),
                    ];
                    break;
                case KycDocument::STATUS_VALIDATED:
                    // Compte une seule fois par type requis
                    if (in_array($doc->getType(), $requiredDocTypes)) {
                        $validatedTypes[$doc->getType()] = true;
                    }
                    $status['hasValidatedDocuments'] = true;
                    break;
                case KycDocument::STATUS_REFUSED:
                case KycDocument::STATUS_OUTDATED:
                    $refusedCount++;
                    $status['hasRefusedDocuments'] = true;
                    $reason = $doc->getRefusedReasonMessage();
                    $type = $doc->getType();
                    $status['refusedDocuments'][] = [
                        'id' => $doc->getId(),
                        'type' => $type,
                        'typeLabel' => isset($typeLabels[$type]) ? $typeLabels[$type] : $type,
                        'reason' => $reason,
                        'reasonLabel' => $reason && isset($reasonLabels[$reason]) ? $reasonLabels[$reason] : $reason,
                    ];
                    break;
            }
        }

        $validatedCount = count($validatedTypes);

        // Déterminer le message et le type de notification
        // PRIORITÉ: Refusé > Pending > Validé > Manquant
        if ($refusedCount > 0) {
            $status['message'] = 'Certains de vos documents ont été refusés. Veuillez les corriger et les renvoyer.';
            $status['type'] = 'warning';
            $status['icon'] = 'fa-exclamation-triangle';
            $status['color'] = 'orange';
            $status['action'] = [
                'text' => 'Corriger mes documents',
                'url' => 'user_documents',
                'params' => [],
            ];
        } elseif ($pendingCount > 0) {
            $status['message'] = 'Notre équipe a reçu vos documents et procède actuellement à leur vérification.';
            $status['type'] = 'info';
            $status['icon'] = 'fa-clock';
            $status['color'] = 'blue';
        } elseif ($validatedCount === $totalRequiredDocs && $totalRequiredDocs > 0) {
            $status['allDocumentsValidated'] = true;
            $status['message'] = 'Votre KYC a été validé par notre équipe !';
            $status['type'] = 'success';
            $status['icon'] = 'fa-check-circle';
            $status['color'] = 'green';
        } else {
            $status['message'] = 'Veuillez soumettre vos documents KYC pour continuer.';
            $status['type'] = 'info';
            $status['icon'] = 'fa-upload';
            $status['color'] = 'purple';
            $status['action'] = [
                'text' => 'Soumettre mes documents',
                'url' => 'user_create_profile',
                'params' => ['step' => 5]
            ];
        }

        return $status;
    }

    /**
     * Détermine les types de documents requis selon le type d'utilisateur
     */
    private function getRequiredDocumentTypes(User $user): array
    {
        $requiredDocTypes = [KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF];

        if ($user->isPrivate() || $user->isCgp()) {
            $requiredDocTypes[] = KycDocument::DOCUMENT_TYPE_ADDRESS_PROOF;
        } elseif ($user->isPro()) {
            $requiredDocTypes[] = KycDocument::DOCUMENT_TYPE_REGISTRATION_PROOF;
            $requiredDocTypes[] = KycDocument::DOCUMENT_TYPE_ARTICLES_OF_ASSOCIATION;
            $requiredDocTypes[] = KycDocument::DOCUMENT_TYPE_SHAREHOLDER_DECLARATION;
        }

        return $requiredDocTypes;
    }

    /**
     * Génère le message de notification personnalisé selon le statut
     */
    public function getNotificationMessage(array $status): string
    {
        return $status['message'] ?? '';
    }

    /**
     * Vérifie si l'utilisateur doit refaire le parcours KYC
     * (était à l'étape 5 et a été redescendu)
     */
    public function shouldRestartKyc(User $user): bool
    {
        // Règle stricte: l'utilisateur DOIT refaire le parcours uniquement s'il avait des documents
        // mais qu'il n'est plus à l'étape finale (5). Cela reflète une invalidation/retour arrière.
        $kycDocuments = $user->getKycDocuments();
        $hasDocuments = method_exists($kycDocuments, 'count') ? $kycDocuments->count() > 0 : !empty($kycDocuments);
        return $hasDocuments && ($user->getStepKyc() ?? 0) < 5;
    }

    /**
     * Obtient le message de notification pour la reprise du parcours KYC
     */
    public function getRestartKycNotification(User $user): ?array
    {
        if (!$this->shouldRestartKyc($user)) {
            return null;
        }

        return [
            'type' => 'warning',
            'icon' => 'fa-exclamation-triangle',
            'color' => 'yellow',
            'title' => 'Mise à jour requise',
            'message' => 'Votre profil KYC a été mis à jour. Vous devez refaire le parcours de validation pour mettre à jour vos documents.',
            'action' => [
                'text' => 'Refaire le parcours KYC',
                'url' => 'user_kyc_restart',
                'params' => []
            ]
        ];
    }
} 