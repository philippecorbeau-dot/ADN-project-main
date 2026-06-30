<?php

namespace App\Services\Mail\Type;

use App\Entity\User\KycDocument;
use App\Entity\User\User;

/**
 * Sends email notifications about KYC document status changes
 */
trait KycDocumentNotification
{
    /**
     * Send notification when a document is validated
     */
    public function kycDocumentValidated(User $user, KycDocument $document): bool
    {
        $sendTo = [$user->getEmail()];
        $subject = $this->translator->trans('Votre document a été validé');
        
        $templateHtml = $this->templating->render('emails/kyc/document_validated.html.twig', [
            'user' => $user,
            'documentType' => $document->getTypeName(),
            'validatedAt' => $document->getUpdatedAt() ?? new \DateTime(),
        ]);

        return $this->send(
            $subject,
            $sendTo,
            [
                'html' => $templateHtml,
                'text' => strip_tags($templateHtml),
            ]
        );
    }

    /**
     * Send notification when a document is refused
     */
    public function kycDocumentRefused(User $user, KycDocument $document, string $refusalReason): bool
    {
        $sendTo = [$user->getEmail()];
        $subject = $this->translator->trans('Votre document nécessite une correction');
        
        // Traduire la raison de refus si c'est une clé connue
        $translatedReason = $this->getRefusalReasonLabel($refusalReason);
        
        $templateHtml = $this->templating->render('emails/kyc/document_refused.html.twig', [
            'user' => $user,
            'documentType' => $document->getTypeName(),
            'refusalReason' => $translatedReason,
        ]);

        return $this->send(
            $subject,
            $sendTo,
            [
                'html' => $templateHtml,
                'text' => strip_tags($templateHtml),
            ]
        );
    }

    /**
     * Send notification when a document has expired
     */
    public function kycDocumentExpired(User $user, KycDocument $document): bool
    {
        $sendTo = [$user->getEmail()];
        $subject = $this->translator->trans('Votre document a expiré');
        
        $templateHtml = $this->templating->render('emails/kyc/document_expired.html.twig', [
            'user' => $user,
            'documentType' => $document->getTypeName(),
            'expirationDate' => $document->getExpirationDate() ?? new \DateTime(),
        ]);

        return $this->send(
            $subject,
            $sendTo,
            [
                'html' => $templateHtml,
                'text' => strip_tags($templateHtml),
            ]
        );
    }

    /**
     * Get human-readable label for refusal reason
     */
    private function getRefusalReasonLabel(string $reason): string
    {
        // Utiliser la liste officielle de l'entité KycDocument
        $labels = KycDocument::REFUSED_REASON_MESSAGE_LIST;
        
        return $labels[$reason] ?? $reason;
    }
}

