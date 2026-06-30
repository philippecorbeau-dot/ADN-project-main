<?php

namespace App\Services\Mail\Type;

trait CallForFunds
{
    public function callForFunds(array $data): bool
    {
        $investment = $data['investment'];
        $templateHtml = $this->templating->render('emails/investment/call_for_funds.html.twig', $data);
        $templateText = $this->templating->render('emails/investment/call_for_funds.txt.twig', $data);

        return $this->send(
            'Rappel pour virement',
            [$investment->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ],
            [],
            $data
        );
    }
}
