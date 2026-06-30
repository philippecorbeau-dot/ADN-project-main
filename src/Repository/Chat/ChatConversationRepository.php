<?php

namespace App\Repository\Chat;

use App\Entity\Chat\ChatConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatConversation>
 */
class ChatConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatConversation::class);
    }

    public function save(ChatConversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOrCreate(string $conversationId): ChatConversation
    {
        $conversation = $this->findOneBy(['conversationId' => $conversationId]);
        if (!$conversation) {
            $conversation = new ChatConversation($conversationId);
            $this->save($conversation, true);
        }
        return $conversation;
    }

    public function deleteByConversationId(string $conversationId): void
    {
        $em = $this->getEntityManager();
        $conversation = $this->findOneBy(['conversationId' => $conversationId]);
        if ($conversation) {
            $em->remove($conversation);
            $em->flush();
        }
    }
}


