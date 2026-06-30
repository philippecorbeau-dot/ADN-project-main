<?php

namespace App\Services\Mail\Type;

/**
 * Sends a mail to admin(s) when a user has downloaded a synthetic file
 */
trait AdminUserWant
{
    public function adminUserWant(array $data): bool
    {
        $subject = 'Un utilisateur a téléchargé le dossier Synthétique';
        $templateHtml = $this->templating->render('emails/user-want/notice.html.twig', $data);
        $templateText = $this->templating->render('emails/user-want/notice.txt.twig', $data);

        return $this->send(
            $subject,
            $this->getAdminTeamAddress(),
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
