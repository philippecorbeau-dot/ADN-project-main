<?php

namespace App\Repository\Chat;

use App\Entity\Chat\ChatMessage;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function save(ChatMessage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ChatMessage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime tous les messages d'une conversation
     */
    public function deleteByConversationId(string $conversationId): void
    {
        $this->createQueryBuilder('m')
            ->delete()
            ->where('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les messages d'une conversation
     */
    public function findByConversation(string $conversationId, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les derniers messages d'une conversation
     */
    public function findRecentByConversation(string $conversationId, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère un ID de conversation unique pour un utilisateur
     */
    public function generateConversationId(User $user): string
    {
        return 'user_' . $user->getId();
    }

    /**
     * Récupère toutes les conversations actives pour l'admin
     */
    public function findActiveConversations(): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.conversationId, MAX(m.createdAt) as lastMessageAt, u.firstName, u.lastName, u.email')
            ->leftJoin('m.sender', 'u')
            ->where('m.senderType = :userType')
            ->setParameter('userType', 'user')
            ->groupBy('m.conversationId, u.firstName, u.lastName, u.email')
            ->orderBy('lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les messages non lus d'une conversation pour l'admin
     */
    public function countUnreadForAdmin(string $conversationId): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.conversationId = :conversationId')
            ->andWhere('m.senderType = :userType')
            ->andWhere('m.isRead = false')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('userType', 'user')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les messages non lus d'une conversation pour l'utilisateur
     */
    public function countUnreadForUser(string $conversationId): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.conversationId = :conversationId')
            ->andWhere('m.senderType = :adminType')
            ->andWhere('m.isRead = false')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('adminType', 'admin')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque tous les messages d'une conversation comme lus pour l'admin
     */
    public function markAsReadForAdmin(string $conversationId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', 'true')
            ->where('m.conversationId = :conversationId')
            ->andWhere('m.senderType = :userType')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('userType', 'user')
            ->getQuery()
            ->execute();
    }

    /**
     * Marque tous les messages d'une conversation comme lus pour l'utilisateur
     */
    public function markAsReadForUser(string $conversationId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', 'true')
            ->where('m.conversationId = :conversationId')
            ->andWhere('m.senderType = :adminType')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('adminType', 'admin')
            ->getQuery()
            ->execute();
    }

    /**
     * Récupère les statistiques des conversations pour l'admin
     */
    public function getChatStatistics(): array
    {
        $totalConversations = $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT m.conversationId)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalUnread = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.senderType = :userType')
            ->andWhere('m.isRead = false')
            ->setParameter('userType', 'user')
            ->getQuery()
            ->getSingleScalarResult();

        $activeToday = $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT m.conversationId)')
            ->where('m.createdAt >= :today')
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_conversations' => $totalConversations,
            'total_unread' => $totalUnread,
            'active_today' => $activeToday,
        ];
    }

    /**
     * Récupère le dernier message d'une conversation
     */
    public function findLastMessage(string $conversationId): ?ChatMessage
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversationId = :conversationId')
            ->setParameter('conversationId', $conversationId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte tous les messages non lus pour l'admin (toutes conversations confondues)
     */
    public function countAllUnreadForAdmin(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.senderType = :userType')
            ->andWhere('m.isRead = false')
            ->setParameter('userType', 'user')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
