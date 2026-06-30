<?php

namespace App\Services\Mail\Type;

use App\Entity\User\User;
use App\Entity\Vefa\Booking;
use App\Entity\Vefa\Housing;
use App\Entity\Vefa\Vefa;
use Symfony\Component\HttpFoundation\Request;

trait VefaContact
{
    public function vefaContactToHomunityPatrimoine(Request $request, Housing $housing, User $user = null): bool
    {
        $vefa = $housing->getVefa();
        $subject = "[Vefa - ". $vefa->getName() ."] Demande d'information";

        if(empty($user)) {
            $subject = $subject . ' (Non inscrit)';
        }

        $parameters = [
            'subject' => $subject,
            'vefa'    => $vefa,
            'form'    => $request->request->all(),
        ];

        $template = $this->twig->load('vefa/emails/contact.html.twig');
        $bodyHtml = $template->renderBlock('content', $parameters);
        $bodyText = $template->renderBlock('body_text', $parameters);

        if(!empty($user)) {
            $this->bookingService->createUpdateBooking($user, $housing, Booking::STEP_REQUESTED_INFOS);
        }

        return $this->send(
            $subject,
            self::ADMIN_VEFA_TEAM_ADDRESS,
            [
                'html' => $bodyHtml,
                'text' => $bodyText,
            ]
        );
    }

    public function vefaAskInfosToHomunityPatrimoine(Request $request): bool
    {
        $subject ="Immobilier neuf - Demande d'information";

        $parameters = [
            'subject' => $subject,
            'form'    => $request->request->all(),
        ];

        $template = $this->twig->load('vefa/emails/infos.html.twig');
        $bodyHtml = $template->renderBlock('content', $parameters);
        $bodyText = $template->renderBlock('body_text', $parameters);

        return $this->send(
            $subject,
            self::ADMIN_VEFA_TEAM_ADDRESS,
            [
                'html' => $bodyHtml,
                'text' => $bodyText,
            ]
        );
    }

    public function vefaSignaturePactSuccess(User $user, Housing $housing)
    {
        $subject = 'Nouveau pacte de préférence "'. $housing->getVefa()->getName() .'"';

        $parameters = [
            'subject' => $subject,
            'user'    => $user,
            'housing' => $housing,
        ];

        $template = $this->twig->load('vefa/emails/pact_signed.html.twig');
        $bodyHtml = $template->renderBlock('content', $parameters);
        $bodyText = $template->renderBlock('body_text', $parameters);

        return $this->send(
            $subject,
            self::ADMIN_VEFA_TEAM_ADDRESS,
            [
                'html' => $bodyHtml,
                'text' => $bodyText,
            ]
        );
    }

    public function vefaSignaturePactRefused(User $user, Housing $housing)
    {
        $subject = 'Pacte de préférence refusé "'. $housing->getVefa()->getName() .'"';

        $parameters = [
            'subject' => $subject,
            'user'    => $user,
            'housing' => $housing,
        ];

        $template = $this->twig->load('vefa/emails/pact_refused.html.twig');
        $bodyHtml = $template->renderBlock('content', $parameters);
        $bodyText = $template->renderBlock('body_text', $parameters);

        return $this->send(
            $subject,
            self::ADMIN_VEFA_TEAM_ADDRESS,
            [
                'html' => $bodyHtml,
                'text' => $bodyText,
            ]
        );
    }

    public function vefaDenunciation(User $user, Vefa $vefa)
    {
        $subject    = 'Dénonciation client Homunity Patrimoine';
        $recipient  = $vefa->getPromoter()->getEmail() ?? self::ADMIN_VEFA_TEAM_ADDRESS[0];

        $parameters = [
            'subject' => $subject,
            'user'    => $user,
            'vefa'    => $vefa,
        ];

        $template = $this->twig->load('vefa/emails/denunciation.html.twig');
        $bodyHtml = $template->renderBlock('content', $parameters);
        $bodyText = $template->renderBlock('body_text', $parameters);

        return $this->send(
            $subject,
            [$recipient],
            [
                'html' => $bodyHtml,
                'text' => $bodyText,
            ]
        );
    }
}
