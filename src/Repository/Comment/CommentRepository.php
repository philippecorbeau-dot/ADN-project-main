<?php

namespace App\Repository\Comment;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Repository\Utils\QueryOptions;
use App\Entity\Comment\Comment;

class CommentRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this->createQueryBuilder('comment');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    protected function model($search): void
    {
        $this->queryBuilder
            ->andWhere('comment.model = :model')
            ->setParameter('model', $search)
        ;
    }

    /**
     * Filters
     */
    protected function email(string $search): void
    {
        $this->queryBuilder
            ->andWhere('comment.email LIKE :email')
            ->setParameter('email', '%'.$search.'%')
        ;
    }
    /**
     * End filters
     */
}
