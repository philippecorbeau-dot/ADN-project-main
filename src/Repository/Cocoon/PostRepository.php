<?php

namespace App\Repository\Cocoon;

use App\Entity\Cocoon\Post;
use App\Repository\Utils\QueryOptions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class PostRepository extends ServiceEntityRepository
{
    use QueryOptions;

    protected $queryBuilder;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this->createQueryBuilder('post');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    public function findRelativePosts(Post $post)
    {
        $queryBuilder = $this->createQueryBuilder('post');

        $queryBuilder->where('post.categoryId = :categoryId')
            ->setParameter('categoryId', $post->getCategoryId())
            ->andWhere('post.id != :postId')
            ->andWhere('post.parent IS NULL')
            ->setParameter('postId', $post->getId());

        return $queryBuilder->getQuery()->getResult();
    }

    public function findChildPosts(Post $post)
    {
        $queryBuilder = $this->createQueryBuilder('post');

        $queryBuilder->where('post.categoryId = :categoryId')
            ->setParameter('categoryId', $post->getCategoryId())
            ->andWhere('post.id != :postId')
            ->setParameter('postId', $post->getId());

        return $queryBuilder->getQuery()->getResult();
    }

    public function getRatingPost(Post $post)
    {
        $queryBuilder = $this->createQueryBuilder('post');

        $queryBuilder
            ->select('AVG(ratings.score) as score, COUNT(ratings) as total')
            ->leftJoin('post.ratings', 'ratings')
            ->andWhere('post.id = :post_id')
            ->setParameter('post_id', $post->getId())
        ;

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    protected function createdAt(): void
    {
        $this->queryBuilder
            ->andWhere('post.createdAt <= :date')
            ->setParameter('date', new \DateTime())
        ;
    }

    protected function status(): void
    {
        $this->queryBuilder
            ->andWhere('post.status = :status')
            ->setParameter('status', true)
        ;
    }

    protected function notLandingToOverride(): void
    {
        $this->queryBuilder
            ->andWhere('post.landingToOverride IS NULL')
        ;
    }


    public function findByLandingToOverride(string $slug)
    {
        $queryBuilder = $this->createQueryBuilder('post');
        $queryBuilder
            ->where('post.landingToOverride = :landingToOverride')
            ->andWhere('post.status = 1')
            ->setParameter('landingToOverride', $slug);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
