<?php

namespace App\Repository\Blog;

use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Blog\Category;
use App\Repository\Utils\QueryOptions;

class CategoryRepository extends ServiceEntityRepository
{
    use QueryOptions;

    protected $queryBuilder;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this->createQueryBuilder('category');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    /**
     * Filters
     */
    protected function name(string $search): void
    {
        $this->queryBuilder
            ->andWhere('category.name LIKE :name')
            ->setParameter('name', '%'.$search.'%')
        ;
    }

    protected function description(string $search): void
    {
        $this->queryBuilder
            ->andWhere('category.description LIKE :description')
            ->setParameter('description', '%'.$search.'%')
        ;
    }
    /**
     * End filters
     */
}
