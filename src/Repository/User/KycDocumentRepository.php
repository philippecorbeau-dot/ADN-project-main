<?php

namespace App\Repository\User;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\DBAL\Connection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User\KycDocument;
use App\Entity\User\User;
use App\Repository\Utils\QueryOptions;

class KycDocumentRepository extends ServiceEntityRepository
{
    use QueryOptions;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KycDocument::class);
    }

    public function getSearchQueryBuilder(array $search = [], ?int $limit = null, array $order = []): Query
    {
        $queryBuilder = $this
            ->createQueryBuilder('kycDocument')
            ->select('kycDocument, files, user, wants') // Left joins and select can be removed.. Only used to reduce number of queries to speed up pages
            ->leftJoin('kycDocument.files', 'files')
            ->leftJoin('kycDocument.user', 'user')
            ->leftJoin('user.wants', 'wants');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    public function exportQueryBuilder(array $search = [], ?int $limit = null, array $order = [])
    {
        $queryBuilder = $this
            ->createQueryBuilder('kycDocument')
            ->select('kycDocument, user, wants') // Left joins and select can be removed.. Only used to reduce number of queries to speed up pages
            ->leftJoin('kycDocument.user', 'user')
            ->leftJoin('user.wants', 'wants');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    public function findOneByTypeAndUser(string $type, User $user)
    {
        $queryBuilder = $this->createQueryBuilder('document')
            ->andWhere('document.type = :type')
            ->andWhere('document.user = :user')
            ->setParameter('type', $type)
            ->setParameter('user', $user)
            ->orderBy('document.createdAt', 'DESC')
            ->setMaxResults(1)
        ;

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function getLastIdentityProofValidated(User $user)
    {
        $qb = $this->createQueryBuilder('document')
            ->andWhere('document.type = :type')
            ->andWhere('document.status = :status')
            ->andWhere('document.user = :user')
            ->setParameter('type', KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF)
            ->setParameter('status', KycDocument::STATUS_VALIDATED)
            ->setParameter('user', $user)
            ->orderBy('document.updatedAt', 'ASC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getKycValidatedByMonth(\DateTime $from, \DateTime $to)
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(*) AS kycValidated, MONTH(updatedAt) as month, YEAR(updatedAt) as year
                FROM user_kyc_document 
                WHERE type = :type
                AND status = :status
                AND updatedAt BETWEEN :from AND :to
                GROUP BY month, year
                ORDER BY year, month
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('type', KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF);
        $stmt->bindValue('status', KycDocument::STATUS_VALIDATED);
        $stmt->bindValue('from', $from->format('Y-m-d'));
        $stmt->bindValue('to', $to->format('Y-m-d'));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getCountIdentityProofValidated(?\DateTime $date = null)
    {
        $queryBuilder = $this->createQueryBuilder('kyc')
            ->leftJoin('kyc.user', 'user')
            ->select('COUNT(DISTINCT(user.id))')
            ->where('kyc.type = :type')
            ->andWhere('kyc.status = :status')
            ->setParameter('type', KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF)
            ->setParameter('status', KycDocument::STATUS_VALIDATED)
        ;

        if (false === empty($date)) {
            $queryBuilder
                ->andWhere('YEAR(kyc.updatedAt) = :datey')
                ->andWhere('MONTH(kyc.updatedAt) = :datem')
                ->andWhere('DAY(kyc.updatedAt) = :dated')
                ->setParameter('datey', $date->format('Y'))
                ->setParameter('datem', $date->format('m'))
                ->setParameter('dated', $date->format('d'));
        }

        return $queryBuilder->getQuery()->getSingleScalarResult();
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

    protected function type(string $search): void
    {
        $this->queryBuilder
            ->andWhere('kycDocument.type LIKE :type')
            ->setParameter('type', $search)
        ;
    }

    protected function status(string $search): void
    {
        $this->queryBuilder
            ->andWhere('kycDocument.status LIKE :status')
            ->setParameter('status', $search)
        ;
    }

    protected function refusedReasonMessage(string $search): void
    {
        $this->queryBuilder
            ->andWhere('kycDocument.refusedReasonMessageName LIKE :message')
            ->setParameter('message', $search)
        ;
    }

    protected function createdAtStart(string $search): void
    {
        $this->queryBuilder
            ->andWhere('kycDocument.createdAt >= :createdAtStart')
            ->setParameter('createdAtStart', $search)
        ;
    }

    protected function createdAtEnd(string $search): void
    {
        $this->queryBuilder
            ->andWhere('kycDocument.createdAt <= :createdAtEnd')
            ->setParameter('createdAtEnd', $search)
        ;
    }

    /**
     * Filtre pour le getSearchQueryBuilder permetant de récupérer unquement le dernier fichier envoyé par type de document
     * @return void
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function onlyLastDocumentsByUser(): void
    {
        $connection = $this->_em->getConnection();
        //Requête pour récupérer tous les IDs de la user_kyc_document groupé par type et trié par la date du dernier fichier
        $sql = 'SELECT id 
                FROM user_kyc_document 
                WHERE (user_id, createdAt) IN ( 
                    SELECT user_id, MAX(createdAt) 
                    FROM user_kyc_document GROUP BY user_id, type 
                )'
        ;

        $filteredKycIds = [];
        //Execution de la requête
        $stmt = $connection->prepare($sql);
        //Récupération des IDs des Documents triés
        foreach ($stmt->executeQuery()->fetchAllAssociative() as $document) {
            $filteredKycIds[] = (int) $document['id'];
        }
        //Filtre des KycDocuments par les ids précédements récupérés
        $this->queryBuilder
            ->andWhere('kycDocument.id IN (:kycIds)')
            ->setParameter('kycIds', $filteredKycIds);
    }
    /**
     * End Filters
     */

    public function findDataLatestDocByUserAndType(User $user, string $type)
    {
        $qb = $this->createQueryBuilder('kyc')
            ->select('kyc.status, kyc.type, kyc.refusedReasonMessage')
            ->where('kyc.user = :user')
            ->andWhere('kyc.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('kyc.createdAt', 'DESC')
            ->setMaxResults(1)
        ;

        return $qb->getQuery()->getOneOrNullResult();
    }
}
