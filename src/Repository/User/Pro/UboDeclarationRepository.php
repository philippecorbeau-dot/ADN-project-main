<?php

namespace App\Repository\User\Pro;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use App\Entity\User\Pro\UboDeclaration;
use App\Repository\Utils\QueryOptions;

class UboDeclarationRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UboDeclaration::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = [], $front = false, bool $lazy = false): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('uboDeclaration')
            ->leftJoin('uboDeclaration.pro', 'pro')
            ->leftJoin('pro.user', 'user');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder;
    }

    public function getSearchQuery(array $search = [], int $limit = null, array $order = [], $front = false, bool $lazy = false): Query
    {
        return $this->getSearchQueryBuilder($search, $limit, $order, $front, $lazy)->getQuery();
    }

    /**
     * Filters
     */
    protected function email(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.email LIKE :email')
            ->setParameter('email', '%'.$search.'%')
        ;
    }

    protected function status(string $search): void
    {
        $this->queryBuilder
            ->andWhere('uboDeclaration.status LIKE :status')
            ->setParameter('status', '%'.$search.'%')
        ;
    }
    /**
     * End filters
     */
}
