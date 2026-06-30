<?php

namespace App\Services\Mail\Type;

/**
 * Sends a mail to admin(s) when a user kyc has been rejected
 */
trait AdminUserIdentityRefused
{
    public function adminUserIdentityRefused(array $data): bool
    {
        $subject = str_replace(['%firstName%', '%lastName%'],
            [$data['user']->getFirstName(), $data['user']->getLastName()],
            "L'identité de %firstName% %lastName% a été refusé"
        );
        $templateHtml = $this->templating->render('emails/user-identity-refused/admin.html.twig', $data);
        $templateText = $this->templating->render('emails/user-identity-refused/admin.txt.twig', $data);

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
