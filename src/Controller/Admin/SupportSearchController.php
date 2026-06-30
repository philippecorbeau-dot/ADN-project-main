<?php

namespace App\Controller\Admin;

use App\Service\MarketData\TwelveDataClient;
use App\Service\MarketData\QuoteAggregator;
use App\Service\MarketData\ReferenceDataClient;
use App\Service\MarketData\FundNavClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\ProductAccount;
use App\Entity\Instrument;
use App\Entity\Holding;

#[IsGranted('ROLE_ADMIN')]
class SupportSearchController extends AbstractController
{
    public function __construct(
        private readonly TwelveDataClient $twelveDataClient,
        private readonly QuoteAggregator $quoteAggregator,
        private readonly ReferenceDataClient $referenceDataClient,
        private readonly FundNavClient $fundNavClient,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/admin/recherche-support', name: 'admin_support_search_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $productId = $request->query->getInt('product');
        $products = $this->em->getRepository(\App\Entity\ProductAccount::class)
            ->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->render('admin/support_search/index.html.twig', [
            'products' => $products,
            'productId' => $productId > 0 ? $productId : null,
            'csrf' => $this->container->get('security.csrf.token_manager')->getToken('add_support')->getValue(),
        ]);
    }

    #[Route('/admin/recherche-support/api', name: 'admin_support_search_api', methods: ['GET'])]
    public function api(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $exchange = $request->query->get('exchange');
        $exchange = $exchange ? strtoupper((string) $exchange) : null;
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $page = max(1, $request->query->getInt('page', 1));
        // Pour l'UI: quand des ids de produits sont fournis, renvoyer leurs quotes en lot
        $symbolsParam = $request->query->get('symbols');

        if ($q === '' && !$symbolsParam) {
            // Pré-chargement: liste curatée par bourse
            if ($exchange) {
                // Mode rapide: ne résoudre QUE la page affichée pour limiter les appels
                $symbols = $this->twelveDataClient->getCuratedSymbols($exchange);
                $total = count($symbols);
                $offset = ($page - 1) * $limit;
                $slice = array_slice($symbols, $offset, $limit);
                $rows = [];
                foreach ($slice as $sym) {
                    $row = ['symbol' => $sym, 'exchange' => $exchange];
                    $isIsin = (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($sym));
                    if ($isIsin) {
                        try {
                            $matches = $this->twelveDataClient->searchSymbols($sym, null);
                            if (!empty($matches)) {
                                usort($matches, function($a,$b){
                                    $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                                    $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                                    return $aw <=> $bw;
                                });
                                $best = $matches[0];
                                $altSymbol = $best['symbol'] ?? null;
                                $altEx = $best['exchange'] ?? null;
                                if ($altSymbol) {
                                    $row['symbol'] = $altSymbol;
                                    if (!empty($altEx)) $row['exchange'] = $altEx;
                                    $series = $this->twelveDataClient->getTimeSeries($altSymbol, '1day', '1');
                                    if (!empty($series)) {
                                        $first = $series[0];
                                        $row['nav'] = isset($first['close']) ? (float)$first['close'] : null;
                                        $row['navDate'] = $first['datetime'] ?? null;
                                    }
                                }
                                $row['isin'] = $sym;
                                if (!empty($best['type'])) $row['type'] = $best['type'];
                                if (!empty($best['currency'])) $row['currency'] = $best['currency'];
                                if (!empty($best['name'])) $row['name'] = $best['name'];
                            }
                        } catch (\Throwable $e) {}
                    } else {
                        try {
                            $qv = $this->twelveDataClient->getQuote($sym);
                            $hasQuote = is_array($qv);
                            if ($hasQuote) {
                                $close = $qv['close'] ?? null;
                                if ($close === null || $close === '' || !is_numeric($close)) {
                                    // Fallback sur previous_close si close indisponible
                                    $close = $qv['previous_close'] ?? null;
                                }
                                if ($close !== null && $close !== '' && is_numeric($close)) {
                                    $row['nav'] = (float) $close;
                                }
                                if (empty($row['name']) && !empty($qv['name'])) {
                                    $row['name'] = (string) $qv['name'];
                                }
                                // Twelve Data renvoie parfois "datetime"
                                if (!empty($qv['datetime'])) {
                                    $row['navDate'] = (string) $qv['datetime'];
                                }
                            }
                            // Si pas de "close" exploitable, fallback time_series (1 point)
                            if (empty($row['nav'])) {
                                $series = $this->twelveDataClient->getTimeSeries($sym, '1day', '1');
                                if (!empty($series)) {
                                    $first = $series[0];
                                    $row['nav'] = isset($first['close']) ? (float) $first['close'] : null;
                                    if (!empty($first['datetime'])) {
                                        $row['navDate'] = $first['datetime'];
                                    }
                                }
                            }
                            // Ultime fallback de date si rien n'a été déterminé
                            if (empty($row['navDate'])) {
                                $row['navDate'] = date('Y-m-d');
                            }
                        } catch (\Throwable $e) {}
                    }
                    $ref = $this->referenceDataClient->resolve($row['symbol'], $exchange);
                    if (!empty($ref['isin']) && empty($row['isin'])) $row['isin'] = $ref['isin'];
                    if (!empty($ref['type']) && empty($row['type'])) $row['type'] = $ref['type'];
                    if (!empty($ref['name']) && (empty($row['name']) || $row['name'] === $row['symbol'])) $row['name'] = $ref['name'];
                    // Dernier filet pour le libellé: utiliser le symbole s'il n'y a toujours pas de nom
                    if (empty($row['name'])) { $row['name'] = $row['symbol']; }
                    // Consolidation finale via agrégateur (assure nav/navDate même si les endpoints bruts ne répondent pas)
                    try {
                        $qAgg = $this->quoteAggregator->getLast($row['symbol'], $row['exchange'] ?? $exchange);
                        if (isset($qAgg['nav']) && $qAgg['nav'] !== null) {
                            $row['nav'] = (float) $qAgg['nav'];
                        }
                        if (!empty($qAgg['navDate'])) {
                            $row['navDate'] = $qAgg['navDate'];
                        }
                    } catch (\Throwable $e) {
                        // non bloquant
                    }
                    $rows[] = $row;
                }
                return $this->json(['data' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]]);
            }
            return $this->json(['data' => []]);
        }

