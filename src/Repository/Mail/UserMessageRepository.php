<?php

namespace App\Repository\Mail;

use App\Entity\Mail\UserMessage;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMessage>
 */
class UserMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMessage::class);
    }

    public function save(UserMessage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserMessage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère tous les messages avec pagination et filtres
     */
    public function findAllWithFilters(?string $search = null, ?string $category = null, ?bool $isRead = null, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'u')
            ->addSelect('u')
            ->orderBy('m.isRead', 'ASC')
            ->addOrderBy('m.createdAt', 'DESC');

        if ($search) {
            $qb->andWhere('m.subject LIKE :search OR m.message LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $qb->andWhere('m.category = :category')
               ->setParameter('category', $category);
        }

        if ($isRead !== null) {
            $qb->andWhere('m.isRead = :isRead')
               ->setParameter('isRead', $isRead);
        }

        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de messages avec filtres
     */
    public function countWithFilters(?string $search = null, ?string $category = null, ?bool $isRead = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->leftJoin('m.sender', 'u');

        if ($search) {
            $qb->andWhere('m.subject LIKE :search OR m.message LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $qb->andWhere('m.category = :category')
               ->setParameter('category', $category);
        }

        if ($isRead !== null) {
            $qb->andWhere('m.isRead = :isRead')
               ->setParameter('isRead', $isRead);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les messages d'un utilisateur spécifique
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.sender = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les messages non lus
     */
    public function countUnread(): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque un message comme lu
     */
    public function markAsRead(UserMessage $message): void
    {
        $message->setIsRead(true);
        $this->save($message, true);
    }

    /**
     * Récupère les statistiques des messages
     */
    public function getStatistics(): array
    {
        $total = $this->count([]);
        $unread = $this->countUnread();
        $withResponse = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.adminResponse IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $byCategory = $this->createQueryBuilder('m')
            ->select('m.category, COUNT(m.id) as count')
            ->groupBy('m.category')
            ->getQuery()
            ->getResult();

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
            'with_response' => $withResponse,
            'by_category' => $byCategory,
        ];
    }

    /**
     * Récupère les messages récents (7 derniers jours)
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'u')
            ->addSelect('u')
            ->where('m.createdAt >= :date')
            ->setParameter('date', new \DateTime('-7 days'))
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
