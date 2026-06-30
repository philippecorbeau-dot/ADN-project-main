<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use App\Integration\O2S\Service\ContactServiceInterface;
use App\Integration\O2S\Service\CompteServiceInterface;
use App\Integration\O2S\Service\AssetServiceInterface;
use App\Integration\O2S\Service\InstitutionServiceInterface;
use App\Integration\O2S\Service\ProductServiceInterface;
use App\Integration\O2S\Sync\O2SSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration interface for O2S (Harvest) integration.
 * 
 * Provides a complete view of all O2S contacts, accounts, and their synchronization status.
 */
#[Route('/admin/o2s', name: 'admin_o2s_')]
#[IsGranted('ROLE_ADMIN')]
class O2SAdminController extends AbstractController
{
    public function __construct(
        private readonly ContactServiceInterface $contactService,
        private readonly CompteServiceInterface $compteService,
        private readonly AssetServiceInterface $assetService,
        private readonly InstitutionServiceInterface $institutionService,
        private readonly ProductServiceInterface $productService,
        private readonly O2SSyncService $syncService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Dashboard O2S - Vue d'ensemble.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Statistiques locales
        $totalUsers = $this->em->getRepository(User::class)->count([]);
        $linkedUsers = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalProducts = $this->em->getRepository(ProductAccount::class)->count([]);
        $o2sProducts = $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ProductAccount::class, 'p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // Calcul de la valorisation totale O2S
        $totalValuation = $this->em->createQueryBuilder()
            ->select('SUM(p.o2sValuation)')
            ->from(ProductAccount::class, 'p')
            ->where('p.o2sValuation IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // ── Détail par catégorie de comptes ──
        $conn = $this->em->getConnection();
        $categoryBreakdown = $conn->fetchAllAssociative("
            SELECT 
                product_type,
                COUNT(*) as count,
                COALESCE(SUM(o2s_valuation), 0) as total_valuation
            FROM product_accounts
            WHERE o2s_compte_id IS NOT NULL
            GROUP BY product_type
            ORDER BY total_valuation DESC
        ");

        // Classification en catégories
        $gereTypes = [
            'ASSURANCE_VIE', 'PER', 'PEA', 'PEA_PME', 'SCPI',
            'COMPTE_TITRES', 'CAPITALISATION', 'DEFISCALISATION',
            'MADELIN', 'RETRAITE_ENTREPRISE', 'AUTRE',
        ];
        $epargneTypes = ['EPARGNE_SALARIALE', 'PERCO'];
        $agregeTypes = [
            'COMPTE_COURANT', 'LIVRET', 'EPARGNE_LOGEMENT', 'COMPTE_A_TERME',
        ];

        $categories = [
            'gere' => ['label' => 'Gestion directe', 'icon' => 'briefcase', 'color' => 'primary', 'description' => 'Assurance vie, PER, PEA, SCPI… — comptes gérés par le CGP', 'count' => 0, 'valuation' => 0.0, 'types' => []],
            'epargne' => ['label' => 'Épargne salariale', 'icon' => 'building', 'color' => 'info', 'description' => 'PEE, PERCO — épargne entreprise', 'count' => 0, 'valuation' => 0.0, 'types' => []],
            'agrege' => ['label' => 'Comptes agrégés', 'icon' => 'university', 'color' => 'secondary', 'description' => 'Comptes bancaires, livrets, PEL — comptes agrégés depuis les banques', 'count' => 0, 'valuation' => 0.0, 'types' => []],
        ];

        foreach ($categoryBreakdown as $row) {
            $type = $row['product_type'];
            $count = (int) $row['count'];
            $val = (float) $row['total_valuation'];
            $typeInfo = ['type' => $type, 'count' => $count, 'valuation' => $val];

            if (in_array($type, $gereTypes, true)) {
                $categories['gere']['count'] += $count;
                $categories['gere']['valuation'] += $val;
                $categories['gere']['types'][] = $typeInfo;
            } elseif (in_array($type, $epargneTypes, true)) {
                $categories['epargne']['count'] += $count;
                $categories['epargne']['valuation'] += $val;
                $categories['epargne']['types'][] = $typeInfo;
            } elseif (in_array($type, $agregeTypes, true)) {
                $categories['agrege']['count'] += $count;
                $categories['agrege']['valuation'] += $val;
                $categories['agrege']['types'][] = $typeInfo;
            } else {
                // Types non classifiés → gestion directe par défaut
                $categories['gere']['count'] += $count;
                $categories['gere']['valuation'] += $val;
                $categories['gere']['types'][] = $typeInfo;
            }
        }

        return $this->render('admin/o2s/index.html.twig', [
            'stats' => [
                'totalUsers' => $totalUsers,
                'linkedUsers' => (int) $linkedUsers,
                'totalProducts' => $totalProducts,
                'o2sProducts' => (int) $o2sProducts,
                'totalValuation' => (float) $totalValuation,
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Liste tous les contacts O2S synchronisés localement.
     * Utilise uniquement la base de données locale (0 appel API).
     */
    #[Route('/contacts', name: 'contacts', methods: ['GET'])]
    public function contacts(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $search = trim($request->query->get('search', ''));
        $statusFilter = $request->query->get('status', ''); // 'linked', 'unlinked', ''

        // Requête locale — zéro appel API
        $qb = $this->em->getRepository(User::class)
            ->createQueryBuilder('u');
        
        if ($statusFilter === 'linked') {
            $qb->andWhere('u.o2sContactId IS NOT NULL');
        } elseif ($statusFilter === 'unlinked') {
            $qb->andWhere('u.o2sContactId IS NULL');
        }
        
        if ($search !== '') {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.o2sContactId LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        $qb->orderBy('u.lastName', 'ASC');
        
        // Total count
        $countQb = clone $qb;
        $totalCount = (int) $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        
        // Paginated results
        $users = $qb->setFirstResult($offset)
                     ->setMaxResults($limit)
                     ->getQuery()
                     ->getResult();

        // Count comptes per user
        $userAccountCounts = [];
        if (!empty($users)) {
            $userIds = array_map(fn(User $u) => $u->getId(), $users);
            $counts = $this->em->createQueryBuilder()
                ->select('IDENTITY(p.user) as userId, COUNT(p.id) as cnt')
                ->from(ProductAccount::class, 'p')
                ->where('IDENTITY(p.user) IN (:ids)')
                ->setParameter('ids', $userIds)
                ->groupBy('p.user')
                ->getQuery()
                ->getResult();
            foreach ($counts as $row) {
                $userAccountCounts[$row['userId']] = (int) $row['cnt'];
            }
        }

        return $this->render('admin/o2s/contacts.html.twig', [
            'users' => $users,
            'userAccountCounts' => $userAccountCounts,
            'page' => $page,
            'limit' => $limit,
            'totalCount' => $totalCount,
            'totalPages' => (int) ceil($totalCount / $limit),
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * Détail d'un contact O2S avec tous ses comptes.
     */
    #[Route('/contact/{contactId}', name: 'contact_detail', methods: ['GET'])]
    public function contactDetail(string $contactId): Response
    {
        try {
            $contact = $this->contactService->getContact($contactId);
            $comptes = $this->compteService->getComptesForContact($contactId);
            
            // Récupérer les détails de valorisation pour chaque compte
            $comptesWithDetails = [];
            foreach ($comptes as $compte) {
                $details = null;
                try {
                    $details = $this->compteService->getAccountDetails($compte->getId());
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to fetch account details', [
                        'compteId' => $compte->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Vérifier si ce compte est lié localement
                $localProduct = $this->em->getRepository(ProductAccount::class)
                    ->findOneBy(['o2sCompteId' => $compte->getId()]);
                
                $comptesWithDetails[] = [
                    'compte' => $compte,
                    'details' => $details,
                    'localProduct' => $localProduct,
                ];
            }

            // Utilisateur local lié
            $linkedUser = $this->em->getRepository(User::class)
                ->findOneBy(['o2sContactId' => $contactId]);

            // Calculer le total
            $summary = $this->compteService->calculateSummary($comptes);

            return $this->render('admin/o2s/contact_detail.html.twig', [
                'contact' => $contact,
                'comptesWithDetails' => $comptesWithDetails,
                'linkedUser' => $linkedUser,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('admin_o2s_contacts');
        }
    }

    /**
     * Détail d'un compte O2S spécifique avec tous ses actifs.
     */
    #[Route('/compte/{compteId}', name: 'compte_detail', methods: ['GET'])]
    public function compteDetail(string $compteId): Response
    {
        try {
            $compte = $this->compteService->getCompte($compteId);
            $details = $this->compteService->getAccountDetails($compteId);
            
            // Enrichir les lignes d'actifs avec les informations complètes
            $assetsEnriched = [];
            foreach ($details->getSituation() as $assetLine) {
                $assetInfo = null;
                if ($assetLine->getAssetId()) {
                    try {
                        $assetInfo = $this->assetService->getAsset($assetLine->getAssetId());
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
                
                $assetsEnriched[] = [
                    'line' => $assetLine,
                    'info' => $assetInfo,
                ];
            }

            // Produit local lié
            $localProduct = $this->em->getRepository(ProductAccount::class)
                ->findOneBy(['o2sCompteId' => $compteId]);

            return $this->render('admin/o2s/compte_detail.html.twig', [
                'compte' => $compte,
                'details' => $details,
                'assetsEnriched' => $assetsEnriched,
                'localProduct' => $localProduct,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('admin_o2s_contacts');
        }
    }

    /**
     * Liste tous les produits O2S synchronisés localement.
     */
    #[Route('/produits', name: 'products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // Filtres
        $search = trim($request->query->get('search', ''));
        $typeFilter = $request->query->get('type', '');
        $sortBy = $request->query->get('sort', 'valuation');
        $sortDir = $request->query->get('dir', 'DESC');
        
        // Construire la requête de base
        $qb = $this->em->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->leftJoin('p.user', 'u')
            ->addSelect('u');
        
        // Appliquer le filtre de recherche
        if ($search !== '') {
            $qb->andWhere('p.displayAlias LIKE :search OR p.internalName LIKE :search OR u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Appliquer le filtre de type
        if ($typeFilter !== '') {
            $qb->andWhere('p.productType = :type')
               ->setParameter('type', $typeFilter);
        }
        
        // Tri
        $sortField = match ($sortBy) {
            'valuation' => 'p.o2sValuation',
            'date' => 'p.o2sValuationDate',
            'sync' => 'p.o2sSyncedAt',
            'client' => 'u.lastName',
            'name' => 'p.displayAlias',
            default => 'p.o2sValuation',
        };
        $qb->orderBy($sortField, $sortDir === 'ASC' ? 'ASC' : 'DESC');
        
        // Compter le total avant pagination
        $countQb = clone $qb;
        $totalCount = $countQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        
        // Appliquer la pagination
        $products = $qb->setFirstResult($offset)
                       ->setMaxResults($limit)
                       ->getQuery()
                       ->getResult();
        
        // Calculer les totaux
        $totalValuation = $this->em->createQueryBuilder()
            ->select('SUM(p.o2sValuation)')
            ->from(ProductAccount::class, 'p')
            ->where('p.o2sValuation IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Types disponibles pour le filtre
        $availableTypes = $this->em->createQueryBuilder()
            ->select('DISTINCT p.productType')
            ->from(ProductAccount::class, 'p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/o2s/products.html.twig', [
            'products' => $products,
            'page' => $page,
            'limit' => $limit,
            'totalCount' => (int) $totalCount,
            'totalPages' => (int) ceil($totalCount / $limit),
            'totalValuation' => (float) $totalValuation,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'availableTypes' => $availableTypes,
        ]);
    }

    /**
     * Liste toutes les institutions O2S (établissements financiers).
     */
    #[Route('/institutions', name: 'institutions', methods: ['GET'])]
    public function institutions(): Response
    {
        try {
            $institutions = $this->institutionService->getAllInstitutions();
            
            // Compter les produits par institution
            $productsByInstitution = [];
            $o2sProducts = $this->productService->getAllProducts();
            foreach ($o2sProducts as $product) {
                $instId = $product->getInstitutionId() ?? 'UNKNOWN';
                if (!isset($productsByInstitution[$instId])) {
                    $productsByInstitution[$instId] = 0;
                }
                $productsByInstitution[$instId]++;
            }

            return $this->render('admin/o2s/institutions.html.twig', [
                'institutions' => $institutions,
                'productsByInstitution' => $productsByInstitution,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->render('admin/o2s/institutions.html.twig', [
                'institutions' => [],
                'productsByInstitution' => [],
            ]);
        }
    }

    /**
     * Liste tous les produits O2S (types de contrats).
     */
    #[Route('/o2s-products', name: 'o2s_products', methods: ['GET'])]
    public function o2sProducts(): Response
    {
        try {
            $products = $this->productService->getAllProducts();
            $institutionsMap = $this->institutionService->getInstitutionsMap();

            // Grouper par type
            $byType = [];
            foreach ($products as $product) {
                $type = $product->getType() ?? 'AUTRE';
                if (!isset($byType[$type])) {
                    $byType[$type] = [];
                }
                $byType[$type][] = $product;
            }

            return $this->render('admin/o2s/o2s_products.html.twig', [
                'products' => $products,
                'byType' => $byType,
                'institutionsMap' => $institutionsMap,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->render('admin/o2s/o2s_products.html.twig', [
                'products' => [],
                'byType' => [],
                'institutionsMap' => [],
            ]);
        }
    }

    /**
     * Vue détaillée de tous les comptes avec valorisations.
     */
    #[Route('/comptes', name: 'comptes', methods: ['GET'])]
    public function comptes(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        // Filtres
        $search = trim($request->query->get('search', ''));
        $typeFilter = $request->query->get('type', '');

        // Récupérer les comptes locaux O2S avec pagination
        $qb = $this->em->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->leftJoin('p.user', 'u')
            ->addSelect('u');
        
        // Appliquer le filtre de recherche
        if ($search !== '') {
            $qb->andWhere('p.displayAlias LIKE :search OR p.internalName LIKE :search OR u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Appliquer le filtre de type
        if ($typeFilter !== '') {
            $qb->andWhere('p.productType = :type')
               ->setParameter('type', $typeFilter);
        }
        
        $qb->orderBy('p.o2sValuation', 'DESC')
           ->setFirstResult($offset)
           ->setMaxResults($limit);

        $products = $qb->getQuery()->getResult();
        
        // Compter avec les mêmes filtres
        $countQb = $this->em->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.o2sCompteId IS NOT NULL')
            ->leftJoin('p.user', 'u');
        
        if ($search !== '') {
            $countQb->andWhere('p.displayAlias LIKE :search OR p.internalName LIKE :search OR u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
        }
        if ($typeFilter !== '') {
            $countQb->andWhere('p.productType = :type')
                    ->setParameter('type', $typeFilter);
        }
        
        $totalCount = $countQb->getQuery()->getSingleScalarResult();

        // Calculer les totaux (global sans filtres)
        $totalValuation = $this->em->createQueryBuilder()
            ->select('SUM(p.o2sValuation)')
            ->from(ProductAccount::class, 'p')
            ->where('p.o2sValuation IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Types disponibles pour le filtre
        $availableTypes = $this->em->createQueryBuilder()
            ->select('DISTINCT p.productType')
            ->from(ProductAccount::class, 'p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/o2s/comptes.html.twig', [
            'products' => $products,
            'page' => $page,
            'limit' => $limit,
            'totalCount' => (int) $totalCount,
            'totalPages' => (int) ceil($totalCount / $limit),
            'totalValuation' => (float) $totalValuation,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'availableTypes' => $availableTypes,
        ]);
    }

    /**
     * Synchronisation complète O2S (étape par étape via AJAX).
     * Fallback: sync tout en un seul appel (non-AJAX).
     */
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('o2s_sync', $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_o2s_index');
        }

        // Non-AJAX fallback: redirect to sync-contacts step
        $this->addFlash('info', 'Veuillez utiliser le bouton Synchroniser pour lancer la synchronisation.');
        return $this->redirectToRoute('admin_o2s_index');
    }

    /**
     * Étape 1 : Synchroniser les contacts O2S.
     */
    #[Route('/sync/contacts', name: 'sync_contacts', methods: ['POST'])]
    public function syncContacts(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_sync', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        set_time_limit(120);

        try {
            $result = $this->syncService->syncAllContacts();

            return $this->json([
                'success' => true,
                'step' => 'contacts',
                'total' => $result->getTotal(),
                'created' => $result->getCreated(),
                'updated' => $result->getUpdated(),
                'skipped' => $result->getSkipped(),
                'errors' => array_slice($result->getErrors(), 0, 10), // Limiter les erreurs affichées
                'errorCount' => count($result->getErrors()),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'step' => 'contacts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Étape 2 : Synchroniser les comptes O2S par lot.
     * Accepte offset/limit pour le mode batch (appels AJAX successifs).
     */
    #[Route('/sync/comptes', name: 'sync_comptes', methods: ['POST'])]
    public function syncComptes(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_sync', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        set_time_limit(120);

        $offset = (int) $request->request->get('offset', 0);
        $batchSize = (int) $request->request->get('batch_size', 10);
        // Limiter la taille du lot pour rester sous le timeout OVH
        $batchSize = min($batchSize, 20);

        try {
            $batchResult = $this->syncService->syncComptesBatch($offset, $batchSize);
            $result = $batchResult['result'];

            return $this->json([
                'success' => true,
                'step' => 'comptes',
                'total' => $batchResult['total'],
                'processed' => $batchResult['processed'],
                'hasMore' => $batchResult['hasMore'],
                'batchCreated' => $result->getCreated(),
                'batchUpdated' => $result->getUpdated(),
                'batchSkipped' => $result->getSkipped(),
                'errors' => array_slice($result->getErrors(), 0, 5),
                'errorCount' => count($result->getErrors()),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'step' => 'comptes',
                'error' => $e->getMessage(),
                'offset' => $offset,
            ], 500);
        }
    }

    /**
     * Pré-vérification : nombre d'utilisateurs liés (pour afficher la progression).
     */
    #[Route('/sync/info', name: 'sync_info', methods: ['GET'])]
    public function syncInfo(): JsonResponse
    {
        $linkedUsers = $this->syncService->getLinkedUsersCount();
        $totalProducts = $this->em->getRepository(ProductAccount::class)->count([]);

        return $this->json([
            'linkedUsers' => $linkedUsers,
            'totalProducts' => $totalProducts,
        ]);
    }

    /**
     * Lier un contact O2S à un utilisateur local existant.
     */
    #[Route('/link-user', name: 'link_user', methods: ['POST'])]
    public function linkUser(Request $request): JsonResponse
    {
        $contactId = $request->request->get('contactId');
        $userId = $request->request->getInt('userId');

        if (!$contactId || !$userId) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        try {
            $user = $this->em->find(User::class, $userId);
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable'], 404);
            }

            // Vérifier que le contact existe
            $contact = $this->contactService->getContact($contactId);

            // Lier
            $user->setO2sContactId($contactId);
            $user->setO2sSyncedAt(new \DateTimeImmutable());
            $this->em->flush();

            // Synchroniser les comptes de ce contact
            $comptes = $this->compteService->getComptesForContact($contactId);
            foreach ($comptes as $compte) {
                $existing = $this->em->getRepository(ProductAccount::class)
                    ->findOneBy(['o2sCompteId' => $compte->getId()]);
                
                if (!$existing) {
                    $product = new ProductAccount();
                    $product->setUser($user);
                    $product->setO2sCompteId($compte->getId());
                    $product->setInternalName('o2s_' . $compte->getId());
                    $product->setDisplayAlias($compte->getDisplayName());
                    $product->setDistributor('O2S - Harvest');
                    $product->setProductType($this->mapProductType($compte->getProductType()));
                    
                    if ($compte->getMontant() !== null) {
                        $product->setO2sValuation((string) $compte->getMontant());
                    }
                    if ($compte->getDateValeur()) {
                        $product->setO2sValuationDate($compte->getDateValeur());
                    }
                    
                    $product->setO2sSyncedAt(new \DateTimeImmutable());
                    $this->em->persist($product);
                }
            }
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => sprintf('Contact lié à %s avec %d compte(s)', $user->getEmail(), count($comptes)),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Délier un utilisateur de son contact O2S.
     */
    #[Route('/unlink-user/{userId}', name: 'unlink_user', methods: ['POST'])]
    public function unlinkUser(int $userId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('unlink_user', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_o2s_contacts');
        }

        $user = $this->em->find(User::class, $userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable');
            return $this->redirectToRoute('admin_o2s_contacts');
        }

        $user->setO2sContactId(null);
        $user->setO2sSyncedAt(null);
        $this->em->flush();

        $this->addFlash('success', 'Utilisateur délié du contact O2S');
        return $this->redirectToRoute('admin_o2s_contacts');
    }

    /**
     * API : Rechercher des utilisateurs pour l'autocomplétion.
     */
    #[Route('/api/users/search', name: 'api_users_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.email LIKE :q')
            ->orWhere('u.lastName LIKE :q')
            ->orWhere('u.firstName LIKE :q')
            ->andWhere('u.o2sContactId IS NULL') // Seulement les non-liés
            ->setParameter('q', '%' . $q . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
            ];
        }

        return $this->json($results);
    }

    /**
     * Synchronisation incrémentale rapide via l'interface admin.
     * Ne synchronise que les nouveaux contacts/comptes.
     */
    #[Route('/sync/incremental', name: 'sync_incremental', methods: ['POST'])]
    public function syncIncremental(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_sync', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        set_time_limit(120);

        try {
            $result = $this->syncService->syncNewContacts();

            return $this->json([
                'success' => true,
                'step' => 'incremental',
                'created' => $result->getCreated(),
                'updated' => $result->getUpdated(),
                'errors' => array_slice($result->getErrors(), 0, 10),
                'errorCount' => count($result->getErrors()),
                'message' => $result->getCreated() === 0 && $result->getUpdated() === 0
                    ? 'Aucun nouveau contact détecté — base à jour.'
                    : sprintf('%d créé(s), %d mis à jour', $result->getCreated(), $result->getUpdated()),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'step' => 'incremental',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mise à jour des valorisations par lot (AJAX récursif).
     * Traite N comptes à chaque appel et indique s'il reste des comptes.
     */
    #[Route('/sync/valuations', name: 'sync_valuations', methods: ['POST'])]
    public function syncValuations(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_sync', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        set_time_limit(120);

        $batchSize = min((int) ($request->request->get('batch_size', 30)), 50);

        try {
            $result = $this->syncService->syncValuationsBatch($batchSize);

            // Combien de comptes restent à mettre à jour ?
            $totalO2s = (int) $this->em->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(ProductAccount::class, 'p')
                ->where('p.o2sCompteId IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult();

            return $this->json([
                'success' => true,
                'updated' => $result->getUpdated(),
                'errors' => array_slice($result->getErrors(), 0, 5),
                'errorCount' => count($result->getErrors()),
                'batchSize' => $batchSize,
                'totalAccounts' => $totalO2s,
                'message' => sprintf('%d valorisation(s) mise(s) à jour', $result->getUpdated()),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Synchronisation des comptes d'un seul utilisateur (on-demand).
     */
    #[Route('/sync/user/{userId}', name: 'sync_user', methods: ['POST'])]
    public function syncUser(int $userId, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_sync', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        try {
            $result = $this->syncService->syncComptesForUserId($userId);

            return $this->json([
                'success' => true,
                'created' => $result->getCreated(),
                'updated' => $result->getUpdated(),
                'skipped' => $result->getSkipped(),
                'errors' => $result->getErrors(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API : Rafraîchir les données d'un compte O2S.
     */
    #[Route('/api/refresh/{compteId}', name: 'api_refresh', methods: ['POST'])]
    public function refreshCompte(string $compteId): JsonResponse
    {
        try {
            $details = $this->compteService->getAccountDetails($compteId);
            
            // Mettre à jour le produit local si existant
            $product = $this->em->getRepository(ProductAccount::class)
                ->findOneBy(['o2sCompteId' => $compteId]);
            
            if ($product && $details->hasValuation()) {
                $product->setO2sValuation((string) $details->getTotalValue());
                $product->setO2sValuationDate($details->getValuationDate());
                $product->setO2sSyncedAt(new \DateTimeImmutable());
                $this->em->flush();
            }

            return $this->json([
                'success' => true,
                'totalValue' => $details->getTotalValue(),
                'liquidity' => $details->getLiquidity(),
                'valuationDate' => $details->getValuationDate()?->format('Y-m-d'),
                'assetsCount' => count($details->getSituation()),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Page de gestion des doublons O2S.
     */
    #[Route('/duplicates', name: 'duplicates', methods: ['GET'])]
    public function duplicates(): Response
    {
        $conn = $this->em->getConnection();

        $duplicates = $conn->fetchAllAssociative("
            SELECT 
                a.id as manual_id, 
                a.email as manual_email, 
                a.first_name as manual_fn,
                a.last_name as manual_ln,
                b.id as o2s_id, 
                b.email as o2s_email,
                b.first_name as o2s_fn,
                b.last_name as o2s_ln,
                b.o2s_contact_id,
                (SELECT COUNT(*) FROM product_accounts pa WHERE pa.user_id = b.id) as product_count
            FROM users_adn a
            JOIN users_adn b ON (
                LOWER(REPLACE(REPLACE(TRIM(a.last_name), ' ', ''), '-', '')) 
                = LOWER(REPLACE(REPLACE(TRIM(b.last_name), ' ', ''), '-', ''))
                AND LOWER(REPLACE(REPLACE(TRIM(a.first_name), ' ', ''), '-', '')) 
                = LOWER(REPLACE(REPLACE(TRIM(b.first_name), ' ', ''), '-', ''))
            )
            WHERE a.o2s_contact_id IS NULL
              AND b.o2s_contact_id IS NOT NULL
              AND a.id != b.id
              AND b.email LIKE 'o2s_%@placeholder.local'
            ORDER BY a.last_name, a.first_name
        ");

        $totalProducts = array_sum(array_column($duplicates, 'product_count'));

        return $this->render('admin/o2s/duplicates.html.twig', [
            'duplicates' => $duplicates,
            'totalProducts' => $totalProducts,
        ]);
    }

    /**
     * Action AJAX : Fusionner un doublon spécifique.
     */
    #[Route('/duplicates/merge', name: 'merge_duplicate', methods: ['POST'])]
    public function mergeDuplicate(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_merge', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $manualId = (int) $request->request->get('manual_id');
        $o2sId = (int) $request->request->get('o2s_id');

        if (!$manualId || !$o2sId) {
            return $this->json(['success' => false, 'error' => 'IDs invalides'], 400);
        }

        $conn = $this->em->getConnection();

        try {
            $conn->beginTransaction();

            // Récupérer l'o2s_contact_id
            $o2sContactId = $conn->fetchOne('SELECT o2s_contact_id FROM users_adn WHERE id = ?', [$o2sId]);
            if (!$o2sContactId) {
                throw new \RuntimeException('Utilisateur O2S introuvable');
            }

            // 1. Transférer o2s_contact_id vers le compte manuel (seulement s'il n'en a pas)
            $existingO2sId = $conn->fetchOne('SELECT o2s_contact_id FROM users_adn WHERE id = ?', [$manualId]);
            if (!$existingO2sId) {
                $conn->executeStatement(
                    'UPDATE users_adn SET o2s_contact_id = ?, o2s_synced_at = NOW() WHERE id = ?',
                    [$o2sContactId, $manualId]
                );
            }

            // 2. Transférer les product_accounts
            $transferred = $conn->executeStatement(
                'UPDATE product_accounts SET user_id = ? WHERE user_id = ?',
                [$manualId, $o2sId]
            );

            // 3. Supprimer le compte O2S placeholder
            $conn->executeStatement('DELETE FROM users_adn WHERE id = ?', [$o2sId]);

            $conn->commit();

            return $this->json([
                'success' => true,
                'products_transferred' => $transferred,
                'message' => sprintf('Fusionné avec succès — %d produit(s) transféré(s)', $transferred),
            ]);
        } catch (\Throwable $e) {
            $conn->rollBack();
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Action AJAX : Fusionner TOUS les doublons d'un coup.
     */
    #[Route('/duplicates/merge-all', name: 'merge_all_duplicates', methods: ['POST'])]
    public function mergeAllDuplicates(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('o2s_merge', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $conn = $this->em->getConnection();

        $duplicates = $conn->fetchAllAssociative("
            SELECT 
                a.id as manual_id, 
                b.id as o2s_id, 
                b.o2s_contact_id
            FROM users_adn a
            JOIN users_adn b ON (
                LOWER(REPLACE(REPLACE(TRIM(a.last_name), ' ', ''), '-', '')) 
                = LOWER(REPLACE(REPLACE(TRIM(b.last_name), ' ', ''), '-', ''))
                AND LOWER(REPLACE(REPLACE(TRIM(a.first_name), ' ', ''), '-', '')) 
                = LOWER(REPLACE(REPLACE(TRIM(b.first_name), ' ', ''), '-', ''))
            )
            WHERE a.o2s_contact_id IS NULL
              AND b.o2s_contact_id IS NOT NULL
              AND a.id != b.id
              AND b.email LIKE 'o2s_%@placeholder.local'
        ");

        $merged = 0;
        $productsTransferred = 0;
        $errors = [];

        foreach ($duplicates as $dup) {
            try {
                $conn->beginTransaction();

                // Transférer o2s_contact_id seulement s'il n'en a pas déjà un
                $existingO2sId = $conn->fetchOne('SELECT o2s_contact_id FROM users_adn WHERE id = ?', [$dup['manual_id']]);
                if (!$existingO2sId) {
                    $conn->executeStatement(
                        'UPDATE users_adn SET o2s_contact_id = ?, o2s_synced_at = NOW() WHERE id = ?',
                        [$dup['o2s_contact_id'], $dup['manual_id']]
                    );
                }

                $transferred = $conn->executeStatement(
                    'UPDATE product_accounts SET user_id = ? WHERE user_id = ?',
                    [$dup['manual_id'], $dup['o2s_id']]
                );

                $conn->executeStatement('DELETE FROM users_adn WHERE id = ?', [$dup['o2s_id']]);

                $conn->commit();
                $merged++;
                $productsTransferred += $transferred;
            } catch (\Throwable $e) {
                $conn->rollBack();
                $errors[] = sprintf('[%d←%d] %s', $dup['manual_id'], $dup['o2s_id'], $e->getMessage());
            }
        }

        return $this->json([
            'success' => count($errors) === 0,
            'merged' => $merged,
            'products_transferred' => $productsTransferred,
            'errors' => $errors,
            'message' => sprintf('%d doublon(s) fusionné(s), %d produit(s) transféré(s)', $merged, $productsTransferred),
        ]);
    }

    private function mapProductType(string $o2sType): string
    {
        return match (strtoupper($o2sType)) {
            'ASSURANCE VIE', 'AV' => 'ASSURANCE_VIE',
            'PEA' => 'PEA',
            'PER', 'PERP', 'PERCO' => 'PER',
            'COMPTE TITRE', 'CTO' => 'CTO',
            default => 'AUTRE',
        };
    }
}

