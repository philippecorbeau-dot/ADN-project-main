<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;

/**
 * Sends mail from App\Command\User\RetargetNonValidatedUserCommand
 * Reports the statuses of KYC's
 */
trait OldUser
{
    public function oldUser(array $data): bool
    {
        $templateHtml = $this->templating->render('emails/old-user/retarget.html.twig', $data);
        $templateText = $this->templating->render('emails/old-user/retarget.txt.twig', $data);

        return $this->send(
            $data['subject'],
            [$data['user']->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
