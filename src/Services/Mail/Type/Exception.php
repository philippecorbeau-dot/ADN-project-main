<?php

namespace App\Services\Mail\Type;


/**
 * Sends mail from App\Command\User\KycStatusCommand
 * Reports the statuses of KYC's
 */
trait Exception
{
    public function exception($data): bool
    {
        $subject = "Une erreur est survenue";
        $templateHtml = $this->templating->render('emails/exception.html.twig', ['data' => $data]);
        $templateText = $this->templating->render('emails/exception.txt.twig', ['data' => $data]);

        return $this->send(
            $subject,
            self::ADMIN_ADDRESS,
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
