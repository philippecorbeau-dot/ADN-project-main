<?php

namespace App\Services\Mail\Type;

/**
 * Sends mail from App\Command\Investment\CheckBankwireCommand
 * Sends a mail to the user when bankwire payment has been received
 */
trait BankwireSuccess
{
    public function bankwireSuccess(string $to, array $data): bool
    {
        $subject = 'Nous avons bien reçus vos fonds';
        $templateHtml = $this->templating->render('emails/bankwire/success.html.twig', $data);
        $templateText = $this->templating->render('emails/bankwire/success.txt.twig', $data);

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
