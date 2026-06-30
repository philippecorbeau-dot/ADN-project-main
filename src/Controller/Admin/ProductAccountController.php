<?php

namespace App\Controller\Admin;

use App\Entity\User\User;
use App\Entity\ProductAccount;
use App\Entity\Instrument;
use App\Entity\Holding;
use App\Entity\User\Activity as UserActivity;
use App\Form\Admin\ProductAccountType;
use App\Integration\O2S\Service\ContactServiceInterface;
use App\Integration\O2S\Service\CompteServiceInterface;
use App\Integration\O2S\Service\AssetServiceInterface;
use App\Integration\O2S\Sync\O2SSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ProductAccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?O2SSyncService $o2sSyncService = null,
        private readonly ?ContactServiceInterface $contactService = null,
        private readonly ?CompteServiceInterface $compteService = null,
        private readonly ?AssetServiceInterface $assetService = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/admin/produits', name: 'admin_products_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $repo = $this->em->getRepository(ProductAccount::class);
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $qb = $repo->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.id', 'DESC');

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $products = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    #[Route('/admin/produits/nouveau', name: 'admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new ProductAccount();
        $form = $this->createForm(ProductAccountType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Compactage: ajouter les versements additionnels au montant initial puis vider la collection
            $total = (float) $product->getInitialAmount();
            foreach ($product->getContributions() as $c) {
                $total += (float) $c->getAmount();
            }
            $product->setInitialAmount((string) number_format($total, 2, '.', ''));
            // Effacer les contributions (elles ont été intégrées)
            foreach (clone $product->getContributions() as $c) {
                $product->removeContribution($c);
            }

            $this->em->persist($product);
            $this->em->flush();

            // Activity: création d'investissement
            if ($product->getUser()) {
                $activity = new UserActivity();
                $activity->setUser($product->getUser())
                    ->setTitle('Nouvel investissement créé')
                    ->setMessage('Votre produit "' . ($product->getDisplayAlias() ?: $product->getInternalName()) . '" a été créé.')
                    ->setLevel('success');
                $this->em->persist($activity);
                $this->em->flush();
            }
            $this->addFlash('success', 'Produit créé');
            return $this->redirectToRoute('admin_products_edit', ['id' => $product->getId()]);
        }

        return $this->render('admin/product/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/produits/{id}/editer', name: 'admin_products_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $product = $this->em->find(ProductAccount::class, $id);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable');
        }

        $form = $this->createForm(ProductAccountType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Compactage: ajouter les versements additionnels au montant initial puis vider la collection
            $total = (float) $product->getInitialAmount();
            foreach ($product->getContributions() as $c) {
                $total += (float) $c->getAmount();
            }
            $product->setInitialAmount((string) number_format($total, 2, '.', ''));
            foreach (clone $product->getContributions() as $c) {
                $product->removeContribution($c);
            }

            $this->em->flush();
            $this->addFlash('success', 'Produit mis à jour');
            return $this->redirectToRoute('admin_products_edit', ['id' => $product->getId()]);
        }

        $holdings = $this->em->getRepository(Holding::class)->findBy(['productAccount' => $product]);

        // Pour les produits O2S, récupérer les supports depuis l'API
        $o2sSupports = [];
        $o2sError = null;
        $isO2S = $product->getO2sCompteId() !== null;

        if ($isO2S && $this->compteService) {
            try {
                $accountDetails = $this->compteService->getAccountDetails($product->getO2sCompteId());
                $situation = $accountDetails->getSituation();

                // Récupérer les infos des actifs (ISIN, nom complet, etc.)
                $assetIds = [];
                foreach ($situation as $asset) {
                    if ($asset->getAssetId()) {
                        $assetIds[] = $asset->getAssetId();
                    }
                }
                $assetsInfo = [];
                if (!empty($assetIds) && $this->assetService) {
                    try {
                        $assetsInfo = $this->assetService->getAssets($assetIds);
                    } catch (\Throwable $e) {
                        // Pas grave si on n'a pas les infos des actifs
                    }
                }

                foreach ($situation as $asset) {
                    $assetInfo = $assetsInfo[$asset->getAssetId()] ?? null;
                    $isin = $asset->getIsin() ?? $assetInfo?->getIsin();
                    $name = $asset->getAssetName() ?? $assetInfo?->getLabel() ?? $asset->getAssetId() ?? 'Support inconnu';

                    $o2sSupports[] = [
                        'assetId' => $asset->getAssetId(),
                        'isin' => $isin,
                        'name' => $name,
                        'quantity' => $asset->getQuantity(),
                        'netAssetValue' => $asset->getNetAssetValue(),
                        'netAssetValueDate' => $asset->getNetAssetValueDate()?->format('Y-m-d'),
                        'value' => $asset->getValue(),
                        'percentage' => $asset->getPercentage(),
                        'averageBuyPrice' => $asset->getAverageBuyPrice(),
                        'averageBuyPriceDate' => $asset->getAverageBuyPriceDate()?->format('Y-m-d'),
                        'gainLoss' => $asset->getGainLoss(),
                        'gainLossPercent' => $asset->getGainLossPercent(),
                        'assetType' => $asset->getAssetType() ?? $assetInfo?->getAssetType(),
                        'currency' => $asset->getCurrency() ?? 'EUR',
                    ];
                }
            } catch (\Throwable $e) {
                $o2sError = $e->getMessage();
                $this->logger?->warning('Failed to fetch O2S supports for admin', [
                    'productId' => $id,
                    'compteId' => $product->getO2sCompteId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->render('admin/product/edit.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
            'holdings' => $holdings,
            'isO2S' => $isO2S,
            'o2sSupports' => $o2sSupports,
            'o2sError' => $o2sError,
        ]);
    }

    /**
     * Importer les supports O2S en holdings locaux.
     */
    #[Route('/admin/produits/{id}/import-o2s-supports', name: 'admin_products_import_o2s', methods: ['POST'])]
    public function importO2sSupports(int $id, Request $request): Response
    {
        $product = $this->em->find(ProductAccount::class, $id);
        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], 404);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('import_o2s', $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], 400);
        }

        if (!$product->getO2sCompteId() || !$this->compteService) {
            return $this->json(['error' => 'Ce produit n\'est pas lié à O2S'], 400);
        }

        try {
            $accountDetails = $this->compteService->getAccountDetails($product->getO2sCompteId());
            $situation = $accountDetails->getSituation();

            if (empty($situation)) {
                return $this->json(['error' => 'Aucun support trouvé dans O2S pour ce compte'], 400);
            }

            // Récupérer les infos des actifs
            $assetIds = [];
            foreach ($situation as $asset) {
                if ($asset->getAssetId()) {
                    $assetIds[] = $asset->getAssetId();
                }
            }
            $assetsInfo = [];
            if (!empty($assetIds) && $this->assetService) {
                try {
                    $assetsInfo = $this->assetService->getAssets($assetIds);
                } catch (\Throwable) {}
            }

            $instrumentRepo = $this->em->getRepository(Instrument::class);
            $holdingRepo = $this->em->getRepository(Holding::class);
            $imported = 0;
            $skipped = 0;

            foreach ($situation as $asset) {
                $assetInfo = $assetsInfo[$asset->getAssetId()] ?? null;
                $isin = $asset->getIsin() ?? $assetInfo?->getIsin();
                $name = $asset->getAssetName() ?? $assetInfo?->getLabel() ?? $asset->getAssetId() ?? 'Support inconnu';
                $symbol = $isin ?: ($asset->getAssetId() ?? 'O2S_' . uniqid());

                // Chercher un instrument existant par ISIN ou par symbol
                $instrument = null;
                if ($isin) {
                    $instrument = $instrumentRepo->findOneBy(['isin' => $isin]);
                }
                if (!$instrument) {
                    $instrument = $instrumentRepo->findOneBy(['symbol' => $symbol]);
                }

                // Créer l'instrument si nécessaire
                if (!$instrument) {
                    $instrument = new Instrument();
                    $instrument->setSymbol($symbol);
                    $instrument->setName($name);
                    $instrument->setExchange('O2S');
                    $instrument->setCurrency($asset->getCurrency() ?? 'EUR');
                    $instrument->setIsin($isin);
                    $this->em->persist($instrument);
                } else {
                    // Mettre à jour le nom si nécessaire
                    if ($name && $instrument->getName() !== $name) {
                        $instrument->setName($name);
                    }
                }

                // Vérifier si un holding existe déjà pour ce produit et cet instrument
                $existingHolding = $holdingRepo->findOneBy([
                    'productAccount' => $product,
                    'instrument' => $instrument,
                ]);

                if ($existingHolding) {
                    // Mettre à jour le holding existant
                    $existingHolding->setUnits($asset->getQuantity() !== null ? (string) $asset->getQuantity() : null);
                    $existingHolding->setLastPrice($asset->getNetAssetValue() !== null ? (string) $asset->getNetAssetValue() : null);
                    $existingHolding->setLastPriceDate($asset->getNetAssetValueDate() ? \DateTime::createFromImmutable($asset->getNetAssetValueDate()) : null);
                    $existingHolding->setAmount($asset->getValue() !== null ? (string) number_format($asset->getValue(), 2, '.', '') : null);
                    $existingHolding->setBuyPrice($asset->getAverageBuyPrice() !== null ? (string) $asset->getAverageBuyPrice() : null);
                    $existingHolding->setBuyDate($asset->getAverageBuyPriceDate() ? \DateTime::createFromImmutable($asset->getAverageBuyPriceDate()) : null);
                    $skipped++;
                } else {
                    // Créer un nouveau holding
                    $holding = new Holding();
                    $holding->setProductAccount($product);
                    $holding->setInstrument($instrument);
                    $holding->setUnits($asset->getQuantity() !== null ? (string) $asset->getQuantity() : null);
                    $holding->setLastPrice($asset->getNetAssetValue() !== null ? (string) $asset->getNetAssetValue() : null);
                    $holding->setLastPriceDate($asset->getNetAssetValueDate() ? \DateTime::createFromImmutable($asset->getNetAssetValueDate()) : null);
                    $holding->setAmount($asset->getValue() !== null ? (string) number_format($asset->getValue(), 2, '.', '') : null);
                    $holding->setBuyPrice($asset->getAverageBuyPrice() !== null ? (string) $asset->getAverageBuyPrice() : null);
                    $holding->setBuyDate($asset->getAverageBuyPriceDate() ? \DateTime::createFromImmutable($asset->getAverageBuyPriceDate()) : null);
                    $this->em->persist($holding);
                    $imported++;
                }
            }

            $this->em->flush();

            // Activity log
            if ($product->getUser()) {
                $activity = new UserActivity();
                $activity->setUser($product->getUser())
                    ->setTitle('Supports importés depuis O2S')
                    ->setMessage(sprintf('%d support(s) importé(s), %d mis à jour depuis Harvest/O2S.', $imported, $skipped))
                    ->setLevel('info');
                $this->em->persist($activity);
                $this->em->flush();
            }

            return $this->json([
                'success' => true,
                'imported' => $imported,
                'updated' => $skipped,
                'message' => sprintf('%d support(s) importé(s), %d mis à jour.', $imported, $skipped),
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to import O2S supports', [
                'productId' => $id,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Erreur lors de l\'import: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/admin/produits/{id}/ajouter-support', name: 'admin_products_add_support', methods: ['POST'])]
    public function addSupport(int $id, Request $request): Response
    {
        $product = $this->em->find(ProductAccount::class, $id);
        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], 404);
        }

        // CSRF
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_support', $token)) {
            return $this->json(['error' => 'Token CSRF invalide'], 400);
        }

        $symbol = trim((string) $request->request->get('symbol'));
        $exchange = trim((string) $request->request->get('exchange'));
        $currency = trim((string) $request->request->get('currency'));
        $name = trim((string) $request->request->get('name'));
        $isin = $request->request->get('isin');

        if ($symbol === '' || $exchange === '') {
            return $this->json(['error' => 'Symbol et exchange requis'], 400);
        }

        $instrumentRepo = $this->em->getRepository(Instrument::class);
        $instrument = $instrumentRepo->findOneBy([
            'symbol' => $symbol,
            'exchange' => $exchange,
        ]);

        if (!$instrument) {
            $instrument = new Instrument();
            $instrument->setSymbol($symbol);
            $instrument->setExchange($exchange);
            $instrument->setCurrency($currency ?: '');
            $instrument->setName($name ?: $symbol);
            $instrument->setIsin($isin ?: null);
            $this->em->persist($instrument);
        }

        $holding = new Holding();
        $holding->setProductAccount($product);
        $holding->setInstrument($instrument);
        $this->em->persist($holding);

        $this->em->flush();

        // Activity: support ajouté
        if ($product->getUser()) {
            $activity = new UserActivity();
            $activity->setUser($product->getUser())
                ->setTitle('Nouveau support ajouté')
                ->setMessage('Un support "' . ($instrument->getName() ?? $instrument->getSymbol()) . '" a été ajouté à votre produit.')
                ->setLevel('info');
            $this->em->persist($activity);
            $this->em->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/admin/produits/{productId}/supports/{holdingId}/supprimer', name: 'admin_products_remove_holding', methods: ['POST'])]
    public function removeHolding(int $productId, int $holdingId, Request $request): Response
    {
        // Vérifier si c'est une requête AJAX
        $isAjax = $request->isXmlHttpRequest() || $request->query->get('ajax') === '1';
        
        // CSRF
        $token = $isAjax 
            ? (string) ($request->request->get('_token') ?? $request->headers->get('X-CSRF-Token'))
            : (string) $request->request->get('_token');
            
        if (!$this->isCsrfTokenValid('remove_holding', $token)) {
            if ($isAjax) {
                return $this->json(['error' => 'Token CSRF invalide'], 400);
            }
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_products_edit', ['id' => $productId]);
        }

        $product = $this->em->find(ProductAccount::class, $productId);
        if (!$product) {
            if ($isAjax) {
                return $this->json(['error' => 'Produit introuvable'], 404);
            }
            $this->addFlash('error', 'Produit introuvable.');
            return $this->redirectToRoute('admin_products_index');
        }
        
        $holding = $this->em->find(\App\Entity\Holding::class, $holdingId);
        if (!$holding || $holding->getProductAccount()?->getId() !== $productId) {
            if ($isAjax) {
                return $this->json(['error' => 'Support introuvable'], 404);
            }
            $this->addFlash('error', 'Support introuvable.');
            return $this->redirectToRoute('admin_products_edit', ['id' => $productId]);
        }
        
        // Sauvegarder le nom du support pour l'activité
        $instrumentName = $holding->getInstrument()->getName() ?? $holding->getInstrument()->getSymbol();
        
        // Supprimer le holding
        $this->em->remove($holding);
        $this->em->flush();
        
        // Activity: support supprimé
        if ($product->getUser()) {
            $activity = new UserActivity();
            $activity->setUser($product->getUser())
                ->setTitle('Support supprimé')
                ->setMessage('Le support "' . $instrumentName . '" a été retiré de votre produit.')
                ->setLevel('warning');
            $this->em->persist($activity);
            $this->em->flush();
        }
        
        if ($isAjax) {
            return $this->json([
                'success' => true,
                'message' => 'Support supprimé avec succès',
                'holdingId' => $holdingId
            ]);
        }
        
        $this->addFlash('success', 'Support supprimé avec succès.');
        return $this->redirectToRoute('admin_products_edit', ['id' => $productId]);
    }

    #[Route('/admin/produits/supports/{holdingId}/modifier', name: 'admin_products_update_holding', methods: ['POST'])]
    public function updateHolding(int $holdingId, Request $request): Response
    {
        $holding = $this->em->find(\App\Entity\Holding::class, $holdingId);
        if (!$holding) {
            return $this->json(['error' => 'Support introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Données invalides'], 400);
        }

        // Mise à jour du cours
        if (isset($data['lastPrice'])) {
            $price = $data['lastPrice'] === '' || $data['lastPrice'] === null ? null : (string) $data['lastPrice'];
            $holding->setLastPrice($price);
        }

        // Mise à jour de la date du cours
        if (isset($data['lastPriceDate'])) {
            $date = $data['lastPriceDate'] === '' || $data['lastPriceDate'] === null ? null : new \DateTime($data['lastPriceDate']);
            $holding->setLastPriceDate($date);
        }

        // Mise à jour du nombre de parts
        if (isset($data['units'])) {
            $units = $data['units'] === '' || $data['units'] === null ? null : (string) $data['units'];
            $holding->setUnits($units);
        }

        // Recalcul du montant si cours et parts sont disponibles
        if ($holding->getLastPrice() && $holding->getUnits()) {
            $amount = (float) $holding->getLastPrice() * (float) $holding->getUnits();
            $holding->setAmount((string) number_format($amount, 2, '.', ''));
        } else {
            $holding->setAmount(null);
        }

        $this->em->flush();

        // Activity: support modifié
        if ($holding->getProductAccount() && $holding->getProductAccount()->getUser()) {
            $activity = new UserActivity();
            $activity->setUser($holding->getProductAccount()->getUser())
                ->setTitle('Support modifié')
                ->setMessage('Les informations du support "' . ($holding->getInstrument()->getName() ?? $holding->getInstrument()->getSymbol()) . '" ont été mises à jour.')
                ->setLevel('info');
            $this->em->persist($activity);
            $this->em->flush();
        }

        return $this->json([
            'success' => true,
            'amount' => $holding->getAmount(),
        ]);
    }

    // ==================== O2S INTEGRATION ====================

    #[Route('/admin/o2s/clients', name: 'admin_o2s_clients', methods: ['GET'])]
    public function o2sClients(Request $request): Response
    {
        // Récupérer tous les utilisateurs avec ou sans liaison O2S
        $qb = $this->em->getRepository(User::class)->createQueryBuilder('u');
        
        $filter = $request->query->get('filter', 'all');
        
        if ($filter === 'linked') {
            $qb->where('u.o2sContactId IS NOT NULL');
        } elseif ($filter === 'unlinked') {
            $qb->where('u.o2sContactId IS NULL');
        }
        
        $qb->orderBy('u.lastName', 'ASC')
           ->addOrderBy('u.firstName', 'ASC');
        
        $users = $qb->getQuery()->getResult();
        
        // Statistiques
        $totalUsers = $this->em->getRepository(User::class)->count([]);
        $linkedUsers = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalProducts = $this->em->getRepository(ProductAccount::class)->count([]);
        $o2sProducts = $this->em->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.o2sCompteId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->render('admin/o2s/clients.html.twig', [
            'users' => $users,
            'filter' => $filter,
            'stats' => [
                'totalUsers' => $totalUsers,
                'linkedUsers' => $linkedUsers,
                'totalProducts' => $totalProducts,
                'o2sProducts' => $o2sProducts,
            ],
        ]);
    }

    #[Route('/admin/o2s/client/{id}', name: 'admin_o2s_client_detail', methods: ['GET'])]
    public function o2sClientDetail(int $id): Response
    {
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }
        
        $products = $this->em->getRepository(ProductAccount::class)->findBy(['user' => $user]);
        
        // Récupérer le contact O2S si lié
        $o2sContact = null;
        if ($user->getO2sContactId() && $this->contactService) {
            try {
                $o2sContact = $this->contactService->getContact($user->getO2sContactId());
            } catch (\Throwable $e) {
                // Ignorer si O2S indisponible
            }
        }
        
        return $this->render('admin/o2s/client_detail.html.twig', [
            'user' => $user,
            'products' => $products,
            'o2sContact' => $o2sContact,
        ]);
    }

    #[Route('/admin/o2s/client/{id}/link', name: 'admin_o2s_client_link', methods: ['POST'])]
    public function o2sLinkClient(int $id, Request $request): Response
    {
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }
        
        $o2sContactId = $request->request->get('o2sContactId');
        if (!$o2sContactId) {
            return $this->json(['error' => 'ID contact O2S requis'], 400);
        }
        
        // Vérifier que le contact existe
        if ($this->contactService) {
            try {
                $contact = $this->contactService->getContact($o2sContactId);
                if (!$contact) {
                    return $this->json(['error' => 'Contact O2S introuvable'], 404);
                }
            } catch (\Throwable $e) {
                return $this->json(['error' => 'Erreur O2S: ' . $e->getMessage()], 500);
            }
        }
        
        $user->setO2sContactId($o2sContactId);
        $user->setO2sSyncedAt(new \DateTimeImmutable());
        $this->em->flush();
        
        return $this->json(['success' => true, 'message' => 'Utilisateur lié au contact O2S']);
    }

    #[Route('/admin/o2s/client/{id}/sync', name: 'admin_o2s_client_sync', methods: ['POST'])]
    public function o2sSyncClient(int $id): Response
    {
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }
        
        if (!$user->getO2sContactId()) {
            return $this->json(['error' => 'Utilisateur non lié à O2S'], 400);
        }
        
        if (!$this->o2sSyncService) {
            return $this->json(['error' => 'Service O2S non disponible'], 500);
        }
        
        try {
            $result = $this->o2sSyncService->syncComptesForUser($user);
            $this->em->flush();
            
            return $this->json([
                'success' => true,
                'result' => $result->toArray(),
                'message' => sprintf('Synchronisation terminée: %d créés, %d mis à jour', 
                    $result->getCreated(), $result->getUpdated()),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur sync: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/admin/o2s/contacts', name: 'admin_o2s_contacts_search', methods: ['GET'])]
    public function o2sContactsSearch(Request $request): JsonResponse
    {
        if (!$this->contactService) {
            return $this->json(['error' => 'Service O2S non disponible'], 500);
        }
        
        $query = $request->query->get('q', '');
        
        try {
            $contacts = $this->contactService->getContacts([], 50);
            
            // Filtrer par nom si requête
            if ($query) {
                $contacts = array_filter($contacts, function($c) use ($query) {
                    $name = strtolower($c->getFullName());
                    $email = strtolower($c->getEmail() ?? '');
                    $search = strtolower($query);
                    return str_contains($name, $search) || str_contains($email, $search);
                });
            }
            
            $results = [];
            foreach ($contacts as $contact) {
                $results[] = [
                    'id' => $contact->getId(),
                    'name' => $contact->getFullName(),
                    'email' => $contact->getEmail(),
                ];
            }
            
            return $this->json(['contacts' => array_values($results)]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/o2s/sync-all', name: 'admin_o2s_sync_all', methods: ['POST'])]
    public function o2sSyncAll(): JsonResponse
    {
        if (!$this->o2sSyncService) {
            return $this->json(['error' => 'Service O2S non disponible'], 500);
        }
        
        try {
            $results = $this->o2sSyncService->syncAll();
            
            return $this->json([
                'success' => true,
                'contacts' => $results['contacts']->toArray(),
                'comptes' => $results['comptes']->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}


