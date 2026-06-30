<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configure le fuseau horaire Paris/France pour toute l'application
 */
class TimezoneSubscriber implements EventSubscriberInterface
{
    private const TIMEZONE = 'Europe/Paris';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255], // Priorité maximale
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Configure le fuseau horaire par défaut pour PHP
        date_default_timezone_set(self::TIMEZONE);
    }
}

