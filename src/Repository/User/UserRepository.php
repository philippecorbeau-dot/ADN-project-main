<?php

namespace App\Repository\User;

use App\Entity\User\Control;
use App\Entity\User\Spam;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Driver\PDO\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User\User;
use App\Entity\User\Pro\UboDeclaration;
use App\Entity\User\KycDocument;
use App\Repository\Utils\QueryOptions;

class UserRepository extends ServiceEntityRepository
{
    use QueryOptions;

    private $em;
    private $userProSet = false;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct($registry, User::class);
    }

    public function getSearchQueryBuilder(array $search = [], int $limit = null, array $order = [], $front = false, bool $lazy = false): Query
    {
        $queryBuilder = $this->createQueryBuilder('user');

        $this->filter($queryBuilder, $search);
        $this->setLimit($queryBuilder, $limit);
        $this->setOrder($queryBuilder, $order);

        return $queryBuilder->getQuery();
    }

    public function getCount(): int
    {
        return $queryBuilder = $this
            ->createQueryBuilder('user')
            ->select('COUNT(user)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function findCleanedContacts()
    {
        $emailsExcluded = [];
        $controlList = $this
            ->getEntityManager()
            ->getRepository(Control::class)
            ->findWhereEmailIsNotNull()
        ;

        foreach ($controlList as $control) {
            $emailsExcluded[] = $control->getEmail();
        }

        $spamList = $this
            ->getEntityManager()
            ->getRepository(Spam::class)
            ->findAll()
        ;

        foreach ($spamList as $spam) {
            $emailsExcluded[] = $spam->getEmail();
        }

        $queryBuilder = $this
            ->createQueryBuilder('user');

        $queryBuilder->where('user.email NOT LIKE :yopmail')
            ->andWhere('user.email NOT LIKE :boursoEmail')
            ->andWhere($queryBuilder->expr()->notIn('user.email', $emailsExcluded))
            ->setParameter('yopmail', '%@yopmail%')
            ->setParameter('boursoEmail', 'brs%')
        ;

        return $queryBuilder->getQuery()->getResult();
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
            ->setParameter('email', '%'.trim($search).'%')
        ;
    }

    protected function country(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.country = :country')
            ->setParameter('country', $search)
        ;
    }

    protected function nationality(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.nationality = :nationality')
            ->setParameter('nationality', $search)
        ;
    }

    protected function city(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.city LIKE :city')
            ->setParameter('city', '%'.$search.'%')
        ;
    }

    protected function region(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.region LIKE :region')
            ->setParameter('region', '%'.$search.'%')
        ;
    }

    protected function maritalStatus(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.maritalStatus = :maritalStatus')
            ->setParameter('maritalStatus', $search)
        ;
    }

    protected function profession(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.profession = :profession')
            ->setParameter('profession', $search)
        ;
    }

    protected function type(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.type = :type')
            ->setParameter('type', $search)
        ;
    }

    protected function inProject(string $search): void
    {
        $this->queryBuilder
            ->join('user.projects', 'privateProjects')
            ->andWhere('privateProjects.id = :inProject')
            ->setParameter('inProject', $search)
        ;
    }

    protected function diagnosticOrigin(bool $search): void
    {
        if ($search) {
            $this->queryBuilder
                ->andWhere('user.source = :source')
                ->setParameter('source', 'Diagnostic')
            ;
        } else {
            $this->queryBuilder
                ->andWhere('user.source != :source')
                ->setParameter('source', 'Diagnostic')
            ;
        }
    }

    protected function notInProject(string $search): void
    {
        $queryBuilderIn = $this->createQueryBuilder('user2');

        $this->queryBuilder
            ->andWhere(
                $this->queryBuilder->expr()->notIn(
                    'user.id',
                    $queryBuilderIn
                        ->select('user2.id')
                        ->join('user2.projects', 'privateProjects')
                        ->andWhere('privateProjects.id = '.$search)
                        ->getDQL()
                )
            )
        ;
    }

    protected function kycValidated(string $search): void
    {
        if ($search) {
            $this->queryBuilder
                ->andWhere('user.roles LIKE :kyc_validated')
                ->setParameter('kyc_validated', '%ROLE_USER_IDENTIFIED%')
            ;
        } else {
            $this->queryBuilder
                ->andWhere('user.roles NOT LIKE :kyc_validated')
                ->setParameter('kyc_validated', '%ROLE_USER_IDENTIFIED%')
            ;
        }
    }

    protected function ids(array $search): void
    {
        $this->queryBuilder
            ->andWhere('user.id IN (:ids)')
            ->setParameter('ids', $search)
        ;
    }

    protected function bankAccount(array $search): void
    {
        $this->queryBuilder
            ->leftJoin('user.bankAccount', 'bankAccount')
            ->andWhere('bankAccount.ribStatus = :ribStatus')
            ->setParameter('ribStatus', $search['ribStatus'])
        ;
    }

    protected function howKnown(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.howKnown = :howKnown')
            ->setParameter('howKnown', $search)
        ;
    }

    protected function roles(string $search): void
    {
        $this->queryBuilder
            ->andWhere('user.roles LIKE :role')
            ->setParameter('role', '%' . $search . '%')
        ;
    }

    protected function companyName(string $search): Void
    {
        if (!$this->userProSet) {
            $this->userProSet = true;

            $this->queryBuilder->join('user.pro', 'userPro');
        }

        $this->queryBuilder
            ->andWhere('userPro.companyName LIKE :companyName')
            ->setParameter('companyName', '%'. $search .'%')
        ;
    }

    protected function socialForm(string $search): void
    {
        if (!$this->userProSet) {
            $this->userProSet = true;

            $this->queryBuilder->join('user.pro', 'userPro');
        }

        $this->queryBuilder
            ->andWhere('userPro.socialForm = :socialForm')
            ->setParameter('socialForm', $search)
        ;
    }

    protected function isMarketing(): void
    {
        $this->queryBuilder
            ->andWhere('user.marketing IS NOT NULL')
        ;
    }

    protected function createdFrom(string $dateTime): void
    {
        $this->queryBuilder
            ->andWhere('user.createdAt >= :createdFrom')
            ->setParameter('createdFrom', $dateTime)
        ;
    }

    protected function createdTo(string $dateTime): void
    {
        $this->queryBuilder
            ->andWhere('user.createdAt <= :createdTo')
            ->setParameter('createdTo', $dateTime)
        ;
    }

    protected function updatedFrom(string $dateTime): void
    {
        $this->queryBuilder
            ->andWhere('user.updatedAt >= :updatedFrom')
            ->setParameter('updatedFrom', $dateTime)
        ;
    }

    protected function updatedTo(string $dateTime): void
    {
        $this->queryBuilder
            ->andWhere('user.updatedAt <= :updatedTo')
            ->setParameter('updatedTo', $dateTime)
        ;
    }

    /**
     * End Filters
     */

    /**
     * Global filter
     */

    protected function global(string $search): void
    {
        $search = trim($search);

        $this->queryBuilder
            ->andWhere('user.firstName LIKE :firstname OR user.lastName LIKE :lastname OR user.email LIKE :email')
            ->setParameter('firstname', '%'.$search.'%')
            ->setParameter('lastname', '%'.$search.'%')
            ->setParameter('email', '%'.$search.'%')
        ;
    }

    /**
     * End Global filter
     */

    public function findUsersWaitingValidationCheck(): ?array
    {
        $queryBuilder = $this
            ->createQueryBuilder('user')
            ->leftJoin('user.kycDocuments', 'kyc')
            ->leftJoin('user.pro', 'pro')
            ->leftJoin('pro.uboDeclarations', 'uboDeclaration')
            ->andWhere('user.roles NOT LIKE :roles')
            ->andWhere('kyc.status IN (:statuses) OR uboDeclaration.status IN (:ubo_declaration_statuses)')
            ->andWhere('1 = 1')
            ->setParameter('roles', '%"'.User::ROLE_USER_IDENTIFIED.'"%')
            ->setParameter('statuses', [KycDocument::STATUS_VALIDATION_ASKED, KycDocument::STATUS_REFUSED])
            ->setParameter('ubo_declaration_statuses', [UboDeclaration::STATUS_VALIDATION_ASKED, UboDeclaration::STATUS_REFUSED, UboDeclaration::STATUS_INCOMPLETE])
        ;


        return $queryBuilder->getQuery()->execute();
    }

    public function findInvestedUsers(array $search = []): Query
    {
        $queryBuilder = $this
            ->createQueryBuilder('user')
            ->join('user.investments', 'investments')
            ->andWhere('investments.totalPrice > 0')
        ;

        $this->filter($queryBuilder, $search);

        return $queryBuilder->getQuery();
    }

    public function findInvestedUsersExport(array $search = []): Query
    {
        $queryBuilder = $this
            ->createQueryBuilder('user')
            ->distinct()->join('user.investments', 'investments')
            ->andWhere('investments.totalPrice > 0')
            ->addOrderBy('user.id', 'DESC');
        ;

        $this->filter($queryBuilder, $search);

        return $queryBuilder->getQuery();
    }

    /**
     * TODO: Not important Rewrite dates to add/sub date...
     * Then remove the bundle beberlei/DoctrineExtensions
     */
    public function findUsersByKycStatus($kycStatus, \DateTime $date = null)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', '\DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');

        $queryBuilder = $this->createQueryBuilder('u')
            ->leftJoin('u.kycDocuments', 'um')
            ->join('u.kycDocuments', 'uk')
            ->where('uk.status = :status')
            ->setParameter('status', $kycStatus)
            ->andWhere('uk.type = :type')
            ->setParameter('type', KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF)
            ->andWhere('u.roles NOT LIKE :roles')
            ->setParameter('roles', '%"'.User::ROLE_USER_IDENTIFIED.'"%');

        if (!empty($date)) {
            $queryBuilder
                ->andWhere('YEAR(uk.updatedAt) = :datey')
                ->andWhere('MONTH(uk.updatedAt) = :datem')
                ->andWhere('DAY(uk.updatedAt) = :dated')
                ->setParameter('datey', $date->format('Y'))
                ->setParameter('datem', $date->format('m'))
                ->setParameter('dated', $date->format('d'));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findUsersWhoNotSubmitedLegalDoc(int $days = 7, int $limit = null)
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->join('user.kycDocuments', 'kycDocument') // is this ok ? No KycDoc with id IS NULL will ever be returned if "join" ?
            // ->leftJoin('user.kycDocuments', 'kycDocument')
            ->andWhere('DATE_DIFF(CURRENT_DATE(), user.createdAt) = :nbdays')
            ->setParameter('nbdays', $days)
            ->andWhere('kycDocument.id IS NULL')
            ->orderBy('user.createdAt', 'DESC')
        ;

        if (!empty($limit)) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findUsersNotLoggedSince(int $days = 30, int $limit = null)
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->where('DATE_DIFF(CURRENT_DATE(), user.lastLogin) = :nbdays')
            ->setParameter('nbdays', $days)
            ->orderBy('user.createdAt', 'DESC');

        if (!empty($limit)) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getInvestorsByRegion()
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT SUBSTRING(postal_code, 1, 2) AS zipCode, COUNT(id) AS total
            FROM user
            WHERE country = 'fr'
            GROUP BY zipCode
            ORDER BY zipCode ASC;";

        $stmt = $connection->query($sql);

        return $stmt->fetchAll();
    }

    public function getCountIdentified(\DateTime $date = null)
    {
        $queryBuilder =  $this->createQueryBuilder('user')
            ->select('COUNT(user)')
            ->where('user.roles LIKE :roles')
            ->setParameter('roles', '%"'.User::ROLE_USER_IDENTIFIED.'"%')
        ;

        if(!empty($date)) {
            $queryBuilder
                ->andWhere('YEAR(user.createdAt) = :datey')
                ->andWhere('MONTH(user.createdAt) = :datem')
                ->andWhere('DAY(user.createdAt) = :dated')
                ->setParameter('datey', $date->format('Y'))
                ->setParameter('datem', $date->format('m'))
                ->setParameter('dated', $date->format('d'));
        }


        return $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function getStatsByAge(): array
    {
        $connection = $this->_em->getConnection();
        $sql = "SELECT COUNT(DISTINCT(u.id)) AS total, YEAR(birthday) AS year
            FROM `user` AS u JOIN investment AS i ON i.user_id = u.id
            WHERE 1 GROUP BY YEAR(birthday) ORDER BY year DESC";
        $stmt = $connection->query($sql);
        $data = $stmt->fetchAll();

        $steps = [];
        $ageStep = 10;
        $maxAgeStep = 70;

        $now = (int) (new \DateTime())->format('Y');
        $eighteen = $now - 18;
        $date = $eighteen - 7;

        $levels = [$eighteen, $date];

        for ($i = 0; $i < $maxAgeStep;) {
            $i += $ageStep;
            $levels[] = $date - $i;
        }

        foreach ($levels as $key => $level) {
            if ($key != 0) {
                $range = $levels[$key-1] .'-'. $levels[$key];
                // $steps[$range] = 0;
                $step = '< ' . (string)($now - $levels[$key]);
                $steps[$step] = 0;
                $labels[] = $step;
                foreach ($data as $user) {
                    if ((int)$user['year'] < $levels[$key-1] && (int)$user['year'] > $levels[$key]) {
                        $steps[$step] += $user['total'];
                    }
                }
                $values[] = $steps[$step];
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'steps' => $steps
        ];
    }

    /**
     * Get all distinct users from users and landing page tables
     */
    public function getRegisteredUsers()
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(email) AS total FROM user WHERE email NOT IN ( SELECT email FROM `landing_user` )";
        $stmt = $connection->query($sql);
        $realUsers = $stmt->fetchAll();

        $sql = "SELECT COUNT(email) AS total FROM `landing_user`";
        $stmt = $connection->query($sql);
        $landingUsers = $stmt->fetchAll();

        $total = current($realUsers)['total'] + current($landingUsers)['total'];

        return $total;
    }

    /**
     * Get count of all distinct users from users only
     */
    public function getRegisteredUsersOnly()
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(email) AS total FROM user";
        $stmt = $connection->query($sql);
        $realUsers = $stmt->fetchAll();

        $total = current($realUsers)['total'];

        return $total;
    }

    public function exportQuery()
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT * FROM user";
        $stmt = $connection ->prepare($sql);

        return $stmt->execute();
        ;
    }

    /**
      * Dashboard GB
     * @throws Exception
     */
    public function getStatsAllUsers(\DateTime $from, \DateTime $to = null)
    {
        $connection = $this->_em->getConnection();

        $to = empty($to) ? new \DateTime() : $to;

        $sql = "SELECT COUNT(u.id) AS registered, MONTH(u.created_at) AS month, YEAR(u.created_at) AS year FROM user AS u
            WHERE u.created_at BETWEEN :from AND :to
            GROUP BY MONTH(u.created_at), YEAR(u.created_at)
            ORDER BY year, month ASC;
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d'));
        $stmt->bindValue('to', $to->format('Y-m-d'));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getTodayRegisteredUsers()
    {
        $connection = $this->_em->getConnection();

        $dateEnd = new \DateTime();
        $dateStart = new \DateTime();

        $to = $dateEnd->setTime(23, 59, 59);
        $from = $dateStart->setTime(00, 00, 00);

        $sql = "SELECT COUNT(u.id) AS registered, MONTH(u.created_at) AS month, YEAR(u.created_at) AS year, DATE(u.created_at) AS day FROM user AS u WHERE u.created_at BETWEEN '".$from->format('Y-m-d H:i:s')."' AND '".$to->format('Y-m-d H:i:s')."' GROUP BY MONTH(u.created_at), YEAR(u.created_at), DATE(u.created_at) ORDER BY year, month, day ASC;
        ";
        $stmt = $connection->query($sql);

        return $stmt->fetch();
    }

    public function getStatsFirstInvestment(\DateTime $from, \DateTime $to)
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(i.id) AS investment, MONTH(i.created_at) AS month, YEAR(i.created_at) AS year
            FROM investment AS i
            WHERE id IN (
                SELECT MIN(id) FROM `investment`
                WHERE (created_at > :from AND created_at < :to)
                    AND user_id NOT IN (SELECT user_id FROM investment WHERE created_at < :from)
                GROUP BY user_id
            )
            GROUP BY MONTH(i.created_at), YEAR(i.created_at)
            ORDER BY YEAR(i.created_at), MONTH(i.created_at)
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d'));
        $stmt->bindValue('to', $to->format('Y-m-d'));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getStatsLanding(\DateTime $from, \DateTime $to)
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(email) AS registered, MONTH(l.created_at) AS month, YEAR(l.created_at) AS year
            FROM landing_user AS l
            WHERE l.created_at BETWEEN :from AND :to
            GROUP BY month, year
            ORDER BY year, month
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d'));
        $stmt->bindValue('to', $to->format('Y-m-d'));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getStatsLandingBecomeUsers(\DateTime $from, \DateTime $to)
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(landing_user.email) AS registered, MONTH(landing_user.created_at) AS month, YEAR(landing_user.created_at) AS year
            FROM landing_user
            JOIN user ON user.email = landing_user.email
            WHERE landing_user.created_at BETWEEN :from AND :to
            GROUP BY month, year
            ORDER BY year, month
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d'));
        $stmt->bindValue('to', $to->format('Y-m-d'));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getInvestmentByMonth(\DateTime $from, \DateTime $to)
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT SUM(i.total_price) AS montantTotal, MONTH(i.created_at) AS month, YEAR(i.created_at) AS year
            FROM investment AS i
            WHERE i.created_at BETWEEN :from AND :to AND i.payed_status = 303
            GROUP BY month, year
            ORDER BY year, month
        ";

        $stmt = $connection->prepare($sql);
        $stmt->bindValue('from', $from->format('Y-m-d'));
        $stmt->bindValue('to', $to->format('Y-m-d'));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getTopTenInvestor(bool $crowdfunding = true)
    {
        $table = $crowdfunding ? 'investment' : 'scpi_investment';
        $connection = $this->_em->getConnection();

        $sql = "SELECT SUM(total_price) AS totalPrice, user_id, email, last_name, first_name, image_name 
                FROM `" . $table . "` i
                INNER JOIN user ON i.user_id = user.id 
                GROUP BY user_id 
                ORDER BY `totalPrice` 
                DESC LIMIT 10";

        $stmt = $connection->query($sql);

        return $stmt->fetchAll();
    }

    public function getTopTenRecurrentInvestor(bool $crowdfunding = true)
    {
        $table = $crowdfunding ? 'investment' : 'scpi_investment';
        $connection = $this->_em->getConnection();

        $sql = "SELECT user_id, COUNT(*) AS nb_investment, last_name, first_name, email, image_name, SUM(total_price) AS total_price
            FROM `" . $table . "` i
            INNER JOIN user ON i.user_id = user.id
            WHERE user.id != 2
            GROUP BY user_id
            ORDER BY nb_investment  DESC LIMIT 10";

        $stmt = $connection->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * End dashboard GB
     */

    public function getWalletsToFill(int $limit)
    {
        $qb = $this->createQueryBuilder('user')
            ->join('user.mangopayInfo', 'mangopayInfo')
            ->join('user.investments', 'investments')
            ->leftJoin('user.external', 'external')
            ->where('external.boursorama IS NULL')
            ->andWhere('mangopayInfo.mangopayWalletId IS NOT NULL')
            ->andWhere('user.roles LIKE :roles')
            ->andWhere('(mangopayInfo.balanceDate < :balance_date OR mangopayInfo.balanceDate IS NULL)')
            ->andWhere('investments IS NOT NULL')
            ->groupBy('user.id')
            ->setParameter('roles', '%ROLE_USER_IDENTIFIED%')
            ->setParameter('balance_date', (new \DateTime())->modify('-3 days')->format('Y-m-d'))
            ->setMaxResults($limit);

        return $qb->getQuery()->execute();
    }

    public function getUsersWithoutTaxation(int $year)
    {
        $sql = "SELECT id 
                FROM user 
                WHERE roles LIKE :role 
                AND type = :type
                AND country IS NOT NULL 
                AND id NOT IN (SELECT DISTINCT(user_id) FROM taxation WHERE year = :year)";
        $connection = $this->_em->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->bindValue('role', '%'.User::ROLE_USER_IDENTIFIED.'%');
        $stmt->bindValue('type', User::USER_TYPE_PRIVATE);
        $stmt->bindValue('year', $year);
        $stmt->execute();
        $ids = array_column($stmt->fetchAll(), 'id');

        $qb = $this->createQueryBuilder('user')
            ->andWhere('user.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $qb->getQuery()->execute();
    }

    public function getTotalUsersDistributionSqlQuery()
    {
        return "SELECT COUNT(*) as users
                FROM (
                    SELECT DISTINCT(user.id), user.birthday, FLOOR(DATEDIFF(NOW(), user.birthday) / 365.2425) as age, user.gender as gender, user.profession as profession, user.marital_status as marital_status, user_info.income as income, user_info.earning_amount as earning_amount, user.country as country, 
                        CASE
                          WHEN user_info.earning_amount IS NOT NULL THEN user_info.earning_amount
                          WHEN user_info.income < 30000 THEN 0
                          WHEN user_info.income >= 30000 AND user_info.income < 50000 THEN 1
                          WHEN user_info.income >= 50000 AND user_info.income < 70000 THEN 2
                          WHEN user_info.income >= 70000 THEN 3
                        END as incomes              
                    FROM user LEFT JOIN user_info ON user.info_id = user_info.id WHERE user.type = 100
                ) t0";
    }

    public function totalUsersDistributionByAge(array $ages)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalUsersDistributionSqlQuery()." WHERE birthday IS NOT NULL AND t0.age >= :min AND t0.age < :max";

        for ($i = 0; $i <= count($ages)-2; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':min', $ages[$i]);
            $stmt->bindParam(':max', $ages[$i+1]);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalUsersDistributionByGender(array $genders)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalUsersDistributionSqlQuery()." WHERE t0.gender = :gender";

        foreach ($genders as $gender) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':gender', $gender);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalUsersDistributionByProfession(array $professions)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalUsersDistributionSqlQuery()." WHERE t0.profession = :profession";

        foreach ($professions as $profession) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':profession', $profession);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalUsersDistributionByMaritalStatus(array $statuses)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalUsersDistributionSqlQuery()." WHERE t0.marital_status = :status";

        foreach ($statuses as $key => $status) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':status', $key);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalUsersDistributionByIncome()
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalUsersDistributionSqlQuery()." WHERE t0.incomes = :incomes AND (t0.earning_amount IS NOT NULL OR t0.income IS NOT NULL)";

        for ($i = 0; $i <= 3; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':incomes', $i);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalUsersDistributionByNationality()
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalUsersDistributionSqlQuery()." WHERE t0.country = :country";
        $sql2 = $this->getTotalUsersDistributionSqlQuery()." WHERE t0.country != :country";

        foreach ([$sql, $sql2] as $request) {
            $stmt = $connection->prepare($request);
            $stmt->bindValue(':country', 'FR');
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function getTotalInvestorsDistributionSqlQuery()
    {
        return "SELECT COUNT(*) as investors
                FROM (
                    SELECT DISTINCT(user.id), user.birthday, FLOOR(DATEDIFF(NOW(), user.birthday) / 365.2425) as age, user.gender as gender, user.profession as profession, user.marital_status as marital_status, user_info.income as income, user_info.earning_amount as earning_amount, user.country as country, 
                        CASE
                          WHEN user_info.earning_amount IS NOT NULL THEN user_info.earning_amount
                          WHEN user_info.income < 30000 THEN 0
                          WHEN user_info.income >= 30000 AND user_info.income < 50000 THEN 1
                          WHEN user_info.income >= 50000 AND user_info.income < 70000 THEN 2
                          WHEN user_info.income >= 70000 THEN 3
                        END as incomes              
                    FROM investment JOIN user ON user.id = investment.user_id LEFT JOIN user_info ON user.info_id = user_info.id WHERE user.type = 100
                ) t0";
    }

    public function totalInvestorsDistributionByAge(array $ages)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestorsDistributionSqlQuery()." WHERE birthday IS NOT NULL AND t0.age >= :min AND t0.age < :max";

        for ($i = 0; $i <= count($ages)-2; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':min', $ages[$i]);
            $stmt->bindParam(':max', $ages[$i+1]);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestorsDistributionByGender(array $genders)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestorsDistributionSqlQuery()." WHERE t0.gender = :gender";

        foreach ($genders as $gender) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':gender', $gender);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestorsDistributionByProfession(array $professions)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestorsDistributionSqlQuery()." WHERE t0.profession = :profession";

        foreach ($professions as $profession) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':profession', $profession);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestorsDistributionByMaritalStatus(array $statuses)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestorsDistributionSqlQuery()." WHERE t0.marital_status = :status";

        foreach ($statuses as $key => $status) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':status', $key);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestorsDistributionByIncome()
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestorsDistributionSqlQuery()." WHERE t0.incomes = :incomes AND (t0.earning_amount IS NOT NULL OR t0.income IS NOT NULL)";

        for ($i = 0; $i <= 3; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':incomes', $i);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestorsDistributionByNationality()
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestorsDistributionSqlQuery()." WHERE t0.country = :country";
        $sql2 = $this->getTotalInvestorsDistributionSqlQuery()." WHERE t0.country != :country";

        foreach ([$sql, $sql2] as $request) {
            $stmt = $connection->prepare($request);
            $stmt->bindValue(':country', 'FR', \PDO::PARAM_STR);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestorsDistributionByCountry()
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT COUNT(*) as investors, country
                FROM (
                    SELECT DISTINCT(user.id), country          
                    FROM investment JOIN user ON user.id = investment.user_id 
                    WHERE country IS NOT NULL AND country != 'FR' AND user.type = 100                   
                ) t0 GROUP BY country ORDER BY investors DESC LIMIT 10";

        $stmt = $connection->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getTotalProjectsDistributionSqlQuery()
    {
        return "SELECT COUNT(*) as projects
                FROM (
                    SELECT DISTINCT(product.id), user.birthday, FLOOR(DATEDIFF(NOW(), user.birthday) / 365.2425) as age, user.gender as gender, user.profession as profession, user.marital_status as marital_status, user_info.income as income, user_info.earning_amount as earning_amount,
                        CASE
                          WHEN user_info.earning_amount IS NOT NULL THEN user_info.earning_amount
                          WHEN user_info.income < 30000 THEN 0
                          WHEN user_info.income >= 30000 AND user_info.income < 50000 THEN 1
                          WHEN user_info.income >= 50000 AND user_info.income < 70000 THEN 2
                          WHEN user_info.income >= 70000 THEN 3
                        END as incomes                                     
                    FROM investment JOIN user ON user.id = investment.user_id LEFT JOIN product on product.id = investment.product_id LEFT JOIN user_info ON user.info_id = user_info.id WHERE user.type = 100 
                ) t0";
    }

    public function totalProjectsDistributionByAge(array $ages)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalProjectsDistributionSqlQuery()." WHERE birthday IS NOT NULL AND t0.age >= :min AND t0.age < :max";

        for ($i = 0; $i <= count($ages)-2; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':min', $ages[$i]);
            $stmt->bindParam(':max', $ages[$i+1]);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalProjectsDistributionByGender(array $genders)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalProjectsDistributionSqlQuery()." WHERE t0.gender = :gender";

        foreach ($genders as $gender) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':gender', $gender);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalProjectsDistributionByProfession(array $professions)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalProjectsDistributionSqlQuery()." WHERE t0.profession = :profession";

        foreach ($professions as $profession) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':profession', $profession);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalProjectsDistributionByMaritalStatus(array $statuses)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalProjectsDistributionSqlQuery()." WHERE t0.marital_status = :status";

        foreach ($statuses as $key => $status) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':status', $key);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalProjectsDistributionByIncome()
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalProjectsDistributionSqlQuery()." WHERE t0.incomes = :incomes AND (t0.earning_amount IS NOT NULL OR t0.income IS NOT NULL)";

        for ($i = 0; $i <= 3; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':incomes', $i);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function getTotalInvestmentsDistributionSqlQuery()
    {
        return "SELECT SUM(t0.total_price) 
                FROM (
                    SELECT investment.total_price, user.birthday, FLOOR(DATEDIFF(NOW(), user.birthday) / 365.2425) as age, user.gender as gender, user.profession as profession, user.marital_status as marital_status, user_info.income as income, user_info.earning_amount as earning_amount,
                        CASE
                            WHEN user_info.earning_amount IS NOT NULL THEN user_info.earning_amount
                            WHEN user_info.income < 30000 THEN 0
                            WHEN user_info.income >= 30000 AND user_info.income < 50000 THEN 1
                            WHEN user_info.income >= 50000 AND user_info.income < 70000 THEN 2
                            WHEN user_info.income >= 70000 THEN 3
                        END as incomes     
                    FROM investment JOIN user ON user.id = investment.user_id LEFT JOIN product on product.id = investment.product_id  LEFT JOIN user_info ON user.info_id = user_info.id WHERE user.type = 100 
                ) t0";
    }

    public function totalInvestmentsDistributionByAge(array $ages)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestmentsDistributionSqlQuery()." WHERE birthday IS NOT NULL AND t0.age >= :min AND t0.age < :max";

        for ($i = 0; $i <= count($ages)-2; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':min', $ages[$i]);
            $stmt->bindParam(':max', $ages[$i+1]);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestmentsDistributionByGender(array $genders)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestmentsDistributionSqlQuery()." WHERE t0.gender = :gender";

        foreach ($genders as $gender) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':gender', $gender);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestmentsDistributionByProfession(array $professions)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestmentsDistributionSqlQuery()." WHERE t0.profession = :profession";

        foreach ($professions as $profession) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':profession', $profession);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestmentsDistributionByMaritalStatus(array $statuses)
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestmentsDistributionSqlQuery()." WHERE t0.marital_status = :status";

        foreach ($statuses as $key => $status) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':status', $key);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function totalInvestmentsDistributionByIncome()
    {
        $results = [];
        $connection = $this->_em->getConnection();

        $sql = $this->getTotalInvestmentsDistributionSqlQuery()." WHERE t0.incomes = :incomes AND (t0.earning_amount IS NOT NULL OR t0.income IS NOT NULL)";

        for ($i = 0; $i <= 3; $i++) {
            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':incomes', $i);
            $stmt->execute();
            array_push($results, $stmt->fetchColumn());
        }

        return $results;
    }

    public function investorsDistributionByProducts()
    {
        $sql = "SELECT COUNT(*) AS nb_user, nb_product FROM (
                    SELECT DISTINCT(user.id) AS user_id,i.step_id AS crowdfunding,si.step AS scpi,vefa.step AS vefa,
                    	CASE
                        WHEN i.step_id IS NULL AND si.step IS NULL AND vefa.step IS NULL THEN 0
                        WHEN (i.step_id IS NOT NULL AND si.step IS NULL AND vefa.step IS NULL) OR (i.step_id IS NULL AND si.step IS NOT NULL AND vefa.step IS NULL) OR (i.step_id IS NULL AND si.step IS NULL AND vefa.step IS NOT NULL) THEN 1
                        WHEN (i.step_id IS NOT NULL AND si.step IS NOT NULL AND vefa.step IS NULL) OR (i.step_id IS NOT NULL AND si.step IS NULL AND vefa.step IS NOT NULL) OR (i.step_id IS NULL AND si.step IS NOT NULL AND vefa.step IS NOT NULL) THEN 2
                        WHEN i.step_id IS NOT NULL AND si.step IS NOT NULL AND vefa.step IS NOT NULL THEN 3
                        END AS nb_product
                    FROM user 
                    LEFT JOIN investment i ON i.user_id=user.id 
                    LEFT JOIN scpi_investment si ON si.user_id=user.id 
                    LEFT JOIN vefa_booking vefa ON vefa.user_id=user.id WHERE (i.step_id IN (:crowdfundingFinalSteps) OR i.step_id IS NULL) AND (si.step = :scpiFinalStep OR si.step IS NULL) AND (vefa.step = :vefaFinalStep OR vefa.step IS NULL)
                ) t0 where nb_product != 0 GROUP BY t0.nb_product";

        $connection = $this->_em->getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->bindValue(':crowdfundingFinalSteps', Investment::STEP_ID_FINAL.','.Investment::STEP_ID_FINAL_CARD);
        $stmt->bindValue(':scpiFinalStep', ScpiInvestment::STEP_ID_FINAL);
        $stmt->bindValue(':vefaFinalStep', Booking::STEP_FINAL);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findUsersByMail(string $mail)
    {
        $connection = $this->_em->getConnection();

        $sql = "SELECT id, email
            FROM `user`
            WHERE email LIKE '" . $mail . "%'
            ORDER BY email
            LIMIT 10";

        $stmt = $connection->query($sql);

        return $stmt->fetchAll();
    }

    public function getInvestorsForLcbftExport()
    {
        $queryBuilder = $this
           ->createQueryBuilder('user')
           ->select('
                user.id, 
                user.gender,
                user.firstName,
                user.type,
                user.lastName,
                user.birthday,
                user.birthplace,
                user.nationality,
                user.address,
                user.country,
                user.birthCountry,
                MAX(taxations.type) AS taxcountry,
                pro.siren
            ')
           ->join('user.taxations', 'taxations')
           ->leftJoin('user.pro', 'pro')
           ->where('user.roles LIKE :kyc_validated')
           ->setParameter('kyc_validated', '%'. User::ROLE_USER_IDENTIFIED .'%')
           ->groupBy('taxations.user')
        ;

        return  $queryBuilder->getQuery()->getResult();
    }

    public function getIdentifiedUsers()
    {
        $queryBuilder = $this
            ->createQueryBuilder('user')
            ->where('user.roles LIKE :kyc_validated')
            ->setParameter('kyc_validated', '%'. User::ROLE_USER_IDENTIFIED .'%')
        ;

        return $queryBuilder->getQuery()->getResult();
    }


    public function getInvestorsByProject(Project $project)
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->leftJoin('user.investments', 'investments')
            ->leftJoin('investments.project', 'project')
            ->where('project.id = :project_id')
            ->andWhere('investments.payedStatus = :payed_status_approved')
            ->groupBy('user.id')
            ->setParameter('project_id', $project->getId())
            ->setParameter('payed_status_approved', Investment::PAYED_STATUS_APPROVED)
        ;

        return $queryBuilder->getQuery()->getResult();
    }

    public function getUsersFilledWallet($minBalance = 500): array
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->select('user.email, user.firstName, mangopayInfo.balance')
            ->leftJoin('user.mangopayInfo', 'mangopayInfo')
            ->where('mangopayInfo.balance >= :minBalance')
            ->setParameter('minBalance', $minBalance)
        ;

        return $queryBuilder->getQuery()->getArrayResult();
    }

    public function getTikehauUsers(array $ids)
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->where('user.id in (:ids)')
            ->setParameter('ids', $ids)
        ;

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getTodayRegisteredUsersBoursorama()
    {
        $dateEnd = new \DateTime();
        $dateStart = new \DateTime();

        $to = $dateEnd->setTime(23, 59, 59);
        $from = $dateStart->setTime(00, 00, 00);

        $queryBuilder = $this->createQueryBuilder('user')
            ->select('COUNT(external.id) AS registered, MONTH(user.createdAt) AS month, YEAR(user.createdAt) AS year, DATE(user.createdAt) AS date')
            ->innerJoin('user.external', 'external')
            ->where('user.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('external.boursorama IS NOT NULL')
            ->groupBy('year, month, date')
            ->orderBy('year, month, date')
            ->setParameter('startDate', $from)
            ->setParameter('endDate', $to);

        $query = $queryBuilder->getQuery();
        return $query->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getTodayRegisteredUsersHomunity()
    {
        $dateEnd = new \DateTime();
        $dateStart = new \DateTime();

        $to = $dateEnd->setTime(23, 59, 59);
        $from = $dateStart->setTime(00, 00, 00);

        $queryBuilder = $this->createQueryBuilder('user')
            ->select('COUNT(user.id) AS registered, MONTH(user.createdAt) AS month, YEAR(user.createdAt) AS year, DATE(user.createdAt) AS date')
            ->LeftJoin('user.external', 'external')
            ->where('user.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('external.boursorama IS NULL')
            ->groupBy('year, month, date')
            ->orderBy('year, month, date')
            ->setParameter('startDate', $from)
            ->setParameter('endDate', $to);

        $query = $queryBuilder->getQuery();
        return $query->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getStatsUsersYesterdayBoursorama(\DateTime $from, \DateTime $to = null)
    {
        $queryBuilder = $this->createQueryBuilder('user');
        $queryBuilder->select('COUNT(external.id) AS registered, MONTH(user.createdAt) AS month, YEAR(user.createdAt) AS year')
            ->LeftJoin('user.external', 'external')
            ->andWhere($queryBuilder->expr()->between('user.createdAt', ':from', ':to'))
            ->andWhere('external.boursorama IS NOT NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', empty($to) ? (new \DateTime())->format('Y-m-d') : $to->format('Y-m-d'))
            ->addGroupBy('month')
            ->addGroupBy('year')
            ->orderBy('year', 'ASC')
            ->orderBy('month', 'ASC');

        $query = $queryBuilder->getQuery();
        return $query->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getStatsUserYesterdayHomunity(\DateTime $from, \DateTime $to = null)
    {
        $queryBuilder = $this->createQueryBuilder('user');
        $queryBuilder->select('COUNT(user.id) AS registered, MONTH(user.createdAt) AS month, YEAR(user.createdAt) AS year')
            ->LeftJoin('user.external', 'external')
            ->andWhere($queryBuilder->expr()->between('user.createdAt', ':from', ':to'))
            ->andWhere('external.boursorama IS NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', empty($to) ? (new \DateTime())->format('Y-m-d') : $to->format('Y-m-d'))
            ->addGroupBy('month')
            ->addGroupBy('year')
            ->orderBy('year', 'ASC')
            ->orderBy('month', 'ASC');

        $query = $queryBuilder->getQuery();
        return $query->getOneOrNullResult();
    }

    public function getStatsAllUserLastWeekBoursorama(\DateTime $from, \DateTime $to = null)
    {
        $queryBuilder = $this->createQueryBuilder('user');
        $queryBuilder->select('COUNT(external.id) AS registered, MONTH(user.createdAt) AS month, YEAR(user.createdAt) AS year')
            ->leftJoin('user.external', 'external')
            ->andWhere($queryBuilder->expr()->between('user.createdAt', ':from', ':to'))
            ->andWhere('external.boursorama IS NOT NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', empty($to) ? (new \DateTime())->format('Y-m-d') : $to->format('Y-m-d'))
            ->addGroupBy('month')
            ->addGroupBy('year')
            ->orderBy('year', 'ASC')
            ->orderBy('month', 'ASC');

        $query = $queryBuilder->getQuery();
        $result = $query->getResult();
        return $result ? $result : [['registered' => 0]];
    }

    public function getStatsAllUserLastWeekHomunity(\DateTime $from, \DateTime $to = null)
    {
        $queryBuilder = $this->createQueryBuilder('user');
        $queryBuilder->select('COUNT(user.id) AS registered, MONTH(user.createdAt) AS month, YEAR(user.createdAt) AS year')
            ->leftJoin('user.external', 'external')
            ->andWhere($queryBuilder->expr()->between('user.createdAt', ':from', ':to'))
            ->andWhere('external.boursorama IS NULL')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', empty($to) ? (new \DateTime())->format('Y-m-d') : $to->format('Y-m-d'))
            ->addGroupBy('month')
            ->addGroupBy('year')
            ->orderBy('year', 'ASC')
            ->orderBy('month', 'ASC');

        $query = $queryBuilder->getQuery();
        $result = $query->getResult();
        return $result ? $result : [['registered' => 0]];
    }

    public function findNoTaxationUser(Project $project)
    {
        $now = new \DateTime();
        $conn = $this->em->getConnection();

        $sql  = 'SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, u.last_login 
                FROM investment i, user u 
                WHERE i.user_id = u.id 
                AND i.product_id = ' . $project->getId() . '        
                AND i.user_id NOT IN (
                    SELECT u.id
                    FROM user u, taxation t, investment i
                    WHERE t.user_id = u.id
                    AND t.year = ' . $now->format('Y') . '
                    AND i.user_id = u.id
                    AND i.product_id = ' . $project->getId() . '
                )';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();

        return $data;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getTotalUsersInvest()
    {
        return $this->createQueryBuilder('user')
            ->select('count(user.id)')
            ->leftJoin('user.pro', 'pro')
            ->where('user.roles LIKE :kyc_validated')
            ->setParameter('kyc_validated', '%' . User::ROLE_USER_IDENTIFIED . '%')
            ->getQuery()
            ->getSingleScalarResult()
            ;
    }

    public function getAllUsersInvest(int $start, int $limit)
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->select('user')
            ->leftJoin('user.pro', 'pro')
            ->where('user.roles LIKE :kyc_validated')
            ->setParameter('kyc_validated', '%' . User::ROLE_USER_IDENTIFIED . '%')
            ->setFirstResult($start)
            ->setMaxResults($limit);
        $query = $queryBuilder->getQuery();
        return $query->getResult();
    }

    public function getInvestorsExternalAnd()
    {
        $from = (new \DateTime())->sub(new \DateInterval('P1Y'));

        $qb = $this
            ->createQueryBuilder('user')
            ->select('user.firstName, user.lastName, user.phone, user.email, investment.totalPrice, investment.createdAt, boursorama.id as boursoramaId, project.name as projectName')
            ->innerJoin('user.investments', 'investment')
            ->leftJoin('investment.project', 'project')
            ->leftJoin(Boursorama::class, 'boursorama', Expr\Join::WITH, 'boursorama.investment = investment.id')
            ->where('investment.createdAt > :from')
            ->setParameter('from', $from)
            ->andWhere('investment.payedStatus = :payed')
            ->setParameter('payed', Investment::PAYED_STATUS_APPROVED)
            ->orderBy('investment.createdAt', 'DESC');

        return $qb
            ->getQuery()
            ->getResult();
    }
    /**
     * On récupère les utilisateurs en utilisant les filtres
     */
    public function getUsersByFilters(
        int $page = 1,
        int $nbEltPerPage = 10000,
        string $firstname = '',
        string $lastname = '',
        string $email = '',
        string $howKnown = '',
        string $socialForm = '',
        string $isMarketing = '',
        string $createdFrom = '',
        string $createdTo = '',
        string $country = '',
        string $nationality = '',
        string $city = '',
        string $region = '',
        string $companyName = '',
        string $maritalStatus = '',
        string $profession = '',
        string $type = '',
        string $scope = '',
        string $roles = ''
    ): array
    {
        $db = $this->_em->getConnection()->getWrappedConnection();
        $where = [];
        if ($createdFrom)
            $where[] = " user.created_at >= " . $db->quote($createdFrom);
        if ($createdTo)
            $where[] = "user.created_at <= " . $db->quote($createdTo);
        if ($firstname)
            $where[] = "user.first_name LIKE " . $db->quote("%$firstname%");
        if ($lastname)
            $where[] = "user.last_name LIKE " . $db->quote("%$lastname%");
        if ($email)
            $where[] = "user.email LIKE " . $db->quote("%$email%");
        if ($howKnown)
            $where[] = "user.how_known LIKE " . $db->quote("%$howKnown%");
        if ($socialForm)
            $where[] = "user_pro.social_form LIKE " . $db->quote("%$socialForm%");
        if ($isMarketing)
            $where[] = "user.marketing_id IS NOT NULL";
        if ($country)
            $where[] = "user.country LIKE " . $db->quote("%$country%");
        if ($nationality)
            $where[] = "user.nationality = " . $db->quote("%$nationality%");
        if ($city)
            $where[] = "user.city LIKE " . $db->quote("%$city%");
        if ($region)
            $where[] = "user.region LIKE " . $db->quote("%$region%");
        if ($companyName)
            $where[] = "user_pro.companyName LIKE " . $db->quote("%$companyName%");
        if ($maritalStatus)
            $where[] = "user.marital_status LIKE " . $db->quote("%$maritalStatus%");
        if ($profession)
            $where[] = "user.profession LIKE " . $db->quote("%$profession%");
        if ($type)
            $where[] = "user.type = " . $db->quote("%$type%");
        if ($scope)
            if ($scope == User::SCOPE_HOMUNITY)
                $where[] = "user_external.boursorama IS NULL";
            else
                $where[] = "user_external.boursorama IS NOT NULL";
        if ($roles)
            $where[] = "user.roles LIKE " . $db->quote("%$roles%");
        // requête principale
        $sql = "SELECT
                    user.id,
                    user.last_name,
                    user.first_name,
                    user.email,
                    user.birthday,
                    user.nationality,
                    user.phone,
                    user.address_line1,
                    user.address_line2,
                    user.postal_code,
                    user.city,
                    user.region,
                    user.country,
                    user.created_at,
                    user.last_login,
                    user.enabled,
                    user.roles,
                    user_external.boursorama,
                    user.type,
                    user_info.us_person,
                    user_info.political,
                    user_info.objective,
                    user_info.investmentTerm,
                    user_info.already_invest,
                    user_info.invest_type,
                    user_info.awareness_minimum_amount,
                    user_info.adequacy5,
                    user.marital_status,
                    user.profession,
                    user_mangopay_info.mangopay_id,
                    user_mangopay_info.mangopay_wallet_id,
                    user_info.patrimony_amount,
                    user_info.patrimony,
                    user_info.earning_amount,
                    user_info.income,
                    user_info.thrift_amount,
                    user_info.savingsCapacity,
                    user_info.source_of_founds,
                    user_info.patrimony_percent,
                    user_info.company_owner,
                    user_pro.social_form,
                    user.how_known,
                    user_marketing.utm_source,
                    user_marketing.utm_medium,
                    user_marketing.utm_campaign,
                    user_marketing.utm_content
                FROM user
                    LEFT OUTER JOIN user_pro ON user.pro_id = user_pro.id
                    LEFT OUTER JOIN user_marketing ON user.id = user_marketing.user_id
                    LEFT OUTER JOIN user_external ON user_external.user_id = user.id
                    LEFT OUTER JOIN user_mangopay_info ON user_mangopay_info.id = user.mangopay_info_id
                    LEFT OUTER JOIN user_info ON user.info_id = user_info.id";
        if ($where)
            $sql .= " WHERE " . implode(' AND ', $where);
        $firstElem = ($page - 1) * $nbEltPerPage;
        $sql .= " LIMIT $firstElem, $nbEltPerPage";
        $users = $db->query($sql)->fetchAllAssociative();
        if (!$users)
            return [];
        // réindexation du tableau en utilisant les identifiants des utilisateurs
        $users = array_column($users, null,'id');
        // deuxième requête pour compléter avec les investissements des utilisateurs
        $sql = "SELECT MIN(investment.created_at) AS first_investment_date, 
                       investment.user_id AS userId
                FROM investment
                WHERE investment.user_id IN (" . implode(', ', array_keys($users)) . ")
                  AND investment.payed_status = '" . Investment::PAYED_STATUS_APPROVED . "'
                  AND investment.sign_status = '" . Investment::SIGN_STATUS_APPROVED . "'
                GROUP BY investment.user_id";
        $investments = $db->query($sql);
        foreach ($investments as $investment) {
            $users[$investment['userId']]['first_investment_date'] = $investment['first_investment_date'];
        }
        return $users;
    }

    /**
     * Retourne les utilisateurs sans ID Hubspot
     * @param int $limit
     * @return array
     */
    public function getEmptyHubspotIdUsers(int $limit = 500): ?array
    {
        $qb = $this
            ->createQueryBuilder('user')
            ->where('user.hubspotId IS NULL')
            ->orderBy('user.id', 'DESC')
            ->setMaxResults($limit)
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les utilisateurs avec un ID Hubspot et leur compte mise à jour après le dernier passage de la MAJ Hubspot
     * @return array|null
     */
    public function getUpdatedUsersWithHubspotId(int $limit = 500): ?array
    {
        $qb = $this
            ->createQueryBuilder('user')
            ->where('user.hubspotId IS NOT NULL')
            ->andWhere("user.updatedAt > user.hubspotUpdatedAt OR user.hubspotUpdatedAt IS NULL")
            ->orderBy('user.id', 'DESC')
            ->setMaxResults($limit)
        ;

        return $qb->getQuery()->getResult();
    }
}