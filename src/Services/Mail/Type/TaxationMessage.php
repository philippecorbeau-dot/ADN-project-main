<?php

namespace App\Services\Mail\Type;



use App\Entity\User\Taxation;

trait TaxationMessage
{
    public function refusedStatus(Taxation $taxation, array $additionalMessage = null): bool
    {
        $subject = "Nous n’avons pas pu valider votre déclaration fiscale";

        $data = [
            'user' => $taxation->getUser(),
            'year' => $taxation->getYear(),
        ];
        if(!empty($additionalMessage)) {
            $data['message'] = $additionalMessage['message'];
        }

        $templateHtml = $this->templating->render('emails/user-taxation/refused.html.twig', $data);
        $templateText = $this->templating->render('emails/user-taxation/refused.txt.twig', $data);

        return $this->send(
            $subject,
            [$data['user']->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
