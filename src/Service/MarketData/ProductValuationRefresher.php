<?php

namespace App\Service\MarketData;

use App\Entity\Holding;
use App\Entity\ProductAccount;
use Doctrine\ORM\EntityManagerInterface;

class ProductValuationRefresher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuoteAggregator $quotes,
    ) {}

    /** Met à jour lastPrice/lastPriceDate des holdings d'un produit. */
    public function refresh(ProductAccount $product): void
    {
        $repo = $this->em->getRepository(Holding::class);
        /** @var Holding[] $holdings */
        $holdings = $repo->findBy(['productAccount' => $product]);
        $changed = false;
        foreach ($holdings as $h) {
            $instr = $h->getInstrument();
            if (!$instr) { continue; }
            $q = $this->quotes->getLast($instr->getSymbol(), $instr->getExchange());
            if ($q['nav'] !== null) {
                $h->setLastPrice((string) $q['nav']);
                if (!empty($q['navDate'])) {
                    $h->setLastPriceDate(new \DateTime($q['navDate']));
                }
                // Mettre à jour le montant persisté si nombre de parts connu
                $units = $h->getUnits() !== null ? (float) $h->getUnits() : null;
                if ($units !== null) {
                    $h->setAmount((string) ($units * (float) $q['nav']));
                }
                $changed = true;
            }
        }
        if ($changed) {
            $this->em->flush();
        }
    }
}


