<?php

namespace App\Services\Mail\Type;

use App\Entity\User\BankAccount;
use App\Entity\User\User;

/**
 * RIB has been validated
 */
trait RibValidated
{
    public function ribValidated(BankAccount $bankAccount): bool
    {
        $subject = "Votre RIB a été validé";
        $templateHtml = $this->templating->render('emails/user/rib_validated.html.twig', ['bankAccount' => $bankAccount, 'user' => $bankAccount->getUser()]);
        $templateText = $this->templating->render('emails/user/rib_validated.txt.twig', ['bankAccount' => $bankAccount, 'user' => $bankAccount->getUser()]);

        return $this->send(
            $subject,
            [$bankAccount->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }

    public function ribRefused(BankAccount $bankAccount): bool
    {
        $subject = "Vos coordonnées bancaires n'ont pu être validées";
        $templateHtml = $this->templating->render('emails/user/rib_refused.html.twig', ['bankAccount' => $bankAccount, 'user' => $bankAccount->getUser()]);
        $templateText = $this->templating->render('emails/user/rib_refused.txt.twig', ['bankAccount' => $bankAccount, 'user' => $bankAccount->getUser()]);

        return $this->send(
            $subject,
            [$bankAccount->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
