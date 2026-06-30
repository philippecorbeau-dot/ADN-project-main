<?php

namespace App\Repository\User;

use App\Repository\Utils\QueryOptions;
use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User\Spam;

class SpamRepository extends ServiceEntityRepository
{
    use QueryOptions;

    protected $queryBuilder;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spam::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this->createQueryBuilder('spam');

        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);
        $this->filter($queryBuilder, $search);

        return $queryBuilder->getQuery();
    }

    /**
     * Filters
     */
    protected function email(string $search): void
    {
        $this->queryBuilder
            ->andWhere('spam.email LIKE :email')
            ->setParameter('email', '%'.$search.'%')
        ;
    }

    protected function blocked(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('spam.blocked = :blocked')
            ->setParameter('blocked', $search)
        ;
    }
    /**
     * End filters
     */
}
