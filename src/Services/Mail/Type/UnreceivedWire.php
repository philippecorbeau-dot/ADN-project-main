<?php

namespace App\Services\Mail\Type;

/**
 * Sends mail from App\Command\Investment\CheckUnreceivedWireCommand
 * Sends a mail to the user when bankwire payment has not been done
 */
trait UnreceivedWire
{
    public function unreceivedWire(string $to, array $data): bool
    {
        $subject = "Vous n'avez pas encore finalisé votre investissement dans ".$data['project']->getName();
        $templateHtml = $this->templating->render('emails/unreceived-wire/info.html.twig', $data);
        $templateText = $this->templating->render('emails/unreceived-wire/info.txt.twig', $data);

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
