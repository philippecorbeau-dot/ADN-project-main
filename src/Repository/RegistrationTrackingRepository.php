<?php

namespace App\Repository;

use App\Entity\RegistrationTracking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegistrationTracking>
 */
class RegistrationTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistrationTracking::class);
    }

    /**
     * Récupère les trackings récents (non complétés)
     */
    public function findRecentIncomplete(int $limit = 50): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.completed = false')
            ->orderBy('rt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère tous les trackings récents
     */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('rt')
            ->orderBy('rt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les trackings actifs (non complétés) des dernières 24h
     */
    public function countActiveLast24Hours(): int
    {
        $date = new \DateTime('-24 hours');
        
        return $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->where('rt.completed = false')
            ->andWhere('rt.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
