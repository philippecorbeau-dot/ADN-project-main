<?php

namespace App\Services\Mail\Type;

/**
 * Sends a mail to a user when his KYC are validated
 */
trait UserIdentified
{
    public function userIdentitied(array $user): bool
    {
        $subject = "Votre profil est identifié !";
        $templateHtml = $this->templating->render('emails/user-identified/info.html.twig', $user);
        $templateText = $this->templating->render('emails/user-identified/info.txt.twig', $user);

        return $this->send(
            $subject,
            [$user['user']->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
