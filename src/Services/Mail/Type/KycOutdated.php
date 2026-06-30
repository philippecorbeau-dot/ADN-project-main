<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;

/**
 * Investment has been signed
 */
trait KycOutdated
{
    public function KycOutdated(array $data): bool
    {
        $subject = 'Une mise à jour réglementaire de votre compte est nécessaire !';
        $templateHtml = $this->templating->render('emails/user/kyc-outdated.html.twig', ['data' => $data]);
        $templateText = $this->templating->render('emails/user/kyc-outdated.txt.twig', ['data' => $data]);

        return $this->send(
            $subject,
            [$data['user']->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