        // Mode récupération directe de plusieurs symbols (sélection UI)
        if ($symbolsParam) {
            $symbols = array_filter(array_map('trim', explode(',', (string) $symbolsParam)));
            $result = [];
            foreach ($symbols as $sym) {
                $row = [ 'symbol' => $sym ];
                $qv = $this->quoteAggregator->getLast($sym, $exchange);
                $row['nav'] = $qv['nav'];
                $row['navDate'] = $qv['navDate'];
            $ref = $this->referenceDataClient->resolve($sym, $exchange);
                if (!empty($ref['isin'])) $row['isin'] = $ref['isin'];
                if (!empty($ref['type'])) $row['type'] = $ref['type'];
            if (!empty($ref['name'])) $row['name'] = $ref['name'];
                $row['exchange'] = $exchange;
                $row['currency'] = 'EUR';
                $result[] = $row;
            }
            return $this->json(['data' => $result]);
        }

        $allowed = ['XPAR', 'XAMS', 'XBRU', 'XLIS', 'XDUB', 'FSX'];
        if ($exchange && !in_array($exchange, $allowed, true)) {
            $exchange = null;
        }

        // Si la requête ressemble à un ISIN, tenter d'abord le référentiel local,
        // puis élargir via TwelveData sans filtrer l'exchange (ex: fonds listés sur FSX)
        if (preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($q))) {
            $symbols = $this->referenceDataClient->findSymbolsByIsin($q);
            if (!empty($symbols)) {
                $results = [];
                foreach ($symbols as $sym) {
                    $row = ['symbol' => $sym];
                    $qv = $this->quoteAggregator->getLast($sym, $exchange);
                    $row['nav'] = $qv['nav'];
                    $row['navDate'] = $qv['navDate'];
                    $ref = $this->referenceDataClient->resolve($sym, $exchange);
                    if (!empty($ref['isin'])) $row['isin'] = $ref['isin'];
                    if (!empty($ref['type'])) $row['type'] = $ref['type'];
                    if (!empty($ref['name'])) $row['name'] = $ref['name'];
                    if (($row['type'] ?? '') === 'FUND' && !empty($row['isin'])) {
                        $loc = $this->fundNavClient->getByIsin($row['isin']);
                        if ($loc['nav'] !== null) {
                            $row['nav'] = $loc['nav'];
                            $row['navDate'] = $loc['navDate'];
                        }
                    }
                    $row['exchange'] = $exchange ?: 'EURONEXT';
                    $row['currency'] = 'EUR';
                    $results[] = $row;
                }
                return $this->json(['data' => $results, 'meta' => ['total' => count($results), 'page' => 1, 'limit' => count($results)]]);
            }

            // Fallback: recherche TwelveData par ISIN sans contrainte d'exchange
            $results = $this->twelveDataClient->searchSymbols($q, null);
            if (!empty($results)) {
                // Prioriser les Mutual Funds quand présent
                usort($results, function($a, $b) {
                    $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                    $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                    return $aw <=> $bw;
                });
                foreach ($results as &$it) {
                    $qv = $this->quoteAggregator->getLast($it['symbol'], $it['exchange'] ?? null);
                    $it['nav'] = $qv['nav'];
                    $it['navDate'] = $qv['navDate'];
                    $ref = $this->referenceDataClient->resolve($it['symbol'], $it['exchange'] ?? null);
                    if (!empty($ref['isin']) && empty($it['isin'])) $it['isin'] = $ref['isin'];
                    if (!empty($ref['type']) && empty($it['type'])) $it['type'] = $ref['type'];
                    if (!empty($ref['name']) && (empty($it['name']) || $it['name'] === $it['symbol'])) $it['name'] = $ref['name'];
                }
                $total = count($results);
                $offset = ($page - 1) * $limit;
                $paged = array_slice($results, $offset, $limit);
                return $this->json(['data' => $paged, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]]);
            }
        }

        $results = $this->twelveDataClient->searchSymbols($q, $exchange);
        if (empty($results) && $exchange) {
            // élargir la recherche en ignorant le filtre exchange, puis refiltrer par suffixe
            $broader = $this->twelveDataClient->searchSymbols($q, null);
            if (!empty($broader)) {
                $suffixMap = ['XPAR' => '.PA','XAMS' => '.AS','XBRU' => '.BR','XLIS' => '.LS','XDUB' => '.IR'];
                $suffix = $suffixMap[$exchange] ?? null;
                $results = array_values(array_filter($broader, function ($it) use ($exchange, $suffix) {
                    if ($suffix && isset($it['symbol']) && str_ends_with(strtoupper($it['symbol']), strtoupper($suffix))) return true;
                    if (!empty($it['exchange']) && stripos($it['exchange'], 'euronext') !== false && $suffix) return true;
                    return false;
                }));
                // Si toujours vide après filtrage Euronext, renvoyer les meilleurs résultats globaux (ex: fonds listé sur FSX)
                if (empty($results)) {
                    // Prioriser les Mutual Funds quand présent
                    usort($broader, function($a, $b) {
                        $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                        $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                        return $aw <=> $bw;
                    });
                    // Garder un petit lot pour l'UI
                    $results = array_slice($broader, 0, $limit);
                }
            }
        }
        foreach ($results as &$it) {
            $qv = $this->quoteAggregator->getLast($it['symbol'], $exchange);
            $it['nav'] = $qv['nav'];
            $it['navDate'] = $qv['navDate'];
            $ref = $this->referenceDataClient->resolve($it['symbol'], $exchange);
            if (!empty($ref['isin'])) $it['isin'] = $ref['isin'];
            if (!empty($ref['type'])) $it['type'] = $ref['type'];
            if (!empty($ref['name']) && (empty($it['name']) || $it['name'] === $it['symbol'])) $it['name'] = $ref['name'];

            // Correction date VL pour fonds: privilégier time_series (retourne la vraie date de VL)
            if (!empty($it['type']) && stripos((string) $it['type'], 'fund') !== false) {
                try {
                    $series = $this->twelveDataClient->getTimeSeries($it['symbol'], '1day', '1');
                    if (!empty($series)) {
                        $first = $series[0];
                        if (isset($first['close']) && is_numeric($first['close'])) {
                            $it['nav'] = (float) $first['close'];
                        }
                        if (!empty($first['datetime'])) {
                            $it['navDate'] = (string) $first['datetime'];
                        }
                    }
                } catch (\Throwable $e) {
                    // non bloquant
                }
            }

            // Fallback ISIN→TwelveData si VL absente après enrichissement
            if ((empty($it['nav']) || $it['nav'] === null) && !empty($it['isin'])) {
                try {
                    $matches = $this->twelveDataClient->searchSymbols($it['isin'], null);
                    if (!empty($matches)) {
                        usort($matches, function($a,$b){
                            $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            return $aw <=> $bw;
                        });
                        $alt = $matches[0];
                        $altSymbol = $alt['symbol'] ?? null;
                        $altExchange = $alt['exchange'] ?? null;
                        if ($altSymbol) {
                            $q2 = $this->quoteAggregator->getLast($altSymbol, $altExchange ?: null);
                            if ($q2['nav'] !== null) {
                                $it['nav'] = $q2['nav'];
                                $it['navDate'] = $q2['navDate'];
                                $it['symbol'] = $altSymbol;
                                if (!empty($altExchange)) $it['exchange'] = $altExchange;
                                if (empty($it['type']) && !empty($alt['type'])) $it['type'] = $alt['type'];
                                if (empty($it['currency']) && !empty($alt['currency'])) $it['currency'] = $alt['currency'];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // no-op
                }
            }

            // Fallback SYMBOL→TwelveData si pas d'ISIN ou pas de VL
            if ((empty($it['nav']) || $it['nav'] === null) && !empty($it['symbol'])) {
                try {
                    $matches = $this->twelveDataClient->searchSymbols($it['symbol'], null);
                    if (!empty($matches)) {
                        usort($matches, function($a,$b){
                            $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            return $aw <=> $bw;
                        });
                        $alt = $matches[0];
                        $altSymbol = $alt['symbol'] ?? null;
                        $altExchange = $alt['exchange'] ?? null;
                        if ($altSymbol) {
                            $q2 = $this->quoteAggregator->getLast($altSymbol, $altExchange ?: null);
                            if ($q2['nav'] !== null) {
                                $it['nav'] = $q2['nav'];
                                $it['navDate'] = $q2['navDate'];
                                $it['symbol'] = $altSymbol;
                                if (!empty($altExchange)) $it['exchange'] = $altExchange;
                                if (empty($it['type']) && !empty($alt['type'])) $it['type'] = $alt['type'];
                                if (empty($it['currency']) && !empty($alt['currency'])) $it['currency'] = $alt['currency'];
                                if (empty($it['isin']) && !empty($alt['isin'])) $it['isin'] = $alt['isin'];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // no-op
                }
            }

            // Fallback NAME→TwelveData si toujours pas de VL
            if ((empty($it['nav']) || $it['nav'] === null) && !empty($it['name'])) {
                try {
                    $matches = $this->twelveDataClient->searchSymbols($it['name'], null);
                    if (!empty($matches)) {
                        usort($matches, function($a,$b){
                            $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            return $aw <=> $bw;
                        });
                        $alt = $matches[0];
                        $altSymbol = $alt['symbol'] ?? null;
                        $altExchange = $alt['exchange'] ?? null;
                        if ($altSymbol) {
                            $q2 = $this->quoteAggregator->getLast($altSymbol, $altExchange ?: null);
                            if ($q2['nav'] !== null) {
                                $it['nav'] = $q2['nav'];
                                $it['navDate'] = $q2['navDate'];
                                $it['symbol'] = $altSymbol;
                                if (!empty($altExchange)) $it['exchange'] = $altExchange;
                                if (empty($it['type']) && !empty($alt['type'])) $it['type'] = $alt['type'];
                                if (empty($it['currency']) && !empty($alt['currency'])) $it['currency'] = $alt['currency'];
                                if (empty($it['isin']) && !empty($alt['isin'])) $it['isin'] = $alt['isin'];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // no-op
                }
            }
        }
        $total = count($results);
        $offset = ($page - 1) * $limit;
        $paged = array_slice($results, $offset, $limit);
        return $this->json(['data' => $paged, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]]);
    }

    #[Route('/admin/recherche-support/associer', name: 'admin_support_search_associate', methods: ['POST'])]
    public function associate(Request $request): JsonResponse
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_support', $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], 400);
        }

        $productId = (int) $request->request->get('product');
        $items = $request->request->all('items');
        if ($productId <= 0 || empty($items) || !is_array($items)) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        /** @var ProductAccount|null $product */
        $product = $this->em->find(ProductAccount::class, $productId);
        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], 404);
        }

        $instrumentRepo = $this->em->getRepository(Instrument::class);
        foreach ($items as $it) {
            $symbol = trim((string) ($it['symbol'] ?? ''));
            $exchange = trim((string) ($it['exchange'] ?? ''));
            $currency = trim((string) ($it['currency'] ?? 'EUR'));
            $name = trim((string) ($it['name'] ?? $symbol));
            $isin = isset($it['isin']) ? trim((string) $it['isin']) : null;
            $price = isset($it['price']) ? (float) $it['price'] : null;
            $date = isset($it['date']) && $it['date'] !== '' ? new \DateTime($it['date']) : null;
            $units = isset($it['units']) ? (float) $it['units'] : null;

            if ($symbol === '' || $exchange === '') {
                return $this->json(['error' => 'Données instrument incomplètes'], 400);
            }

            $instrument = $instrumentRepo->findOneBy(['symbol' => $symbol, 'exchange' => $exchange]);
            if (!$instrument) {
                $instrument = new Instrument();
                $instrument->setSymbol($symbol);
                $instrument->setExchange($exchange);
                $instrument->setCurrency($currency ?: '');
                $instrument->setName($name ?: $symbol);
                $instrument->setIsin($isin ?: null);
                $this->em->persist($instrument);
            }

            // Consolidation: si un holding existe déjà pour ce produit et cet instrument, additionner et recalculer le prix moyen
            $holdingRepo = $this->em->getRepository(Holding::class);
            $existing = $holdingRepo->findOneBy(['productAccount' => $product, 'instrument' => $instrument]);
            if ($existing) {
                $prevUnits = (float) ($existing->getUnits() ?? 0);
                $prevCost = (float) ($existing->getBuyCost() ?? 0);
                $addUnits = $units ?? 0.0;
                $addCost = ($units !== null && $price !== null) ? ($units * $price) : 0.0;
                $newUnits = $prevUnits + $addUnits;
                $newCost = $prevCost + $addCost;
                if ($newUnits > 0) {
                    $avg = $newCost / $newUnits;
                    $existing->setBuyPrice((string) $avg);
                }
                if ($addUnits > 0) {
                    $existing->setUnits((string) $newUnits);
                }
                if ($addCost > 0) {
                    $existing->setBuyCost((string) $newCost);
                }
                // Mémoriser la dernière date d'achat si fournie
                if ($date !== null) {
                    $existing->setBuyDate($date);
                }
                // Positionner un prix instantané si souhaité (sera mis à jour par le refresher)
                if ($price !== null) {
                    $existing->setLastPrice((string) $price);
                    $existing->setLastPriceDate($date ?? new \DateTime());
                }
                if ($newUnits > 0 && $price !== null) {
                    $existing->setAmount((string) ($newUnits * $price));
                }
                // pas de persist nécessaire: entity déjà managée
            } else {
                $holding = new Holding();
                $holding->setProductAccount($product);
                $holding->setInstrument($instrument);
                if ($units !== null) { $holding->setUnits((string) $units); }
                // Enregistrer le prix/date d'achat (moyenne initiale = prix d'achat)
                if ($price !== null) {
                    $holding->setBuyPrice((string) $price);
                    $holding->setLastPrice((string) $price); // valeur spot initiale
                }
                if ($date !== null) {
                    $holding->setBuyDate($date);
                    $holding->setLastPriceDate($date);
                }
                if ($units !== null && $price !== null) {
                    $holding->setBuyCost((string) ($units * $price));
                    $holding->setAmount((string) ($units * $price));
                }
                $this->em->persist($holding);
            }
        }

        $this->em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/admin/recherche-support/holdings', name: 'admin_support_search_holdings', methods: ['GET'])]
    public function holdings(Request $request): JsonResponse
    {
        $productId = (int) $request->query->get('product');
        if ($productId <= 0) {
            return $this->json(['items' => []]);
        }
        /** @var ProductAccount|null $product */
        $product = $this->em->find(ProductAccount::class, $productId);
        if (!$product) {
            return $this->json(['items' => []]);
        }
        $items = [];
        foreach ($this->em->getRepository(Holding::class)->findBy(['productAccount' => $product]) as $h) {
            /** @var Holding $h */
            $instrument = $h->getInstrument();
            $price = $h->getLastPrice() !== null ? (float) $h->getLastPrice() : ($h->getBuyPrice() !== null ? (float) $h->getBuyPrice() : null);
            $date = $h->getLastPriceDate() ?: $h->getBuyDate();
            $items[] = [
                'symbol' => $instrument?->getSymbol() ?? '',
                'name' => $instrument?->getName() ?? '',
                'isin' => $instrument?->getIsin() ?? '',
                'exchange' => $instrument?->getExchange() ?? '',
                'currency' => $instrument?->getCurrency() ?? 'EUR',
                'price' => $price,
                'date' => $date ? $date->format('Y-m-d') : '',
                'units' => $h->getUnits() !== null ? (float) $h->getUnits() : 0.0,
            ];
        }
        return $this->json(['items' => $items]);
    }

    #[Route('/admin/recherche-support/update', name: 'admin_support_search_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_support', $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], 400);
        }
        $productId = (int) $request->request->get('product');
        $items = $request->request->all('items');
        if ($productId <= 0 || empty($items) || !is_array($items)) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }
        /** @var ProductAccount|null $product */
        $product = $this->em->find(ProductAccount::class, $productId);
        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], 404);
        }
        $instrumentRepo = $this->em->getRepository(Instrument::class);
        $holdingRepo = $this->em->getRepository(Holding::class);
        foreach ($items as $it) {
            $symbol = trim((string) ($it['symbol'] ?? ''));
            $exchange = trim((string) ($it['exchange'] ?? ''));
            $currency = trim((string) ($it['currency'] ?? 'EUR'));
            $name = trim((string) ($it['name'] ?? $symbol));
            $isin = isset($it['isin']) ? trim((string) $it['isin']) : null;
            $price = isset($it['price']) ? (float) $it['price'] : null;
            $date = isset($it['date']) && $it['date'] !== '' ? new \DateTime($it['date']) : null;
            $units = isset($it['units']) ? (float) $it['units'] : null;

            if ($symbol === '' || $exchange === '') {
                return $this->json(['error' => 'Données instrument incomplètes'], 400);
            }

            $instrument = $instrumentRepo->findOneBy(['symbol' => $symbol, 'exchange' => $exchange]);
            if (!$instrument) {
                $instrument = new Instrument();
                $instrument->setSymbol($symbol);
                $instrument->setExchange($exchange);
                $instrument->setCurrency($currency ?: '');
                $instrument->setName($name ?: $symbol);
                $instrument->setIsin($isin ?: null);
                $this->em->persist($instrument);
            }

            $holding = $holdingRepo->findOneBy(['productAccount' => $product, 'instrument' => $instrument]);
            if (!$holding) {
                $holding = new Holding();
                $holding->setProductAccount($product);
                $holding->setInstrument($instrument);
                $this->em->persist($holding);
            }
            if ($units !== null) { $holding->setUnits((string) $units); }
            if ($price !== null) {
                $holding->setBuyPrice((string) $price);
                $holding->setLastPrice((string) $price);
            }
            if ($date !== null) {
                $holding->setBuyDate($date);
                $holding->setLastPriceDate($date);
            }
            if ($units !== null && $price !== null) {
                $holding->setBuyCost((string) ($units * $price));
                $holding->setAmount((string) ($units * $price));
            }
        }
        $this->em->flush();
        return $this->json(['success' => true]);
    }
}


