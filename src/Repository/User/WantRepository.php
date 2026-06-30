<?php

namespace App\Repository\User;

use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User\Want;
use App\Repository\Utils\QueryOptions;

class WantRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Want::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this->createQueryBuilder('want')
            ->leftJoin('want.user', 'user');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    /**
     * Filters
     */
    protected function firstname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.firstName LIKE :firstname')
            ->setParameter('firstname', '%'.$search.'%')
        ;
    }

    protected function lastname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.lastName LIKE :lastname')
            ->setParameter('lastname', '%'.$search.'%')
        ;
    }

    protected function email(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.email LIKE :email')
            ->setParameter('email', '%'.$search.'%')
        ;
    }

    protected function newsletter(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.wantNl = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function infopack(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.wantInfopack = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function allInfopack(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.wantInfopack = :bool')
            ->orWhere('want.scpiInfopack = :bool')
            ->orWhere('want.vefaInfopack = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function docBlog(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.wantDocBlog = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function syntheticFile(string $search): void
    {
        $this->queryBuilder
            ->andWhere('want.wantSyntheticFile LIKE :file')
            ->setParameter('file', '%'.$search.'%')
        ;
    }

    protected function hasSyntheticFile(bool $bool): void
    {
        if ($bool) {
            $this->queryBuilder
                ->andWhere('want.wantSyntheticFile IS NOT NULL')
            ;
        } else {
            $this->queryBuilder
                ->andWhere('want.wantSyntheticFile IS NULL')
            ;
        }

    }

    protected function scpiInfopack(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.scpiInfopack = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function wantInfopack(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.wantInfopack = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function vefaInfopack(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('want.vefaInfopack = :bool')
            ->setParameter('bool', $search)
        ;
    }

    protected function updatedAtStart(string $filter): void
    {
        $this->queryBuilder
            ->andWhere('want.updatedAt >= :updatedAtStart')
            ->setParameter('updatedAtStart', $filter)
        ;
    }

    protected function updatedAtEnd(string $filter): void
    {
        $this->queryBuilder
            ->andWhere('want.updatedAt <= :updatedAtEnd')
            ->setParameter('updatedAtEnd', $filter)
        ;
    }

    /**
     * End filters
     */
}
