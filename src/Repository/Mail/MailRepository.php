<?php

namespace App\Repository\Mail;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Mail\Mail;
use App\Repository\Utils\QueryOptions;

class MailRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mail::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this
            ->createQueryBuilder('mail')
            ->leftJoin('mail.user', 'user')
        ;

        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);
        $this->filter($queryBuilder, $search);

        return $queryBuilder->getQuery();
    }

    /**
     * Filters
     */
    protected function lastname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.lastName LIKE :lastname')
            ->setParameter('lastname', '%'.$search.'%');
    }

    protected function firstname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.firstName LIKE :firstname')
            ->setParameter('firstname', '%'.$search.'%');
    }

    protected function sentTo(string $search): void
    {
        $this->queryBuilder
            ->andWhere('mail.sentTo LIKE :email')
            ->setParameter('email', '%'.$search.'%');
    }

    protected function subject(string $search): void
    {
        $this->queryBuilder
            ->andWhere('mail.subject LIKE :subject')
            ->setParameter('subject', '%'.$search.'%');
    }

    protected function templateTxt(string $search): void
    {
        $this->queryBuilder
            ->andWhere('mail.templateTxt LIKE :message')
            ->setParameter('message', '%'.$search.'%');
    }
    /**
     * End filters
     */
}
