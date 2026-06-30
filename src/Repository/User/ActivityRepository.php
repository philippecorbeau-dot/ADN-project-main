<?php

namespace App\Repository\User;

use App\Entity\User\Activity;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * @return Activity[]
     */
    public function findLatestForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les activités récentes pour un utilisateur (non lues)
     * @param User $user
     * @param int $days Nombre de jours pour considérer une activité comme récente
     * @return int
     */
    public function countRecentActivities(User $user, int $days = 30): int
    {
        $since = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.user = :user')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les activités récentes liées aux KYC
     * @param User $user
     * @param int $limit
     * @return Activity[]
     */
    public function findRecentKycActivities(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.title LIKE :kycTitle')
            ->setParameter('user', $user)
            ->setParameter('kycTitle', '%Document%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}


