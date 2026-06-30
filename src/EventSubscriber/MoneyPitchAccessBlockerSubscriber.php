<?php

namespace App\EventSubscriber;

use App\Entity\User\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Bloque l'accès à l'espace client ADN pour les utilisateurs devant être redirigés vers MoneyPitch.
 * Vérifie à chaque requête si l'utilisateur doit être sur MoneyPitch et le redirige si nécessaire.
 */
class MoneyPitchAccessBlockerSubscriber implements EventSubscriberInterface
{
    // Routes autorisées même pour les utilisateurs MoneyPitch
    private const ALLOWED_ROUTES = [
        'app_moneypitch_redirect',
        'app_moneypitch_login',
        'app_moneypitch_error',
        'app_logout',
        'app_login',
        'app_home',
        'app_register',
        'app_verify_email',
        'app_forgot_password_request',
        'app_reset_password',
        // Routes publiques du site vitrine
        'services_page',
        'expertise_page',
        'contact_page',
        'news_list',
        'news_show',
        'legal_mentions',
        'privacy_policy',
        'cookies_policy',
        'cgv',
    ];

    // Préfixes de routes autorisées
    private const ALLOWED_ROUTE_PREFIXES = [
        'admin_',           // Toutes les routes admin
        '_wdt',             // Web Debug Toolbar
        '_profiler',        // Profiler Symfony
        'api_',             // API
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité haute pour intercepter avant les contrôleurs
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ne traiter que la requête principale (pas les sous-requêtes)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Pas de route = requête invalide ou asset, on ignore
        if (!$route) {
            return;
        }

        // Vérifier si la route est autorisée
        if ($this->isRouteAllowed($route)) {
            return;
        }

        // Récupérer l'utilisateur connecté
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Vérifier si l'utilisateur doit être redirigé vers MoneyPitch
        if (!$user->shouldRedirectToMoneyPitch()) {
            return;
        }

        // L'utilisateur essaie d'accéder à une page non autorisée alors qu'il devrait être sur MoneyPitch
        // Rediriger vers la page de redirection MoneyPitch
        $response = new RedirectResponse(
            $this->urlGenerator->generate('app_moneypitch_redirect')
        );

        $event->setResponse($response);
    }

    /**
     * Vérifie si une route est autorisée pour les utilisateurs MoneyPitch
     */
    private function isRouteAllowed(string $route): bool
    {
        // Vérifier les routes exactes
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return true;
        }

        // Vérifier les préfixes de routes
        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

