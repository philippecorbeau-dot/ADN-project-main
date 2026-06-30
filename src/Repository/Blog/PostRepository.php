<?php

namespace App\Repository\Blog;

use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Repository\Utils\QueryOptions;
use App\Entity\Blog\Post;

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

        $queryBuilder
            ->join('post.category', 'category')
            ->join('post.user', 'user')
        ;

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    protected function publicationDate(): void
    {
        $this->queryBuilder
            ->andWhere('post.publicationDateStart <= :date')
            ->setParameter('date', new \DateTime())
        ;
    }

    protected function status(bool $filter): void
    {
        $this->queryBuilder
            ->andWhere('post.status = :status')
            ->setParameter('status', true)
        ;
    }

    protected function category(string $filter): void
    {
        $this->queryBuilder
            ->andWhere('category.seoSlug = :category')
            ->setParameter('category', $filter)
        ;
    }


    public function findOneWithoutHomunityNews()
    {
        $queryBuilder = $this->createQueryBuilder('post')
            ->leftJoin('post.category', 'category')
            ->leftJoin('post.user', 'user')
            ->where("category.seoSlug != 'les-news-d-homunity'")
            ->groupBy('post.id')
            ->orderBy('post.createdAt', 'DESC')
            ->setMaxResults(1);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findCrowdPosts($limit = 4)
    {
        $queryBuilder = $this->createQueryBuilder('post')
            ->leftJoin('post.category', 'category')
            ->leftJoin('post.user', 'user')
            ->where("category.seoSlug = 'crowdfunding'")
            ->groupBy('post.id')
            ->orderBy('post.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findLifeInsurancePosts($limit = 4)
    {
        $queryBuilder = $this->createQueryBuilder('post')
            ->leftJoin('post.category', 'category')
            ->leftJoin('post.user', 'user')
            ->where("category.seoSlug = 'assurance-vie'")
            ->groupBy('post.id')
            ->orderBy('post.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findImmoPosts($limit = 4)
    {
        $queryBuilder = $this->createQueryBuilder('post')
            ->leftJoin('post.category', 'category')
            ->leftJoin('post.user', 'user')
            ->where("category.seoSlug = 'immobilier'")
            ->groupBy('post.id')
            ->orderBy('post.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findScpiPosts($limit = 4)
    {
        $queryBuilder = $this->createQueryBuilder('post')
            ->leftJoin('post.category', 'category')
            ->leftJoin('post.user', 'user')
            ->where("category.seoSlug = 'scpi'")
            ->groupBy('post.id')
            ->orderBy('post.createdAt', 'DESC')
            ->setMaxResults($limit);

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

    /**
     * Filters
     */
    protected function title(string $search): void
    {
        $this->queryBuilder
            ->andWhere('post.title like :title')
            ->setParameter('title', '%'.$search.'%')
        ;
    }

    protected function content(string $search): void
    {
        $this->queryBuilder
            ->andWhere('post.content like :content')
            ->setParameter('content', '%'.$search.'%')
        ;
    }

    protected function user(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.email like :email')
            ->setParameter('email', '%'.$search.'%')
        ;
    }

    protected function disableInSitemap(bool $search): void
    {
        $this->queryBuilder
            ->andWhere('post.disableInSitemap = :disableInSitemap')
            ->setParameter('disableInSitemap', $search)
        ;
    }
    /**
     * End filters
     */
}
