<?php

namespace App\Services\Mail\Type;

use App\Entity\LifeInsurance\Investment;

trait LifeInsurance
{
    public function reminderEmail(Investment $investment, array $arrayAttachedDocuments): bool
    {
        $templateHtml = $this->templating->render('emails/lifeinsurance/reminder-email.html.twig', ['investment' => $investment]);
        $templateText = $this->templating->render('emails/lifeinsurance/reminder-email.txt.twig', ['investment' => $investment]);

        return $this->send(
            'Assurance-vie Homunity Vie',
            [$investment->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ],
            $arrayAttachedDocuments
        );
    }

    public function fileBeingValidated(Investment $investment, array $arrayAttachedDocuments): bool
    {
        $templateHtml = $this->templating->render('emails/lifeinsurance/file-being-validated.html.twig', ['investment' => $investment]);
        $templateText = $this->templating->render('emails/lifeinsurance/file-being-validated.txt.twig', ['investment' => $investment]);

        return $this->send(
            'Votre souscription est en cours de validation.',
            [$investment->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ],
            $arrayAttachedDocuments
        );
    }

    public function fileValidatedHomunity(Investment $investment): bool
    {
        $templateHtml = $this->templating->render('emails/lifeinsurance/file-validated-by-homunity.html.twig', ['investment' => $investment]);
        $templateText = $this->templating->render('emails/lifeinsurance/file-validated-by-homunity.txt.twig', ['investment' => $investment]);

        return $this->send(
            'Votre souscription est en cours de validation.',
            $investment->getUser()->getEmail(),
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }

    public function fileValidatedPartner(Investment $investment): bool
    {
        $templateHtml = $this->templating->render('emails/lifeinsurance/file-validated-by-parnter.html.twig', $investment);
        $templateText = $this->templating->render('emails/lifeinsurance/file-validated-by-parnter.txt.twig', $investment);

        return $this->send(
            'Votre souscription est validée.',
            self::ADMIN_LIFEINSURANCE_TEAM_ADDRESS,
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }

    public function manageProfilesUpdateAlert(): bool
    {
        $templateHtml = $this->templating->render('emails/lifeinsurance/profiles-update-alert.html.twig');
        $templateText = $this->templating->render('emails/lifeinsurance/profiles-update-alert.txt.twig');

        return $this->send(
            'IMPORTANT : les unités de compte de l\'Assurance Vie ont été modifiées.',
            self::ADMIN_LIFEINSURANCE_TEAM_ADDRESS,
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
