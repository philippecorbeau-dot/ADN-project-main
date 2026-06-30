<?php

namespace App\EventSubscriber;

use App\Entity\User\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Gère la redirection vers MoneyPitch après une connexion réussie
 * pour les utilisateurs ayant le flag redirectToMoneyPitch activé
 */
class MoneyPitchRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onLoginSuccess', 0],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // Vérifier que c'est bien un utilisateur de notre application
        if (!$user instanceof User) {
            return;
        }

        // Vérifier si l'utilisateur doit être redirigé vers MoneyPitch
        if ($user->shouldRedirectToMoneyPitch()) {
            // Rediriger vers la page intermédiaire MoneyPitch
            $response = new RedirectResponse(
                $this->urlGenerator->generate('app_moneypitch_redirect')
            );
            $event->setResponse($response);
            return;
        }

        // Utilisateur ADN (non-MoneyPitch, non-admin) : rediriger vers le dashboard client
        if (!$user->isAdmin()) {
            $response = new RedirectResponse(
                $this->urlGenerator->generate('user_dashboard')
            );
            $event->setResponse($response);
        }
    }
}

