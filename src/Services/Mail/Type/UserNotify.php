<?php

namespace App\Services\Mail\Type;

trait UserNotify
{
    public function filledWallets(array $user): bool
    {
        $subject = "Il vous reste " . number_format($user['balance'], 0, ',', ' ') . " € sur votre portefeuille Homunity !";
        
        $data = [
            'user' => $user,
        ];
        
        $templateHtml = $this->templating->render('emails/configuration/filled-wallet.html.twig', $data);
        $templateText = $this->templating->render('emails/configuration/filled-wallet.txt.twig', $data);

        return $this->send(
            $subject,
            [$user['email']],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
