<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;

/**
 * Sends mail from App\Command\User\RetargetNonValidatedUserCommand
 * Reports the statuses of KYC's
 */
trait NonValidatedUser
{
    public function nonValidatedUserRetarget(User $user): bool
    {
        $subject = $user->getFirstName()." vous n'avez pas encore validé votre compte Homunity";
        $templateHtml = $this->templating->render('emails/non-validated-user/report.html.twig', ['user' => $user]);
        $templateText = $this->templating->render('emails/non-validated-user/report.txt.twig', ['user' => $user]);

        return $this->send(
            $subject,
            [$user->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
