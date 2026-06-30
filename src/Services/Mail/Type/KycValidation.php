<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;

/**
 * Sends mail about the validation of KYC's for a user
 */
trait KycValidation
{
    public function kycValidationAsked(User $user, array $attachedDocs)
    {
        // $this->kycValidationAskedAdmin($user, $attachedDocs);
    }

    /*
    private function kycValidationAskedAdmin(User $user, array $attachedDocs): bool
    {
        $subject = '%firstname% %lastname% souhaite valider son identité';
        $subject = str_replace(
            ['%firstname%', '%lastname%'],
            [$user->getFirstName(), $user->getLastName()],
            $subject
        );
        $templateHtml = $this->templating->render('emails/kyc/admin-validation.html.twig', ['user' => $user]);
        $templateText = $this->templating->render('emails/kyc/admin-validation.txt.twig', ['user' => $user]);

        return $this->send(
            $subject,
            $this->getAdminTeamAddress(),
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ],
            $attachedDocs
        );
    }*/
}
