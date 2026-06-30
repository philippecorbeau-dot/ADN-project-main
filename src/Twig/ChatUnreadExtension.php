<?php

namespace App\Twig;

use App\Repository\Chat\ChatMessageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\User\User;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Extension Twig pour fournir le compteur de messages chat non lus
 * sur toutes les pages du dashboard utilisateur
 */
class ChatUnreadExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return [
                'globalUnreadMessagesCount' => 0,
            ];
        }

        // Générer l'ID de conversation pour cet utilisateur
        $conversationId = $this->chatMessageRepository->generateConversationId($user);
        
        // Compter les messages admin non lus
        $unreadCount = (int) $this->chatMessageRepository->countUnreadForUser($conversationId);

        return [
            'globalUnreadMessagesCount' => $unreadCount,
        ];
    }
}

