<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 *
 * @method Stock|null find($id, $lockMode = null, $lockVersion = null)
 * @method Stock|null findOneBy(array $criteria, array $orderBy = null)
 * @method Stock[]    findAll()
 * @method Stock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function save(Stock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Stock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySymbol(string $symbol): ?Stock
    {
        return $this->findOneBy(['symbol' => $symbol]);
    }

    public function findActiveStocks(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.symbol', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function updateStockData(string $symbol, array $data): void
    {
        $stock = $this->findBySymbol($symbol);
        
        if (!$stock) {
            $stock = new Stock();
            $stock->setSymbol($symbol);
            $stock->setCreatedAt(new \DateTimeImmutable());
        }

        $stock->setName($data['name']);
        $stock->setPrice($data['price']);
        $stock->setChange($data['change']);
        $stock->setChangePercent($data['changePercent']);
        $stock->setVolume($data['volume']);
        $stock->setHigh($data['high']);
        $stock->setLow($data['low']);
        $stock->setOpen($data['open']);
        $stock->setPreviousClose($data['previousClose']);
        $stock->setIsPositive($data['isPositive']);
        $stock->setUpdatedAt(new \DateTimeImmutable());

        $this->save($stock, true);
    }
} 