<?php

namespace App\Repository;

use App\Entity\InvestmentComparison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvestmentComparison>
 */
class InvestmentComparisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestmentComparison::class);
    }

    /**
     * Retourne la table de comparaison sous forme [criterion => [product => value]]
     */
    public function getMatrix(): array
    {
        $items = $this->findBy([], ['position' => 'ASC']);
        $matrix = [];
        foreach ($items as $item) {
            $matrix[$item->getCriterion()][$item->getProduct()] = $item->getValue();
        }
        return $matrix;
    }

    //    /**
    //     * @return InvestmentComparison[] Returns an array of InvestmentComparison objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?InvestmentComparison
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
