<?php

namespace App\EventSubscriber;

use App\Repository\Mail\UserMessageRepository;
use App\Repository\Mail\ContactRepository;
use App\Repository\Chat\ChatMessageRepository;
use App\Repository\User\KycDocumentRepository;
use App\Entity\User\KycDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class AdminSidebarCountersSubscriber implements EventSubscriberInterface
{
    private ?array $counters = null;

    public function __construct(
        private readonly UserMessageRepository $userMessageRepository,
        private readonly ContactRepository $contactRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly KycDocumentRepository $kycDocumentRepository,
        private readonly Environment $twig
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Vérifier si c'est une route admin moderne
        if (!str_starts_with($request->attributes->get('_route', ''), 'admin_modern_')) {
            return;
        }

        // Calculer les compteurs une seule fois par requête
        if ($this->counters === null) {
            try {
                $this->counters = [
                    'unreadMessages' => $this->userMessageRepository->countUnread(),
                    'unreadContacts' => $this->contactRepository->countUnread(), // Contacts non lus
                    'unreadChatMessages' => $this->chatMessageRepository->countAllUnreadForAdmin(),
                    'pendingKycDocuments' => $this->kycDocumentRepository->count(['status' => KycDocument::STATUS_PENDING]),
                ];
            } catch (\Exception $e) {
                // En cas d'erreur (table n'existe pas encore), utiliser des valeurs par défaut
                $this->counters = [
                    'unreadMessages' => 0,
                    'unreadContacts' => 0,
                    'unreadChatMessages' => 0,
                    'pendingKycDocuments' => 0,
                ];
            }

            // Injecter les variables dans Twig globalement
            $this->twig->addGlobal('unreadMessages', $this->counters['unreadMessages']);
            $this->twig->addGlobal('unreadContacts', $this->counters['unreadContacts']);
            $this->twig->addGlobal('unreadChatMessages', $this->counters['unreadChatMessages']);
            $this->twig->addGlobal('pendingKycDocuments', $this->counters['pendingKycDocuments']);
        }
    }
}

