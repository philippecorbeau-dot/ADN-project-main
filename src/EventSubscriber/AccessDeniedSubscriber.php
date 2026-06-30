<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * Intercepte les exceptions AccessDeniedException pour afficher une page propre
 * au lieu d'une erreur technique, même en mode développement.
 */
class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 2],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        // Ne traiter que les AccessDeniedException
        if (!$exception instanceof AccessDeniedException) {
            return;
        }
        
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        
        // Si c'est une route admin, afficher une page propre
        if (str_starts_with($path, '/admin')) {
            try {
                $content = $this->twig->render('admin_modern/errors/access_denied.html.twig', [
                    'message' => $exception->getMessage(),
                    'previousUrl' => $request->headers->get('referer'),
                ]);
                
                $response = new Response($content, Response::HTTP_FORBIDDEN);
                $event->setResponse($response);
            } catch (\Throwable $e) {
                // Si le template n'existe pas, rediriger vers le dashboard avec un message flash
                $session = $request->getSession();
                $session->getFlashBag()->add('error', 'Vous n\'avez pas accès à cette fonctionnalité.');
                
                $response = new RedirectResponse($this->urlGenerator->generate('admin_modern_dashboard'));
                $event->setResponse($response);
            }
        }
    }
}

