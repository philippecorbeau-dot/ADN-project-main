<?php

namespace App\Services\Mail\Type;

/**
 * Sends a mail to a user if kyc is not accepted
 */
trait UserIdentityRefused
{
    public function userIdentityRefused(array $data): bool
    {
        $subject = "Votre identité n'a pu être validé";
        $templateHtml = $this->templating->render('emails/user-identity-refused/user.html.twig', $data);
        $templateText = $this->templating->render('emails/user-identity-refused/user.txt.twig', $data);

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
