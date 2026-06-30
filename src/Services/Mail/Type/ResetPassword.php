<?php

namespace App\Services\Mail\Type;

/**
 * Reset password mailer for users
 */
trait ResetPassword
{
    public function resetPassword(string $to, array $data): bool
    {
        $subject = "Demande de réinitialisation du mot de passe";
        $templateHtml = $this->templating->render('emails/user/reset-password.html.twig', $data);
        $templateText = $this->templating->render('emails/user/reset-password.txt.twig', $data);

        return $this->send(
            $subject,
            [$to],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
