<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;
use App\Entity\Project\Project;

/**
 * Sends mail from App\Command\User\KycStatusCommand
 * Reports the statuses of KYC's
 */
trait InvestmentRequest
{
    public function investmentRequest(User $user, Project $project): bool
    {
        $subject = "Demande d’investissement pour %projectName%";
        $subject = str_replace('%projectName%', $project->getName(), $subject);
        $templateHtml = $this->templating->render('emails/investment/request.html.twig', ['project' => $project, 'user' => $user]);
        $templateText = $this->templating->render('emails/investment/request.txt.twig', ['project' => $project, 'user' => $user]);

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
