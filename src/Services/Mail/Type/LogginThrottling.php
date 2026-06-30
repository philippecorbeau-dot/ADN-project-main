<?php

namespace App\Services\Mail\Type;

/**
 * Sends mail from App\EventListener\Security\AuthenticationListener
 */
trait LogginThrottling
{
    public function logginThrottling(array $data): bool
    {
        $templateHtml = $this->templating->render('emails/loggin-throttling/report.html.twig', $data);
        $templateText = $this->templating->render('emails/loggin-throttling/report.txt.twig', $data);

        return $this->send(
            $data['subject'],
            $this->getAdminTeamAddress(),
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
