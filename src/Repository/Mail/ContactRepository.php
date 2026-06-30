<?php

namespace App\Repository\Mail;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Mail\Contact;
use App\Repository\Utils\QueryOptions;

class ContactRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function getSearchQueryBuilder(array $search = [], ?int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this
            ->createQueryBuilder('contact')
        ;

        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);
        $this->filter($queryBuilder, $search);

        return $queryBuilder->getQuery();
    }

    /**
     * Filters
     */
    protected function global(string $search): void
    {
        $this->queryBuilder
            ->andWhere('contact.email LIKE :email')
            ->setParameter('email', '%'.$search.'%')
        ;
    }

    protected function lastname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('contact.lastname LIKE :lastname')
            ->setParameter('lastname', '%'.$search.'%');
    }

    protected function firstname(string $search): void
    {
        $this->queryBuilder
            ->andWhere('contact.firstname LIKE :firstname')
            ->setParameter('firstname', '%'.$search.'%');
    }

    protected function email(string $search): void
    {
        $this->queryBuilder
            ->andWhere('contact.email LIKE :email')
            ->setParameter('email', '%'.$search.'%');
    }

    protected function subject(string $search): void
    {
        $this->queryBuilder
            ->andWhere('contact.subject LIKE :subject')
            ->setParameter('subject', '%'.$search.'%');
    }

    protected function message(string $search): void
    {
        $this->queryBuilder
            ->andWhere('contact.message LIKE :message')
            ->setParameter('message', '%'.$search.'%');
    }
    /**
     * End filters
     */

    /**
     * Compte les contacts non lus
     */
    public function countUnread(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
