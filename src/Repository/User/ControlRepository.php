<?php

namespace App\Repository\User;

use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User\Control;
use App\Repository\Utils\QueryOptions;

class ControlRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Control::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this->createQueryBuilder('control');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }
    
    public function findWhereEmailIsNotNull()
    {
        $queryBuilder =  $this->createQueryBuilder('control')
            ->where('control.email IS NOT NULL');

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Filters
     */

    protected function firstname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('control.firstName LIKE :firstname')
            ->setParameter('firstname', '%'.$search.'%')
        ;
    }

    protected function lastname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('control.lastName LIKE :lastname')
            ->setParameter('lastname', '%'.$search.'%')
        ;
    }

    protected function email(string $search): void
    {
        $this->queryBuilder
            ->andWhere('control.email LIKE :email')
            ->setParameter('email', '%'.trim($search).'%')
        ;
    }

    protected function type(array $search): void
    {
        foreach ($search as $key => $tag) {
            $field = 'tag'.$key;

            $this->queryBuilder
                ->andWhere('control.type LIKE :'.$field)
                ->setParameter($field, '%'.trim($tag).'%')
            ;
        }
    }

    /**
     * End filters
     */
}
