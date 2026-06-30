<?php

namespace App\Controller\Front\User;

use App\Entity\Chat\ChatMessage;
use App\Entity\User\User;
use App\Entity\Chat\ChatConversation;
use App\Repository\Chat\ChatConversationRepository;
use App\Repository\Chat\ChatMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/user/chat", name: "user_chat_")]
#[IsGranted("ROLE_USER")]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatConversationRepository $chatConversationRepository,
    ) {}

    #[Route("", name: "index", methods: ["GET"])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        // Permettre la sélection explicite d'une conversation (nouveau chat, etc.)
        $requestedConversationId = trim((string) $request->query->get('conv', ''));
        $basePrefix = 'user_' . $user->getId();
        if ($requestedConversationId !== '' && str_starts_with($requestedConversationId, $basePrefix)) {
            $conversationId = $requestedConversationId;
        } else {
            $conversationId = $this->chatMessageRepository->generateConversationId($user);
        }
        $conversation = $this->chatConversationRepository->findOrCreate($conversationId);
        // Si la conversation est fermée, on n'affiche pas: on crée une nouvelle conversation proprement
        if ($conversation->isClosed()) {
            return $this->redirectToRoute('user_chat_new');
        }

        // Données de carte de conversation
        $lastMessage = $this->chatMessageRepository->findLastMessage($conversationId);
        $unreadCount = (int) $this->chatMessageRepository->countUnreadForUser($conversationId);
        $status = $conversation->getStatus();
        
        // Marquer tous les messages admin comme lus après calcul
        $this->chatMessageRepository->markAsReadForUser($conversationId);
        
        // Récupérer les messages récents
        $messages = $this->chatMessageRepository->findRecentByConversation($conversationId, 50);
        $messages = array_reverse($messages); // Les afficher dans l'ordre chronologique

        return $this->render('front/user/chat/index.html.twig', [
            'user' => $user,
            'messages' => $messages,
            'conversationId' => $conversationId,
            'conversationStatus' => $status,
            'conversationCard' => [
                'title' => 'Équipe ADN Family Office',
                'preview' => $lastMessage ? $lastMessage->getMessage() : 'Bienvenue ! Dites-nous comment on peut vous aider.',
                'time' => $lastMessage ? $lastMessage->getFormattedDateTime() : '',
                'status' => $status, // 'ouvert' | 'ferme'
                'unread' => $unreadCount,
            ],
        ]);
    }

    #[Route("/delete", name: "delete", methods: ["POST"])]
    public function deleteConversation(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversationId = trim((string) ($request->request->get('conversationId') ?? $request->query->get('conversationId', '')));
        $basePrefix = 'user_' . $user->getId();
        if ($conversationId === '' || !str_starts_with($conversationId, $basePrefix)) {
            return new JsonResponse(['success' => false, 'error' => 'Conversation invalide'], 400);
        }
        // Supprimer messages puis conversation
        $this->chatMessageRepository->deleteByConversationId($conversationId);
        $this->chatConversationRepository->deleteByConversationId($conversationId);
        return new JsonResponse(['success' => true]);
    }

    #[Route("/new", name: "new", methods: ["GET"])]
    public function createNewConversation(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        // Génère un nouvel ID unique basé sur l'utilisateur
        $base = 'user_' . $user->getId();
        $suffix = (new \DateTimeImmutable())->format('YmdHis');
        $conversationId = $base . '-' . $suffix;
        $conversation = $this->chatConversationRepository->findOrCreate($conversationId);
        $conversation->setStatus(ChatConversation::STATUS_OPEN);
        $this->entityManager->flush();
        return $this->redirectToRoute('user_chat_index', ['conv' => $conversationId]);
    }

    #[Route("/send", name: "send", methods: ["POST"])]
    public function sendMessage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $messageContent = trim($request->request->get('message', ''));
        
        if (empty($messageContent)) {
            return new JsonResponse(['error' => 'Le message ne peut pas être vide'], 400);
        }

        if (strlen($messageContent) > 1000) {
            return new JsonResponse(['error' => 'Le message est trop long (maximum 1000 caractères)'], 400);
        }

        // Conversation ciblée (fallback sur la conversation "par défaut")
        $providedConversationId = trim((string) ($request->request->get('conversationId') ?? $request->query->get('conversationId', '')));
        $basePrefix = 'user_' . $user->getId();
        if ($providedConversationId !== '' && str_starts_with($providedConversationId, $basePrefix)) {
            $conversationId = $providedConversationId;
        } else {
            $conversationId = $this->chatMessageRepository->generateConversationId($user);
        }
        $conversation = $this->chatConversationRepository->findOrCreate($conversationId);
        if ($conversation->isClosed()) {
            return new JsonResponse(['error' => 'La conversation est fermée par l’équipe.'], 403);
        }
        
        // Créer le message
        $chatMessage = new ChatMessage();
        $chatMessage->setSender($user);
        $chatMessage->setMessage($messageContent);
        $chatMessage->setSenderType('user');
        $chatMessage->setConversationId($conversationId);
        
        $this->chatMessageRepository->save($chatMessage, true);

        // Publier via Mercure pour les admins
        // Pas de publication temps réel (Mercure retiré)

        return new JsonResponse([
            'success' => true,
            'message' => [
                'id' => $chatMessage->getId(),
                'message' => $chatMessage->getMessage(),
                'senderName' => $chatMessage->getSenderName(),
                'senderInitials' => $chatMessage->getSenderInitials(),
                'formattedTime' => $chatMessage->getFormattedTime(),
                'isFromAdmin' => false,
                'isFromUser' => true,
            ],
            'lastId' => $chatMessage->getId(),
        ]);
    }

    #[Route("/messages", name: "messages", methods: ["GET"])]
    public function getMessages(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        // Conversation ciblée (fallback sur la conversation "par défaut")
        $providedConversationId = trim((string) $request->query->get('conversationId', ''));
        $basePrefix = 'user_' . $user->getId();
        if ($providedConversationId !== '' && str_starts_with($providedConversationId, $basePrefix)) {
            $conversationId = $providedConversationId;
        } else {
            $conversationId = $this->chatMessageRepository->generateConversationId($user);
        }
        $afterId = (int) $request->query->get('afterId', 0);
        $messages = $this->chatMessageRepository->findRecentByConversation($conversationId, 50);
        $messages = array_reverse($messages);

        $messagesData = [];
        $lastId = 0;
        foreach ($messages as $message) {
            if ($afterId && $message->getId() <= $afterId) {
                continue; // ne retourner que les nouveaux messages
            }
            $messagesData[] = [
                'id' => $message->getId(),
                'message' => $message->getMessage(),
                'senderName' => $message->getSenderName(),
                'senderInitials' => $message->getSenderInitials(),
                'formattedTime' => $message->getFormattedTime(),
                'isFromAdmin' => $message->isFromAdmin(),
                'isFromUser' => $message->isFromUser(),
                'isSystemMessage' => $message->isSystemMessage(),
            ];
            $lastId = max($lastId, (int) $message->getId());
        }

        return new JsonResponse(['messages' => $messagesData, 'lastId' => $lastId]);
    }

    #[Route("/read", name: "mark_read", methods: ["POST"])]
    public function markRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        // Conversation ciblée (fallback sur la conversation "par défaut")
        $providedConversationId = trim((string) ($request->request->get('conversationId') ?? $request->query->get('conversationId', '')));
        $basePrefix = 'user_' . $user->getId();
        if ($providedConversationId !== '' && str_starts_with($providedConversationId, $basePrefix)) {
            $conversationId = $providedConversationId;
        } else {
            $conversationId = $this->chatMessageRepository->generateConversationId($user);
        }
        $this->chatMessageRepository->markAsReadForUser($conversationId);
        return new JsonResponse(['success' => true]);
    }

    #[Route("/typing", name: "typing", methods: ["POST"])]
    public function sendTypingIndicator(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $conversationId = $this->chatMessageRepository->generateConversationId($user);
        $isTyping = $request->request->getBoolean('typing', false);

        // Publier l'indicateur de frappe via Mercure
        $update = new Update(
            ['chat/admin'], // Topic pour tous les admins
            json_encode([
                'type' => 'typing_indicator',
                'conversationId' => $conversationId,
                'isTyping' => $isTyping,
                'userInfo' => [
                    'id' => $user->getId(),
                    'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                ]
            ])
        );

        $this->hub->publish($update);

        return new JsonResponse(['success' => true]);
    }
}
