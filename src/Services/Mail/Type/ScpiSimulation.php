<?php

namespace App\Services\Mail\Type;

trait ScpiSimulation
{
    public function scpiSimulation(array $data): bool
    {
        $templateHtml = $this->templating->render('emails/scpi/simulation.html.twig', $data);
        $templateText = $this->templating->render('emails/scpi/simulation.txt.twig', $data);

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
