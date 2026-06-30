<?php

namespace App\Services\Mail\Type;

/**
 * Sends a mail to the user when with security code depending on the entity
 */
trait TwoFactorAuthentication
{
    public function twoFactorAuthentication(string $to, array $data): bool
    {
        $data['base64EncodedId'] = base64_encode($data['user']->getId());

        return $this->send(
            $data['subject'],
            [$to],
            [
                'html' => $this->templating->render('emails/validation/twoFactor.html.twig', $data),
                'text' => $this->templating->render('emails/validation/twoFactor.txt.twig', $data),
            ]
        );
    }
}
