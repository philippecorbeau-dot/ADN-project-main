<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;
use App\Entity\Project\Project;

/**
 * Investment has been signed
 */
trait InvestmentSigned
{
    public function investmentSigned(User $user, Project $project): bool
    {
        $subject = "Votre document est signé";
        $templateHtml = $this->templating->render('emails/investment/signed.html.twig', ['project' => $project, 'user' => $user]);
        $templateText = $this->templating->render('emails/investment/signed.txt.twig', ['project' => $project, 'user' => $user]);

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
