<?php

namespace App\Repository;

use App\Entity\InvestmentOpportunityClick;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvestmentOpportunityClickRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestmentOpportunityClick::class);
    }

    /**
     * Statistiques des clics par produit
     */
    public function getClickStatsByProduct(): array
    {
        $qb = $this->createQueryBuilder('ioc')
            ->select('ioc.productType, COUNT(ioc.id) as clicks')
            ->groupBy('ioc.productType')
            ->orderBy('clicks', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des clics par produit sur une période
     */
    public function getClickStatsByProductSince(\DateTimeInterface $since): array
    {
        $qb = $this->createQueryBuilder('ioc')
            ->select('ioc.productType, COUNT(ioc.id) as clicks')
            ->where('ioc.clickedAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('ioc.productType')
            ->orderBy('clicks', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des clics par action (discover/documents)
     */
    public function getClickStatsByAction(): array
    {
        $qb = $this->createQueryBuilder('ioc')
            ->select('ioc.action, COUNT(ioc.id) as clicks')
            ->groupBy('ioc.action')
            ->orderBy('clicks', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Total des clics sur toutes les opportunités
     */
    public function getTotalClicks(): int
    {
        return $this->createQueryBuilder('ioc')
            ->select('COUNT(ioc.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total des clics depuis une date
     */
    public function getTotalClicksSince(\DateTimeInterface $since): int
    {
        return $this->createQueryBuilder('ioc')
            ->select('COUNT(ioc.id)')
            ->where('ioc.clickedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Clics par jour sur les 30 derniers jours
     */
    public function getClicksPerDayLast30Days(): array
    {
        $since = new \DateTime('-30 days');
        $since->setTime(0, 0, 0);

        $qb = $this->createQueryBuilder('ioc')
            ->select('DATE(ioc.clickedAt) as day, COUNT(ioc.id) as clicks')
            ->where('ioc.clickedAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('day')
            ->orderBy('day', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Top 5 des produits les plus cliqués
     */
    public function getTopProducts(int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('ioc')
            ->select('ioc.productType, COUNT(ioc.id) as clicks')
            ->groupBy('ioc.productType')
            ->orderBy('clicks', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Enregistrer un clic
     */
    public function recordClick(
        string $productType, 
        string $action, 
        ?object $user = null, 
        ?string $ipAddress = null, 
        ?string $userAgent = null, 
        ?string $referrer = null
    ): InvestmentOpportunityClick {
        $click = new InvestmentOpportunityClick();
        $click->setProductType($productType);
        $click->setAction($action);
        $click->setUser($user);
        $click->setIpAddress($ipAddress);
        $click->setUserAgent($userAgent);
        $click->setReferrer($referrer);

        $this->getEntityManager()->persist($click);
        $this->getEntityManager()->flush();

        return $click;
    }
}

