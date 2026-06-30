<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\Persistence\ManagerRegistry;
use Vich\UploaderBundle\Handler\UploadHandler;
use App\Entity\User\User;
use App\Services\User\InvestorProfileScorer;
use App\Entity\User\KycDocument;
use App\Entity\User\KycDocumentFile;
use App\Entity\User\Info;
use App\Entity\User\Pro;
use App\Entity\User\Document;
use App\Entity\User\Config;
use App\Entity\User\Control;
use App\Entity\Stock;
use App\Entity\Mail\UserMessage;
use App\Entity\Chat\ChatMessage;
use App\Repository\InvestmentOpportunityClickRepository;
use App\Repository\Mail\UserMessageRepository;
use App\Repository\Chat\ChatMessageRepository;
use App\Repository\Chat\ChatConversationRepository;
use App\Entity\Chat\ChatConversation;
use App\Entity\InvestmentComparison;
use App\Repository\InvestmentComparisonRepository;
use App\Entity\User\Activity as UserActivity;
use App\Repository\User\ActivityRepository;
use App\Entity\InvestmentProductDocument;
use App\Entity\Blog\Post;
use App\Entity\Blog\Category;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Services\Mail\MailManager;
use App\Repository\RegistrationTrackingRepository;
use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use App\Integration\O2S\Sync\O2SSyncService;
use Psr\Log\LoggerInterface;

#[Route('/admin/modern')]
class ModernAdminController extends AbstractController
{
    private $doctrine;
    private $mailManager;
    private $trackingRepository;
    private $o2sSyncService;
    private $logger;
    
    public function __construct(
        ManagerRegistry $doctrine,
        MailManager $mailManager,
        RegistrationTrackingRepository $trackingRepository,
        O2SSyncService $o2sSyncService,
        LoggerInterface $logger,
    ) {
        $this->doctrine = $doctrine;
        $this->mailManager = $mailManager;
        $this->trackingRepository = $trackingRepository;
        $this->o2sSyncService = $o2sSyncService;
        $this->logger = $logger;
    }

    /**
     * Vérifie si l'utilisateur connecté peut accéder à un module spécifique
     */
    private function checkModuleAccess(string $module): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user || !$user->canAccessModule($module)) {
            throw new AccessDeniedException('Vous n\'avez pas accès à ce module.');
        }
    }

    /**
     * Vérifie si l'utilisateur est Super Admin
     */
    private function isSuperAdmin(): bool
    {
        /** @var User $user */
        $user = $this->getUser();
        return $user && $user->isSuperAdmin();
    }

    #[Route('/tracking', name: 'admin_modern_tracking', methods: ['GET'])]
    public function tracking(): Response
    {
        $this->checkModuleAccess('dashboard');
        
        // Afficher tous les trackings récents (pas seulement les incomplets) pour le premier chargement
        $trackings = $this->trackingRepository->findRecent(100);
        $activeCount = $this->trackingRepository->countActiveLast24Hours();
        
        return $this->render('admin_modern/tracking.html.twig', [
            'trackings' => $trackings,
            'activeCount' => $activeCount,
        ]);
    }

    #[Route('/opportunities/documents', name: 'admin_modern_opportunity_documents', methods: ['GET','POST'])]
    public function opportunityDocuments(Request $request): Response
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository(InvestmentProductDocument::class);

        if ($request->isMethod('POST')) {
            $type = (string) $request->request->get('product_type', '');
            $title = trim((string) $request->request->get('title', ''));
            /** @var array<\Symfony\Component\HttpFoundation\File\UploadedFile> $files */
            $files = $request->files->all('files') ?: [];

            if (!array_key_exists($type, InvestmentProductDocument::getAvailableProductTypes())) {
                $this->addFlash('error', 'Type de produit invalide.');
                return $this->redirectToRoute('admin_modern_opportunity_documents');
            }

            if (empty($files)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins un fichier.');
                return $this->redirectToRoute('admin_modern_opportunity_documents');
            }

            foreach ($files as $uploaded) {
                if (!$uploaded) { continue; }
                $doc = new InvestmentProductDocument();
                $doc->setProductType($type);
                $doc->setTitle($title !== '' ? $title : ($uploaded->getClientOriginalName() ?? 'Document'));
                $doc->setFile($uploaded);
                $em->persist($doc);
            }
            $em->flush();
            $this->addFlash('success', 'Document(s) ajouté(s) avec succès.');
            return $this->redirectToRoute('admin_modern_opportunity_documents');
        }

        $docs = $repo->findBy([], ['updatedAt' => 'DESC']);

        // Grouper par type pour l'affichage
        $byType = [];
        foreach ($docs as $d) {
            $byType[$d->getProductType()][] = $d;
        }

        return $this->render('admin_modern/opportunity_documents.html.twig', [
            'types' => InvestmentProductDocument::getAvailableProductTypes(),
            'documentsByType' => $byType,
        ]);
    }

    #[Route('/activities', name: 'admin_modern_activities', methods: ['GET','POST'])]
    public function activities(Request $request, ActivityRepository $activityRepo): Response
    {
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);

        if ($request->isMethod('POST')) {
            $userId = (int) $request->request->get('user_id');
            $title = trim((string) $request->request->get('title', ''));
            $message = trim((string) $request->request->get('message', ''));
            $level = (string) $request->request->get('level', 'info');

            if ($userId && $title !== '') {
                /** @var User|null $user */
                $user = $userRepo->find($userId);
                if ($user) {
                    $activity = new UserActivity();
                    $activity->setUser($user)
                        ->setTitle($title)
                        ->setMessage($message ?: null)
                        ->setLevel(in_array($level, ['info','success','warning'], true) ? $level : 'info');

                    $em->persist($activity);
                    $em->flush();
                    $this->addFlash('success', 'Activité créée.');
                    return $this->redirectToRoute('admin_modern_activities');
                }
            }
            $this->addFlash('error', 'Veuillez sélectionner un utilisateur et saisir un titre.');
            return $this->redirectToRoute('admin_modern_activities');
        }

        $users = $userRepo->findBy([], ['createdAt' => 'DESC'], 50);
        $activities = $activityRepo->findBy([], ['createdAt' => 'DESC'], 50);
        return $this->render('admin_modern/activities.html.twig', [
            'users' => $users,
            'activities' => $activities,
        ]);
    }

    #[Route('/opportunities/documents/{id}/delete', name: 'admin_modern_opportunity_documents_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function opportunityDocumentsDelete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_opportunity_doc_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        $em = $this->doctrine->getManager();
        $doc = $em->find(InvestmentProductDocument::class, $id);
        if ($doc) {
            // Supprimer physiquement le fichier si présent (en plus de Vich)
            try {
                $fileName = $doc->getFileName();
                if ($fileName) {
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/product-documents/' . $fileName;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            } catch (\Throwable $e) {
                // on ignore les erreurs d'IO et on poursuit la suppression en BDD
            }
            $em->remove($doc);
            $em->flush();
            $this->addFlash('success', 'Document supprimé.');
        }
        return $this->redirectToRoute('admin_modern_opportunity_documents');
    }

    // (removed duplicate lightweight dashboard stub)

    #[Route('/comparison', name: 'admin_modern_comparison', methods: ['GET','POST'])]
    public function comparison(Request $request, InvestmentComparisonRepository $repo): Response
    {
        $em = $this->doctrine->getManager();

        if ($request->isMethod('POST')) {
            $values    = $request->request->all('value') ?? [];
            $products  = $request->request->all('product') ?? [];
            $criteria  = $request->request->all('criterion') ?? [];
            $deletes   = array_keys($request->request->all('delete') ?? []);

            foreach ($deletes as $id) {
                if ($entity = $repo->find((int)$id)) {
                    $em->remove($entity);
                }
            }

            foreach ($values as $id => $value) {
                $entity = $repo->find((int)$id);
                if ($entity) {
                    if (isset($products[$id])) {
                        $entity->setProduct((string)$products[$id]);
                    }
                    if (isset($criteria[$id])) {
                        $entity->setCriterion((string)$criteria[$id]);
                    }
                    $entity->setValue((string)$value);
                    $entity->setUpdatedAt(new \DateTime());
                }
            }

            $new = $request->request->all('new') ?? [];
            if (!empty($new['product']) && !empty($new['criterion']) && isset($new['value'])) {
                $ic = new InvestmentComparison();
                $ic->setProduct((string)$new['product'])
                   ->setCriterion((string)$new['criterion'])
                   ->setValue((string)$new['value'])
                   ->setPosition($repo->count([]) + 1)
                   ->setUpdatedAt(new \DateTime());
                $em->persist($ic);
            }

            $em->flush();
            $this->addFlash('success', 'Table de comparaison mise à jour.');
            return $this->redirectToRoute('admin_modern_comparison');
        }

        $data = $repo->findBy([], ['position' => 'ASC']);
        return $this->render('admin_modern/comparison.html.twig', [
            'items' => $data,
        ]);
    }

    #[Route('/site-settings', name: 'admin_modern_site_settings', methods: ['GET', 'POST'])]
    public function siteSettings(Request $request, SiteSettingRepository $repo): Response
    {
        $em = $this->doctrine->getManager();

        // Définition des paramètres par défaut
        $defaultSettings = [
            SiteSetting::KEY_CLIENTS_ACCOMPAGNES => ['value' => '170', 'label' => 'Clients accompagnés', 'suffix' => null],
            SiteSetting::KEY_ACTIFS_GERES => ['value' => '70', 'label' => 'Actifs gérés (M€)', 'suffix' => null],
            SiteSetting::KEY_ANNEES_EXPERTISE => ['value' => '15', 'label' => "Années d'expertise", 'suffix' => null],
            SiteSetting::KEY_MONTANT_ACCESSIBLE => ['value' => '10 000', 'label' => 'Montant accessible (€)', 'suffix' => '€'],
        ];

        // Initialiser les paramètres s'ils n'existent pas
        foreach ($defaultSettings as $key => $config) {
            $existing = $repo->getByKey($key);
            if (!$existing) {
                $setting = new SiteSetting();
                $setting->setSettingKey($key);
                $setting->setValue($config['value']);
                $setting->setLabel($config['label']);
                $setting->setSuffix($config['suffix']);
                $em->persist($setting);
            }
        }
        $em->flush();

        if ($request->isMethod('POST')) {
            $values = $request->request->all('value') ?? [];
            
            foreach ($values as $key => $value) {
                $setting = $repo->getByKey($key);
                if ($setting) {
                    $setting->setValue($value);
                    $em->persist($setting);
                }
            }
            
            $em->flush();
            $this->addFlash('success', 'Paramètres du site mis à jour avec succès.');
            return $this->redirectToRoute('admin_modern_site_settings');
        }

        $settings = $repo->findAll();
        
        return $this->render('admin_modern/site_settings.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('', name: 'admin_modern_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->checkModuleAccess('dashboard');
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        $kycRepo = $em->getRepository(KycDocument::class);
        $clickRepo = $em->getRepository(\App\Entity\InvestmentOpportunityClick::class);

        // Métriques principales — contacts O2S
        $totalContacts = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()->getSingleScalarResult();

        // Personnes morales (pas de prénom)
        $personnesMoralesCount = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere("u.firstName IS NULL OR u.firstName = ''")
            ->getQuery()->getSingleScalarResult();

        // Personnes physiques
        $personnesPhysiquesCount = $totalContacts - $personnesMoralesCount;

        // Dernière synchronisation O2S
        $lastO2sSync = $userRepo->createQueryBuilder('u')
            ->select('MAX(u.o2sSyncedAt)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()->getSingleScalarResult();

        $userCount7 = (int) $userRepo->createQueryBuilder('u')
            ->select('count(u.id)')
            ->where('u.createdAt >= :date')
            ->setParameter('date', (new \DateTime('-7 days'))->setTime(0,0))
            ->getQuery()->getSingleScalarResult();

        $kycPending = $kycRepo->count(['status' => KycDocument::STATUS_PENDING]);
        $kycValidated = $kycRepo->count(['status' => KycDocument::STATUS_VALIDATED]);
        $kycRefused = $kycRepo->count(['status' => KycDocument::STATUS_REFUSED]);

        // Inscriptions sur 30 jours pour le graphique
        $usersChartLabels = [];
        $usersChartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-$i days");
            $start = (clone $date)->setTime(0, 0, 0);
            $end = (clone $date)->setTime(23, 59, 59);
            $usersChartLabels[] = $date->format('d/m');
            $count = $userRepo->createQueryBuilder('u')
                ->select('count(u.id)')
                ->where('u.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()->getSingleScalarResult();
            $usersChartData[] = (int)$count;
        }

        // KYC par statut pour graphique en donut
        $kycByStatus = [
            'PENDING' => $kycPending,
            'VALIDATED' => $kycValidated,
            'REFUSED' => $kycRefused,
        ];

        // Distribution des profils investisseurs
        $profilesData = $userRepo->createQueryBuilder('u')
            ->select('u.investorProfile, COUNT(u) as count')
            ->where('u.investorProfile IS NOT NULL')
            ->groupBy('u.investorProfile')
            ->getQuery()
            ->getResult();

        $lastUsers = $userRepo->findBy([], ['createdAt' => 'DESC'], 5);

        // Statistiques des clics sur les opportunités d'investissement
        $investmentClicks = [
            'total' => 0,
            'last_7_days' => 0,
            'last_30_days' => 0,
            'by_product' => [],
            'by_action' => [],
            'top_products' => [],
        ];

        try {
            $investmentClicks = [
                'total' => $clickRepo->count([]),
                'last_7_days' => $clickRepo->getTotalClicksSince(new \DateTime('-7 days')),
                'last_30_days' => $clickRepo->getTotalClicksSince(new \DateTime('-30 days')),
                'by_product' => $clickRepo->getClickStatsByProduct(),
                'by_action' => $clickRepo->getClickStatsByAction(),
                'top_products' => $clickRepo->getTopProducts(4),
            ];
        } catch (\Exception $e) {
            // En cas d'erreur, on garde les valeurs par défaut
            // La table sera créée plus tard
        }

        // Messages non lus
        $messageRepo = $em->getRepository(UserMessage::class);
        $unreadMessages = $messageRepo->countUnread();

        $placeholderEmailsCount = $this->o2sSyncService->countPlaceholderEmails();

        return $this->render('admin_modern/dashboard.html.twig', [
            'totalContacts' => $totalContacts,
            'personnesMoralesCount' => $personnesMoralesCount,
            'personnesPhysiquesCount' => $personnesPhysiquesCount,
            'lastO2sSync' => $lastO2sSync,
            'placeholderEmailsCount' => $placeholderEmailsCount,
            'userCount7' => $userCount7,
            'kycPending' => $kycPending,
            'kycValidated' => $kycValidated,
            'kycRefused' => $kycRefused,
            'usersChartLabels' => $usersChartLabels,
            'usersChartData' => $usersChartData,
            'kycByStatus' => $kycByStatus,
            'profilesData' => $profilesData,
            'lastUsers' => $lastUsers,
            'investmentClicks' => $investmentClicks,
            'unreadMessages' => $unreadMessages,
        ]);
    }

    /**
     * AJAX endpoint: synchronise les contacts O2S (nouveaux + comptes manquants).
     * Déclenché automatiquement depuis le dashboard admin si la dernière sync est > 24h.
     */
    #[Route('/api/sync-o2s', name: 'admin_api_sync_o2s', methods: ['POST'])]
    public function syncO2s(): JsonResponse
    {
        $this->checkModuleAccess('dashboard');

        $this->logger->info('Admin dashboard: triggering O2S sync');

        $results = [
            'newContacts' => 0,
            'newComptes' => 0,
            'updatedComptes' => 0,
            'errors' => [],
        ];

        try {
            // 1. Sync des nouveaux contacts (rapide: compare la liste O2S vs BDD)
            $contactsResult = $this->o2sSyncService->syncNewContacts();
            $results['newContacts'] = $contactsResult->getCreated();

            if ($contactsResult->hasErrors()) {
                $results['errors'] = array_merge($results['errors'], $contactsResult->getErrors());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Admin sync: contacts sync failed', ['error' => $e->getMessage()]);
            $results['errors'][] = 'Sync contacts: ' . $e->getMessage();
        }

        try {
            // 2. Sync des comptes : utilisateurs sans comptes + utilisateurs avec sync > 1h
            $comptesResult = $this->o2sSyncService->syncMissingComptes(30);
            $results['newComptes'] = $comptesResult->getCreated();
            $results['updatedComptes'] = $comptesResult->getUpdated();

            if ($comptesResult->hasErrors()) {
                $results['errors'] = array_merge($results['errors'], $comptesResult->getErrors());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Admin sync: comptes sync failed', ['error' => $e->getMessage()]);
            $results['errors'][] = 'Sync comptes: ' . $e->getMessage();
        }

        // 3. Correction des emails placeholder (passe complète)
        try {
            $emailResult = $this->o2sSyncService->fixPlaceholderEmails();
            $fixed = $emailResult->getMetadata('fixed') ?? 0;
            $remaining = $emailResult->getMetadata('remaining') ?? 0;
            $conflictsResolved = $emailResult->getMetadata('conflictsResolved') ?? 0;
            if ($fixed > 0) {
                $results['emailsFixed'] = $fixed;
                $results['emailsRemaining'] = $remaining;
                $results['emailConflictsResolved'] = $conflictsResolved;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Admin sync: email fix failed', ['error' => $e->getMessage()]);
            $results['errors'][] = 'Fix emails: ' . $e->getMessage();
        }

        // 4. Recalculer les statistiques
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);

        $totalContacts = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()->getSingleScalarResult();

        $personnesMoralesCount = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.o2sContactId IS NOT NULL')
            ->andWhere("u.firstName IS NULL OR u.firstName = ''")
            ->getQuery()->getSingleScalarResult();

        $personnesPhysiquesCount = $totalContacts - $personnesMoralesCount;

        $lastO2sSync = $userRepo->createQueryBuilder('u')
            ->select('MAX(u.o2sSyncedAt)')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()->getSingleScalarResult();

        $placeholderEmailsCount = $this->o2sSyncService->countPlaceholderEmails();

        return new JsonResponse([
            'success' => true,
            'results' => $results,
            'stats' => [
                'totalContacts' => $totalContacts,
                'personnesPhysiquesCount' => $personnesPhysiquesCount,
                'personnesMoralesCount' => $personnesMoralesCount,
                'placeholderEmails' => $placeholderEmailsCount,
                'lastO2sSync' => $lastO2sSync ? (new \DateTime($lastO2sSync))->format('d/m/Y à H:i') : null,
            ],
        ]);
    }

    /**
     * AJAX endpoint: corrige les emails placeholder (passe complète).
     * Utile sur OVH où les commandes CLI ne peuvent pas joindre Harvest.
     */
    #[Route('/api/fix-emails', name: 'admin_api_fix_emails', methods: ['POST'])]
    public function fixPlaceholderEmailsAction(): JsonResponse
    {
        $this->checkModuleAccess('dashboard');

        set_time_limit(600); // 10 minutes max (85 contacts × ~2s API = ~3 min)

        try {
            $result = $this->o2sSyncService->fixPlaceholderEmails();

            $fixed = $result->getMetadata('fixed') ?? 0;
            $noEmail = $result->getMetadata('noEmail') ?? 0;
            $conflicts = $result->getMetadata('conflicts') ?? 0;
            $conflictsResolved = $result->getMetadata('conflictsResolved') ?? 0;
            $remaining = $result->getMetadata('remaining') ?? 0;
            $errors = $result->getErrors();

            $messageParts = [];
            if ($fixed > 0) {
                $messageParts[] = sprintf('%d email(s) corrigé(s)', $fixed);
            }
            if ($conflictsResolved > 0) {
                $messageParts[] = sprintf('dont %d conflit(s) résolu(s) (couples/familles)', $conflictsResolved);
            }
            if ($noEmail > 0) {
                $messageParts[] = sprintf('%d sans email dans O2S', $noEmail);
            }
            if ($conflicts > 0) {
                $messageParts[] = sprintf('%d conflit(s) non résolu(s)', $conflicts);
            }
            $messageParts[] = sprintf('%d placeholder(s) restant(s)', $remaining);

            return new JsonResponse([
                'success' => true,
                'fixed' => $fixed,
                'noEmail' => $noEmail,
                'conflicts' => $conflicts,
                'conflictsResolved' => $conflictsResolved,
                'errors' => count($errors),
                'remaining' => $remaining,
                'message' => implode('. ', $messageParts) . '.',
                'stats' => [
                    'placeholderEmails' => $remaining,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Fix emails failed', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX endpoint: classify contacts (Client/Prospect) depuis Harvest, par lots.
     * Traite 10 contacts par appel. Le JS rappelle en boucle jusqu'à remaining=0.
     * Bouton temporaire — à retirer une fois la classification effectuée partout.
     */
    #[Route('/api/backfill-type-contacts', name: 'admin_api_backfill_type_contacts', methods: ['POST'])]
    public function backfillTypeContactsAction(): JsonResponse
    {
        $this->checkModuleAccess('dashboard');

        try {
            $stats = $this->o2sSyncService->backfillTypeContacts(limit: 10);

            return new JsonResponse([
                'success' => true,
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
                'remaining' => $stats['remaining'],
                'total' => $stats['total'],
                'message' => sprintf(
                    '%d classifié(s), %d restant(s) sur %d total',
                    $stats['updated'],
                    $stats['remaining'],
                    $stats['total']
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Classify contacts failed', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/actions', name: 'admin_modern_actions', methods: ['GET'])]
    public function actions(): Response
    {
        // Page simple listant des raccourcis d'administration
        return $this->render('admin_modern/quick_access.html.twig');
    }

    #[Route('/client-portfolio', name: 'admin_modern_client_portfolio', methods: ['GET'])]
    public function clientPortfolio(): Response
    {
        $em = $this->doctrine->getManager();
        
        // Récupérer tous les utilisateurs avec leurs informations de patrimoine
        $users = $em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.info', 'i')
            ->addSelect('i')
            ->leftJoin('u.pro', 'p')
            ->addSelect('p')
            ->where('u.stepKyc >= 3') // Seulement les utilisateurs ayant complété l'étape patrimoine
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Calculer les statistiques globales
        $totalPatrimoine = 0;
        $totalLiquidity = 0;
        $totalRealestate = 0;
        $totalAccountSecurities = 0;
        $totalCapitalisation = 0;
        $totalScpi = 0;
        $totalIncome = 0;
        $userCount = 0;

        $portfolioData = [];
        
        foreach ($users as $user) {
            $info = $user->getInfo();
            $pro  = $user->getPro();

            // Particuliers (personnes physiques)
            if (!$user->isPro() && $info) {
                // Immobilier = jouissance + locatif (hors SCPI)
                $realestateTotal = (float) ($info->getRealestate() ?? 0)
                                 + (float) ($info->getRental() ?? 0);
                // Compte titres / épargne = titres + crowdfinance + autres
                $securitiesTotal = (float) ($info->getAccountSecurities() ?? 0)
                                 + (float) (method_exists($info, 'getCrowdfinance') ? ($info->getCrowdfinance() ?? 0) : 0)
                                 + (float) (method_exists($info, 'getPatrimonyOther') ? ($info->getPatrimonyOther() ?? 0) : 0);
                $capitalisation = (float) ($info->getCapitalisation() ?? 0);
                $scpi = (float) ($info->getScpi() ?? 0);

                $userPatrimoine = $realestateTotal + $securitiesTotal + $capitalisation + $scpi;

                $portfolioData[] = [
                    'user' => $user,
                    'info' => $info,
                    'totalPatrimoine' => $userPatrimoine,
                    'isPro' => false,
                ];

                $totalPatrimoine += $userPatrimoine;
                $totalLiquidity += (float) ($info->getLiquidity() ?? 0);
                $totalRealestate += $realestateTotal;
                $totalAccountSecurities += $securitiesTotal;
                $totalCapitalisation += $capitalisation;
                $totalScpi += $scpi;
                $totalIncome += (float) ($info->getIncome() ?? 0);
                $userCount++;
                continue;
            }

            // Professionnels (personnes morales)
            if ($user->isPro() && $pro) {
                $capital = (float) ($pro->getCapital() ?? 0);
                $stocks  = (float) ($pro->getStocks() ?? 0); // Réserves
                $turnover = (float) ($pro->getTurnover() ?? 0); // CA N-1
                $oldResult = (float) ($pro->getOldResult() ?? 0); // Résultat N-1
                $forecastTurnover = (float) ($pro->getForecastTurnover() ?? 0); // CA prévision N

                // Définition d'un "patrimoine" simplifié PRO: capital social + réserves
                $userPatrimoine = $capital + $stocks;

                $portfolioData[] = [
                    'user' => $user,
                    'info' => null,
                    'pro' => $pro,
                    'totalPatrimoine' => $userPatrimoine,
                    'isPro' => true,
                    'proSummary' => [
                        'capital' => $capital,
                        'stocks' => $stocks,
                        'turnover' => $turnover,
                        'oldResult' => $oldResult,
                        'forecastTurnover' => $forecastTurnover,
                    ],
                ];

                $totalPatrimoine += $userPatrimoine;
                // Les autres agrégats (immobilier, etc.) ne s'appliquent pas directement aux PRO
                $userCount++;
            }
        }

        // Trier par patrimoine total décroissant
        usort($portfolioData, function($a, $b) {
            return $b['totalPatrimoine'] <=> $a['totalPatrimoine'];
        });

        $stats = [
            'userCount' => $userCount,
            'totalPatrimoine' => $totalPatrimoine,
            'totalLiquidity' => $totalLiquidity,
            'totalRealestate' => $totalRealestate,
            'totalAccountSecurities' => $totalAccountSecurities,
            'totalCapitalisation' => $totalCapitalisation,
            'totalScpi' => $totalScpi,
            'totalIncome' => $totalIncome,
            'averagePatrimoine' => $userCount > 0 ? $totalPatrimoine / $userCount : 0,
        ];

        return $this->render('admin_modern/client_portfolio.html.twig', [
            'portfolioData' => $portfolioData,
            'stats' => $stats,
        ]);
    }

    #[Route('/client-portfolio/export', name: 'admin_modern_client_portfolio_export', methods: ['GET'])]
    public function clientPortfolioExport(): Response
    {
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);

        $users = $userRepo->createQueryBuilder('u')
            ->leftJoin('u.info', 'i')->addSelect('i')
            ->leftJoin('u.pro', 'p')->addSelect('p')
            ->where('u.stepKyc >= 3')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()->getResult();

        $rows = [];
        $rows[] = [
            'ID', 'Type', 'Nom', 'Email', 'PatrimoineTotal',
            'Immobilier/Capital', 'Titres/Reserves', 'AV/CA_N_1', 'SCPI/Resultat_N_1', 'Liquidites/CA_Prevu', 'Revenus'
        ];

        foreach ($users as $user) {
            $isPro = $user->isPro();
            $info = $user->getInfo();
            $pro  = $user->getPro();

            if ($isPro && $pro) {
                $capital = (float) ($pro->getCapital() ?? 0);
                $stocks  = (float) ($pro->getStocks() ?? 0);
                $turnover = (float) ($pro->getTurnover() ?? 0);
                $oldResult = (float) ($pro->getOldResult() ?? 0);
                $forecastTurnover = (float) ($pro->getForecastTurnover() ?? 0);
                $total = $capital + $stocks;

                $rows[] = [
                    $user->getId(), 'PRO', trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '')),
                    $user->getEmail(), $total,
                    $capital, $stocks, $turnover, $oldResult, $forecastTurnover, ''
                ];
            } elseif (!$isPro && $info) {
                $realestateTotal = (float) ($info->getRealestate() ?? 0)
                                 + (float) ($info->getRental() ?? 0);
                $securitiesTotal = (float) ($info->getAccountSecurities() ?? 0)
                                 + (float) (method_exists($info, 'getCrowdfinance') ? ($info->getCrowdfinance() ?? 0) : 0)
                                 + (float) (method_exists($info, 'getPatrimonyOther') ? ($info->getPatrimonyOther() ?? 0) : 0);
                $capitalisation = (float) ($info->getCapitalisation() ?? 0);
                $scpi = (float) ($info->getScpi() ?? 0);
                $total = $realestateTotal + $securitiesTotal + $capitalisation + $scpi;
                $rows[] = [
                    $user->getId(), 'PARTICULIER', trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '')),
                    $user->getEmail(), $total,
                    $realestateTotal,
                    $securitiesTotal,
                    $capitalisation,
                    $scpi,
                    (float) ($info->getLiquidity() ?? 0),
                    (float) ($info->getIncome() ?? 0),
                ];
            }
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($csv);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'client_portfolio.csv'
        );
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    #[Route('/news', name: 'admin_modern_news', methods: ['GET'])]
    public function newsIndex(): Response
    {
        $this->checkModuleAccess('news');
        $em = $this->doctrine->getManager();
        $posts = $em->getRepository(Post::class)->findBy([], ['publicationDateStart' => 'DESC']);
        return $this->render('admin_modern/news/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/news/new', name: 'admin_modern_news_new', methods: ['GET','POST'])]
    #[Route('/news/{id}/edit', name: 'admin_modern_news_edit', methods: ['GET','POST'])]
    public function newsForm(Request $request, Post $post = null): Response
    {
        $em = $this->doctrine->getManager();
        $isNew = false;
        if (!$post) {
            $post = new Post();
            $isNew = true;
            $post->setPublicationDateStart(new \DateTime());
            $post->setCommentsEnabled(false);
            $post->setStatus(1);
        }

        if ($request->isMethod('POST')) {
            $post->setTitle((string)$request->request->get('title'));
            $post->setSeoTitle((string)$request->request->get('seo_title'));
            $post->setSeoDescription((string)$request->request->get('seo_description'));
            $post->setContent((string)$request->request->get('content'));
            $post->setCanonicalUrl($request->request->get('canonical_url') ?: null);
            $post->setRedirectUrl($request->request->get('redirect_url') ?: null);
            
            // Combinaison de la date et de l'heure (format HTML5: YYYY-MM-DD)
            $dateDay = $request->request->get('publication_date_day'); // Format: YYYY-MM-DD (standard HTML5)
            $dateHour = $request->request->get('publication_date_hour', '10');
            $dateMinute = $request->request->get('publication_date_minute', '00');
            
            // Validation et création de la date
            if (!empty($dateDay) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDay)) {
                $dateStr = $dateDay; // Déjà au bon format YYYY-MM-DD
            } else {
                $dateStr = date('Y-m-d'); // Date du jour par défaut
            }
            $post->setPublicationDateStart(new \DateTime($dateStr . ' ' . $dateHour . ':' . $dateMinute . ':00'));
            
            $post->setImageAlt($request->request->get('image_alt') ?: null);
            
            // Statut de publication (1 = publié, 0 = brouillon)
            $isPublished = $request->request->get('is_published') ? 1 : 0;
            $post->setStatus($isPublished);
            
            // Mettre à la Une
            $isFeatured = $request->request->get('is_featured') ? true : false;
            $post->setIsFeatured($isFeatured);

            // Catégorie
            $categoryId = (int)$request->request->get('category');
            if ($category = $em->getRepository(Category::class)->find($categoryId)) {
                $post->setCategory($category);
            }

            // Image upload via VichUploader
            if ($file = $request->files->get('image_file')) {
                $post->setImageFile($file);
            }

            if ($isNew) { 
                $post->setCreatedAt(new \DateTime());
                $post->setUser($this->getUser());
                $em->persist($post); 
            }
            $post->setUpdatedAt(new \DateTime());
            $em->flush();
            $this->addFlash('success', 'Article sauvegardé.');
            return $this->redirectToRoute('admin_modern_news');
        }

        $categories = $this->doctrine->getRepository(Category::class)->findBy([], ['name' => 'ASC']);
        return $this->render('admin_modern/news/form.html.twig', [
            'post' => $post,
            'categories' => $categories,
            'is_new' => $isNew,
        ]);
    }

    #[Route('/news/{id}/delete', name: 'admin_modern_news_delete', methods: ['POST'])]
    public function newsDelete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_post_'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_modern_news');
        }
        
        $em = $this->doctrine->getManager();
        $post = $em->getRepository(Post::class)->find($id);
        
        if (!$post) {
            $this->addFlash('error', 'Article non trouvé.');
            return $this->redirectToRoute('admin_modern_news');
        }
        
        try {
            // Récupérer le nom de l'image avant suppression pour la supprimer manuellement
            $imageName = $post->getImageName();
            
            // Supprimer les ratings liés à l'article (si la table existe)
            $conn = $em->getConnection();
            try {
                $conn->executeStatement('DELETE FROM ratings WHERE blog_post_id = :postId', ['postId' => $id]);
            } catch (\Exception $e) {
                // Table ratings n'existe pas, on ignore
            }
            
            // Désactiver temporairement le filtre SoftDeleteable pour une suppression définitive
            $filters = $em->getFilters();
            $softDeleteEnabled = $filters->isEnabled('softdeleteable');
            if ($softDeleteEnabled) {
                $filters->disable('softdeleteable');
            }
            
            // Suppression directe en base de données pour éviter le soft delete
            $conn->executeStatement('DELETE FROM blog_post WHERE id = :id', ['id' => $id]);
            
            // Réactiver le filtre si nécessaire
            if ($softDeleteEnabled) {
                $filters->enable('softdeleteable');
            }
            
            // Supprimer l'image manuellement si elle existe
            if ($imageName) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/images/' . $imageName;
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            }
            
            $this->addFlash('success', 'Article supprimé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('admin_modern_news');
    }

    /**
     * Upload d'images pour l'éditeur TinyMCE
     */
    #[Route('/upload/image', name: 'admin_modern_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        
        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier envoyé'], 400);
        }
        
        // Vérifier le type de fichier
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.'], 400);
        }
        
        // Vérifier la taille (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['error' => 'Le fichier est trop volumineux (max 5MB)'], 400);
        }
        
        // Générer un nom unique
        $extension = $file->guessExtension();
        $filename = 'article_' . uniqid() . '_' . time() . '.' . $extension;
        
        // Créer le dossier si nécessaire
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/articles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Déplacer le fichier
        try {
            $file->move($uploadDir, $filename);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
        
        // Retourner l'URL de l'image
        $imageUrl = '/uploads/articles/' . $filename;
        
        return new JsonResponse([
            'location' => $imageUrl
        ]);
    }

    #[Route('/users', name: 'admin_modern_users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $this->checkModuleAccess('users');
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $kycStep = $request->query->get('kyc_step', '');
        $profileType = $request->query->get('profile_type', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        
        $qb = $userRepo->createQueryBuilder('u')
            ->select('u')
            ->orderBy('u.createdAt', 'DESC');
        
        if (!empty($search)) {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $qb->andWhere('u.isVerified = true');
            } elseif ($status === 'inactive') {
                $qb->andWhere('u.isVerified = false');
            }
        }
        
        if ($kycStep !== '' && $kycStep !== null) {
            $qb->andWhere('u.stepKyc = :kycStep')
               ->setParameter('kycStep', (int) $kycStep);
        }
        
        if (!empty($profileType)) {
            $qb->andWhere('u.investorProfile = :profileType')
               ->setParameter('profileType', $profileType);
        }
        
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);
        
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($qb, fetchJoinCollection: false);
        $totalFiltered = count($paginator);
        $totalPages = max(1, (int) ceil($totalFiltered / $limit));
        
        $stats = $em->createQuery(
            'SELECT COUNT(u.id) as total,
                    SUM(CASE WHEN u.isVerified = true THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN u.isVerified = false THEN 1 ELSE 0 END) as inactive
             FROM App\Entity\User\User u'
        )->getSingleResult();
        
        $kycSteps = [
            User::STEP_KYC_PROFILE => 'Étape 1 - Profil',
            User::STEP_KYC_OBJECTIVES => 'Étape 2 - Objectifs',
            User::STEP_KYC_PATRIMONY => 'Étape 3 - Patrimoine',
            User::STEP_KYC_EXPERIENCE => 'Étape 4 - Expérience',
            User::STEP_KYC_DOCUMENTS => 'Étape 5 - Documents',
        ];
        
        $profileTypes = [
            'PRUDENT' => 'Prudent',
            'EQUILIBRE' => 'Équilibré',
            'DYNAMIQUE' => 'Dynamique',
            'SPE' => 'Spéculateur',
        ];

        $viewVars = [
            'users' => $paginator,
            'totalUsers' => (int) $stats['total'],
            'activeUsers' => (int) $stats['active'],
            'inactiveUsers' => (int) $stats['inactive'],
            'totalFiltered' => $totalFiltered,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'kycSteps' => $kycSteps,
            'profileTypes' => $profileTypes,
            'currentFilters' => [
                'search' => $search,
                'status' => $status,
                'kyc_step' => $kycStep,
                'profile_type' => $profileType,
            ],
        ];

        // Pour les requêtes AJAX (recherche live), renvoyer un fragment HTML + métadonnées
        // afin de mettre à jour la liste sans recharger toute la page.
        if ($request->isXmlHttpRequest() || $request->query->getBoolean('ajax')) {
            $html = $this->renderView('admin_modern/_users_results.html.twig', $viewVars);

            return new JsonResponse([
                'html'           => $html,
                'totalUsers'     => $viewVars['totalUsers'],
                'activeUsers'    => $viewVars['activeUsers'],
                'inactiveUsers'  => $viewVars['inactiveUsers'],
                'totalFiltered'  => $viewVars['totalFiltered'],
                'currentPage'    => $viewVars['currentPage'],
                'totalPages'     => $viewVars['totalPages'],
            ]);
        }

        return $this->render('admin_modern/users.html.twig', $viewVars);
    }

    #[Route('/users/{id}', name: 'admin_modern_user_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function viewUser(int $id): Response
    {
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        $kycRepo = $em->getRepository(KycDocument::class);
        
        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        // Récupération des documents KYC
        $kycDocuments = $kycRepo->findBy(['user' => $user], ['createdAt' => 'DESC']);
        
        // Étapes KYC (pour affichage label)
        $kycSteps = [
            User::STEP_KYC_PROFILE => 'Étape 1 - Profil',
            User::STEP_KYC_OBJECTIVES => 'Étape 2 - Objectifs',
            User::STEP_KYC_PATRIMONY => 'Étape 3 - Patrimoine',
            User::STEP_KYC_EXPERIENCE => 'Étape 4 - Expérience',
            User::STEP_KYC_DOCUMENTS => 'Étape 5 - Documents',
        ];

        return $this->render('admin_modern/user_view.html.twig', [
            'user' => $user,
            'kycDocuments' => $kycDocuments,
            'kycSteps' => $kycSteps,
        ]);
    }

    #[Route('/users/{id}/impersonate', name: 'admin_modern_user_impersonate', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function impersonateUser(int $id): Response
    {
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        // Rediriger avec le paramètre de switch_user
        return $this->redirectToRoute('user_dashboard', ['_switch_user' => $user->getEmail()]);
    }
    
    #[Route('/users/{id}/edit', name: 'admin_modern_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editUser(int $id, Request $request): Response
    {
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        if ($request->isMethod('POST')) {
            // Traitement du formulaire d'édition
            $data = $request->request->all();
            
            // Mise à jour des champs de base
            if (isset($data['firstName'])) {
                $user->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $user->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $user->setEmail($data['email']);
            }
            if (isset($data['isVerified'])) {
                $user->setIsVerified((bool) $data['isVerified']);
            }
            if (array_key_exists('kycStep', $data) && $data['kycStep'] !== '') {
                $user->setStepKyc((int) $data['kycStep']);
            }
            if (isset($data['investorProfile'])) {
                $user->setInvestorProfile($data['investorProfile']);
            }
            
            $em->flush();
            
            $this->addFlash('success', 'Utilisateur mis à jour avec succès');
            return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
        }
        
        // Étapes KYC (pour formulaire)
        $kycSteps = [
            User::STEP_KYC_PROFILE => 'Étape 1 - Profil',
            User::STEP_KYC_OBJECTIVES => 'Étape 2 - Objectifs',
            User::STEP_KYC_PATRIMONY => 'Étape 3 - Patrimoine',
            User::STEP_KYC_EXPERIENCE => 'Étape 4 - Expérience',
            User::STEP_KYC_DOCUMENTS => 'Étape 5 - Documents',
        ];

        return $this->render('admin_modern/user_edit.html.twig', [
            'user' => $user,
            'kycSteps' => $kycSteps,
            'adminRoles' => User::getAdminRolesLabels(),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }

    #[Route('/users/{id}/roles', name: 'admin_modern_user_roles', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function updateUserRoles(int $id, Request $request): Response
    {
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Validation CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_roles_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('admin_modern_user_edit', ['id' => $id]);
        }

        // Récupérer les rôles sélectionnés (peut être vide si aucune case cochée)
        $selectedRoles = $request->request->all('roles');
        if (!is_array($selectedRoles)) {
            $selectedRoles = [];
        }
        
        // Ne garder que les rôles admin valides
        $validAdminRoles = array_keys(User::ROLES_ADMIN_LIST);
        $newAdminRoles = array_values(array_intersect($selectedRoles, $validAdminRoles));
        
        // Récupérer les rôles actuels bruts (sans ROLE_USER ajouté par getRoles())
        $currentRolesRaw = $user->getRolesRaw();
        
        // Conserver les rôles non-admin actuels (ROLE_USER, ROLE_USER_IDENTIFIED, etc.)
        $currentNonAdminRoles = array_values(array_diff($currentRolesRaw, $validAdminRoles));
        
        // Combiner les rôles et réindexer le tableau
        $finalRoles = array_values(array_unique(array_merge($currentNonAdminRoles, $newAdminRoles)));
        
        // Mettre à jour les rôles directement
        $user->setRoles($finalRoles);
        
        // Forcer la mise à jour
        $em->persist($user);
        $em->flush();
        
        // Message de confirmation avec détails
        $rolesLabels = array_map(fn($r) => User::ROLES_ADMIN_LABELS[$r] ?? $r, $newAdminRoles);
        $msg = empty($newAdminRoles) 
            ? 'Tous les rôles administratifs ont été retirés.' 
            : 'Rôles mis à jour : ' . implode(', ', $rolesLabels);
        $this->addFlash('success', $msg);
        
        return $this->redirectToRoute('admin_modern_user_edit', ['id' => $id]);
    }

    #[Route('/users/{id}/verify-email', name: 'admin_modern_user_verify_email', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function verifyEmail(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('verify_email_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        $user->setIsVerified(true);
        $em->flush();
        $this->addFlash('success', "L'email de l'utilisateur a été vérifié.");
        return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
    }

    /**
     * Suspend un compte utilisateur
     */
    #[Route('/users/{id}/suspend', name: 'admin_modern_user_suspend', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function suspendUser(int $id, Request $request): Response
    {
        $this->checkModuleAccess('users');
        
        if (!$this->isCsrfTokenValid('suspend_user_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        // Empêcher de suspendre un Super Admin (sauf si on est Super Admin soi-même et on se suspend pas soi-même)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($user->isSuperAdmin() && (!$currentUser->isSuperAdmin() || $currentUser->getId() === $user->getId())) {
            $this->addFlash('error', 'Vous ne pouvez pas suspendre ce compte.');
            return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
        }
        
        // Empêcher de se suspendre soi-même
        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas suspendre votre propre compte.');
            return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
        }
        
        $reason = $request->request->get('reason', 'Compte suspendu par l\'administrateur');
        $user->suspend($reason);
        $em->flush();
        
        $this->addFlash('success', "Le compte de {$user->getName()} a été suspendu.");
        return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
    }

    /**
     * Réactive un compte utilisateur suspendu
     */
    #[Route('/users/{id}/unsuspend', name: 'admin_modern_user_unsuspend', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function unsuspendUser(int $id, Request $request): Response
    {
        $this->checkModuleAccess('users');
        
        if (!$this->isCsrfTokenValid('unsuspend_user_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        $user->unsuspend();
        $em->flush();
        
        $this->addFlash('success', "Le compte de {$user->getName()} a été réactivé.");
        return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
    }

    /**
     * Supprime définitivement un utilisateur
     */
    #[Route('/users/{id}/delete', name: 'admin_modern_user_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deleteUser(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_user_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        
        // Empêcher de supprimer son propre compte
        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
        }
        
        // Empêcher de supprimer un Super Admin
        if ($user->isSuperAdmin()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer un Super Admin.');
            return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
        }
        
        $userName = $user->getName();
        
        try {
            // Supprimer les dépendances qui n'ont pas de cascade configurée
            $conn = $em->getConnection();
            
            // 1. Supprimer les Holdings liés aux ProductAccounts de l'utilisateur
            $conn->executeStatement('
                DELETE h FROM holdings h
                INNER JOIN product_accounts pa ON h.product_account_id = pa.id
                WHERE pa.user_id = :userId
            ', ['userId' => $id]);
            
            // 2. Supprimer les ProductContributions liées aux ProductAccounts de l'utilisateur
            $conn->executeStatement('
                DELETE pc FROM product_contributions pc
                INNER JOIN product_accounts pa ON pc.product_account_id = pa.id
                WHERE pa.user_id = :userId
            ', ['userId' => $id]);
            
            // 3. Supprimer les ProductAccounts de l'utilisateur
            $conn->executeStatement('DELETE FROM product_accounts WHERE user_id = :userId', ['userId' => $id]);
            
            // 4. Supprimer les activités de l'utilisateur (table: user_activity)
            $conn->executeStatement('DELETE FROM user_activity WHERE user_id = :userId', ['userId' => $id]);
            
            // 5. Supprimer les messages de chat (colonne: sender_id)
            $conn->executeStatement('DELETE FROM chat_messages WHERE sender_id = :userId', ['userId' => $id]);
            
            // 6. Supprimer les comparaisons d'investissement (si la table existe)
            try {
                $conn->executeStatement('DELETE FROM investment_comparisons WHERE user_id = :userId', ['userId' => $id]);
            } catch (\Exception $e) {
                // Table peut ne pas exister
            }
            
            // 7. Supprimer les clics sur opportunités (si la table existe)
            try {
                $conn->executeStatement('DELETE FROM investment_opportunity_clicks WHERE user_id = :userId', ['userId' => $id]);
            } catch (\Exception $e) {
                // Table peut ne pas exister
            }
            
            // Soft delete avec Gedmo (définit deletedAt)
            // Les entités liées avec cascade: ['remove'] seront également soft-deleted ou supprimées
            $em->remove($user);
            $em->flush();
            
            $this->addFlash('success', "Le compte de {$userName} a été supprimé définitivement.");
        } catch (\Exception $e) {
            $this->addFlash('error', "Erreur lors de la suppression : " . $e->getMessage());
            return $this->redirectToRoute('admin_modern_user_view', ['id' => $id]);
        }
        
        return $this->redirectToRoute('admin_modern_users');
    }

    #[Route('/kyc', name: 'admin_modern_kyc', methods: ['GET'])]
    public function kyc(): Response
    {
        $this->checkModuleAccess('kyc');
        $em = $this->doctrine->getManager();
        $kycRepo = $em->getRepository(KycDocument::class);
        
        $documents = $kycRepo->findBy([], ['createdAt' => 'DESC'], 50);

        return $this->render('admin_modern/kyc.html.twig', [
            'documents' => $documents,
        ]);
    }

    #[Route('/investor-profiles', name: 'admin_modern_investor_profiles', methods: ['GET'])]
    public function investorProfiles(Request $request): Response
    {
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        // Récupération des filtres
        $profileFilter = $request->query->get('profile', '');
        $statusFilter = $request->query->get('status', '');
        
        // Construction de la requête avec filtres
        $qb = $userRepo->createQueryBuilder('u')
            ->where('u.investorProfile IS NOT NULL')
            ->orderBy('u.createdAt', 'DESC');
        
        // Filtre par profil
        if (!empty($profileFilter)) {
            $qb->andWhere('u.investorProfile = :profile')
               ->setParameter('profile', $profileFilter);
        }
        
        // Filtre par statut
        if (!empty($statusFilter)) {
            if ($statusFilter === 'active') {
                $qb->andWhere('u.isVerified = true');
            } elseif ($statusFilter === 'inactive') {
                $qb->andWhere('u.isVerified = false');
            }
        }
        
        $users = $qb->getQuery()->getResult();
        
        // Statistiques par profil
        $profileStats = [
            'PRUDENT' => $userRepo->count(['investorProfile' => 'PRUDENT']),
            'EQUILIBRE' => $userRepo->count(['investorProfile' => 'EQUILIBRE']),
            'DYNAMIQUE' => $userRepo->count(['investorProfile' => 'DYNAMIQUE']),
            'SPE' => $userRepo->count(['investorProfile' => 'SPE']),
        ];
        
        // Total utilisateurs avec profil
        $totalWithProfile = array_sum($profileStats);
        $totalUsers = $userRepo->count([]);
        
        // Profils investisseurs disponibles
        $profileTypes = [
            'PRUDENT' => 'Prudent',
            'EQUILIBRE' => 'Équilibré',
            'DYNAMIQUE' => 'Dynamique',
            'SPE' => 'Spéculateur',
        ];

        return $this->render('admin_modern/investor_profiles.html.twig', [
            'users' => $users,
            'profileStats' => $profileStats,
            'totalWithProfile' => $totalWithProfile,
            'totalUsers' => $totalUsers,
            'profileTypes' => $profileTypes,
            'currentFilters' => [
                'profile' => $profileFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    #[Route('/users/search', name: 'admin_users_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository(User::class);
        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('u')
            ->select('u.id, u.firstName, u.lastName, u.email')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(10);
        if ($q !== '') {
            $qb->where('LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%' . strtolower($q) . '%');
        }
        $rows = $qb->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $r) {
            $label = trim(($r['firstName'] ?? '') . ' ' . ($r['lastName'] ?? ''));
            if ($label === '') { $label = $r['email']; }
            else { $label .= ' <' . $r['email'] . '>'; }
            $out[] = ['id' => (int) $r['id'], 'label' => $label];
        }
        return new JsonResponse($out);
    }

    #[Route('/investor-profiles/recalc/{id}', name: 'admin_modern_investor_profiles_recalc', methods: ['POST'])]
    public function investorProfilesRecalc(int $id, InvestorProfileScorer $scorer): Response
    {
        $em = $this->doctrine->getManager();
        /** @var User|null $user */
        $user = $em->getRepository(User::class)->find($id);
        if ($user) {
            $scorer->calculateAndUpdateProfile($user);
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Profil recalculé pour ' . ($user->getEmail() ?? 'utilisateur #' . $id));
        } else {
            $this->addFlash('error', 'Utilisateur introuvable.');
        }

        return $this->redirectToRoute('admin_modern_investor_profiles');
    }

    #[Route('/kyc/document/{id}/view', name: 'admin_modern_kyc_document_view', methods: ['GET'])]
    public function viewKycDocument(int $id): Response
    {
        $em = $this->doctrine->getManager();
        $kycRepo = $em->getRepository(KycDocument::class);
        
        $document = $kycRepo->find($id);
        
        if (!$document) {
            throw $this->createNotFoundException('Document KYC non trouvé');
        }
        
        $filename = null;
        $filePath = null;
        
        // Essayer d'abord avec les fichiers associés (nouvelle structure)
        $files = $document->getFiles();
        if (!$files->isEmpty()) {
            $file = $files->first();
            $filename = $file->getName();
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $filename;
        }
        // Sinon utiliser le filename direct du document (ancienne structure)
        elseif ($document->getFilename()) {
            $filename = $document->getFilename();
            // Essayer plusieurs dossiers possibles
            $possiblePaths = [
                $this->getParameter('kernel.project_dir') . '/var/uploads/kyc/' . $filename,
                $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $filename,
                $this->getParameter('kernel.project_dir') . '/public/uploads/' . $filename,
                $this->getParameter('kernel.project_dir') . '/var/uploads/' . $filename,
                $this->getParameter('kernel.project_dir') . '/uploads/' . $filename,
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $filePath = $path;
                    break;
                }
            }
        }
        
        if (!$filename || !$filePath || !file_exists($filePath)) {
            $this->addFlash('error', 'Le fichier n\'existe pas sur le serveur : ' . ($filename ?: 'nom de fichier manquant'));
            return $this->redirectToRoute('admin_modern_kyc');
        }
        
        // Déterminer le type MIME
        $mimeType = mime_content_type($filePath);
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Si c'est une image, on l'affiche directement
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) || 
            in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_INLINE);
        }
        
        // Si c'est un PDF, on l'affiche en ligne
        if ($mimeType === 'application/pdf' || $fileExtension === 'pdf') {
            return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_INLINE);
        }
        
        // Pour autres types, on force le téléchargement
        return $this->file($filePath, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/kyc/document/{id}/validate', name: 'admin_modern_kyc_document_validate', methods: ['POST'])]
    public function validateKycDocument(int $id): Response
    {
        $em = $this->doctrine->getManager();
        $kycRepo = $em->getRepository(KycDocument::class);
        
        $document = $kycRepo->find($id);
        
        if (!$document) {
            throw $this->createNotFoundException('Document KYC non trouvé');
        }
        
        $document->setStatus(KycDocument::STATUS_VALIDATED);
        $document->setUpdatedAt(new \DateTime());
        
        $em->flush();

        $user = $document->getUser();
        if ($user) {
            // Activity: KYC validé
            $activity = new UserActivity();
            $activity->setUser($user)
                ->setTitle('Document validé')
                ->setMessage('Votre document KYC "' . $document->getTypeName() . '" a été validé.')
                ->setLevel('success');
            $em->persist($activity);
            $em->flush();
            
            // Envoyer l'email de notification
            try {
                $this->mailManager->kycDocumentValidated($user, $document);
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas le processus
                // Le document est validé même si l'email échoue
            }
        }
        
        $this->addFlash('success', 'Document validé avec succès');
        
        return $this->redirectToRoute('admin_modern_kyc');
    }

    #[Route('/kyc/document/{id}/refuse', name: 'admin_modern_kyc_document_refuse', methods: ['POST'])]
    public function refuseKycDocument(int $id, Request $request): Response
    {
        $em = $this->doctrine->getManager();
        $kycRepo = $em->getRepository(KycDocument::class);
        
        $document = $kycRepo->find($id);
        
        if (!$document) {
            throw $this->createNotFoundException('Document KYC non trouvé');
        }
        
        $refusalReason = $request->request->get('refusal_reason');
        
        if (!$refusalReason) {
            $this->addFlash('error', 'Veuillez sélectionner une raison de refus');
            return $this->redirectToRoute('admin_modern_kyc');
        }
        
        $document->setStatus(KycDocument::STATUS_REFUSED);
        $document->setRefusedReasonMessage($refusalReason);
        $document->setUpdatedAt(new \DateTime());
        
        $em->flush();

        $user = $document->getUser();
        if ($user) {
            // Activity: KYC refusé
            $activity = new UserActivity();
            $activity->setUser($user)
                ->setTitle('Document refusé')
                ->setMessage('Votre document KYC "' . $document->getTypeName() . '" a été refusé : ' . $refusalReason)
                ->setLevel('warning');
            $em->persist($activity);
            $em->flush();
            
            // Envoyer l'email de notification avec la raison du refus
            try {
                $this->mailManager->kycDocumentRefused($user, $document, $refusalReason);
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas le processus
            }
        }
        
        $this->addFlash('success', 'Document refusé avec succès');
        
        return $this->redirectToRoute('admin_modern_kyc');
    }

    #[Route('/markets', name: 'admin_modern_markets', methods: ['GET'])]
    public function markets(): Response
    {
        $this->checkModuleAccess('markets');
        $em = $this->doctrine->getManager();
        $stockRepo = $em->getRepository(Stock::class);
        
        $stocks = $stockRepo->findBy([], ['updatedAt' => 'DESC'], 20);

        return $this->render('admin_modern/markets.html.twig', [
            'stocks' => $stocks,
        ]);
    }

    #[Route('/messages', name: 'admin_modern_messages', methods: ['GET'])]
    public function messages(Request $request): Response
    {
        $this->checkModuleAccess('messages');
        $em = $this->doctrine->getManager();
        $messageRepo = $em->getRepository(UserMessage::class);
        
        // Paramètres de filtre
        $search = $request->query->get('search');
        $category = $request->query->get('category');
        $isRead = $request->query->get('is_read');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        // Convertir le paramètre is_read en boolean ou null
        $isReadFilter = null;
        if ($isRead === '1') {
            $isReadFilter = true;
        } elseif ($isRead === '0') {
            $isReadFilter = false;
        }

        // Récupération des messages avec filtres
        $messages = $messageRepo->findAllWithFilters($search, $category, $isReadFilter, $page, $limit);
        $totalMessages = $messageRepo->countWithFilters($search, $category, $isReadFilter);
        $totalPages = ceil($totalMessages / $limit);

        // Statistiques
        $statistics = $messageRepo->getStatistics();

        return $this->render('admin_modern/messages.html.twig', [
            'messages' => $messages,
            'statistics' => $statistics,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_messages' => $totalMessages,
            'search' => $search,
            'category' => $category,
            'is_read' => $isRead,
            'categories' => UserMessage::getCategoryOptions(),
            'unreadMessages' => $statistics['unread'],
        ]);
    }

    #[Route('/message/{id}', name: 'admin_modern_message_show', methods: ['GET'])]
    public function showMessage(UserMessage $message): Response
    {
        $em = $this->doctrine->getManager();
        $messageRepo = $em->getRepository(UserMessage::class);
        
        // Marquer le message comme lu si ce n'est pas déjà fait
        if (!$message->isRead()) {
            $messageRepo->markAsRead($message);
        }

        // Compter les messages non lus pour le menu
        $unreadMessages = $messageRepo->countUnread();

        return $this->render('admin_modern/message_show.html.twig', [
            'message' => $message,
            'unreadMessages' => $unreadMessages,
        ]);
    }

    #[Route('/message/{id}/respond', name: 'admin_modern_message_respond', methods: ['POST'])]
    public function respondToMessage(UserMessage $message, Request $request): Response
    {
        $response = $request->request->get('admin_response');
        
        if (empty($response)) {
            $this->addFlash('error', 'La réponse ne peut pas être vide.');
            return $this->redirectToRoute('admin_modern_message_show', ['id' => $message->getId()]);
        }

        $message->setAdminResponse($response);
        $message->setIsRead(true);
        
        $em = $this->doctrine->getManager();
        $em->flush();
        
        // Temps réel désactivé (Mercure retiré)

        $this->addFlash('success', 'Réponse envoyée avec succès.');
        
        return $this->redirectToRoute('admin_modern_message_show', ['id' => $message->getId()]);
    }

    #[Route('/message/{id}/toggle-read', name: 'admin_modern_message_toggle_read', methods: ['POST'])]
    public function toggleMessageRead(UserMessage $message): Response
    {
        $message->setIsRead(!$message->isRead());
        
        $em = $this->doctrine->getManager();
        $em->flush();
        
        // Temps réel désactivé (Mercure retiré)

        $status = $message->isRead() ? 'lu' : 'non lu';
        $this->addFlash('success', "Message marqué comme {$status}.");
        
        return $this->redirectToRoute('admin_modern_messages');
    }

    #[Route('/chat', name: 'admin_modern_chat', methods: ['GET'])]
    public function chat(ChatConversationRepository $conversationRepo): Response
    {
        $this->checkModuleAccess('chat');
        $em = $this->doctrine->getManager();
        $chatRepo = $em->getRepository(ChatMessage::class);
        
        // Récupérer les conversations actives
        $conversations = $chatRepo->findActiveConversations();
        
        // Enrichir avec les informations des utilisateurs et compter les non lus
        $conversationsData = [];
        foreach ($conversations as $conv) {
            $unreadCount = $chatRepo->countUnreadForAdmin($conv['conversationId']);
            $lastMessage = $chatRepo->findLastMessage($conv['conversationId']);
            $conversation = $conversationRepo->findOneBy(['conversationId' => $conv['conversationId']]);
            
            $conversationsData[] = [
                'conversationId' => $conv['conversationId'],
                'userName' => $conv['firstName'] . ' ' . $conv['lastName'],
                'userEmail' => $conv['email'],
                'lastMessageAt' => $conv['lastMessageAt'],
                'unreadCount' => $unreadCount,
                'lastMessage' => $lastMessage,
                'status' => $conversation ? $conversation->getStatus() : ChatConversation::STATUS_OPEN,
            ];
        }
        
        // Trier par dernière activité
        usort($conversationsData, function($a, $b) {
            return $b['lastMessageAt'] <=> $a['lastMessageAt'];
        });
        
        // Statistiques
        $statistics = $chatRepo->getChatStatistics();

        return $this->render('admin_modern/chat.html.twig', [
            'conversations' => $conversationsData,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/chat/{conversationId}', name: 'admin_modern_chat_conversation', methods: ['GET'])]
    public function chatConversation(string $conversationId, ChatConversationRepository $conversationRepo): Response
    {
        $em = $this->doctrine->getManager();
        $chatRepo = $em->getRepository(ChatMessage::class);
        
        // Marquer les messages comme lus
        $chatRepo->markAsReadForAdmin($conversationId);
        
        // Récupérer les messages de la conversation
        $messages = $chatRepo->findByConversation($conversationId, 100);
        $conversation = $conversationRepo->findOrCreate($conversationId);
        
        // Récupérer les informations de l'utilisateur
        $userInfo = null;
        if (!empty($messages)) {
            $firstUserMessage = null;
            foreach ($messages as $message) {
                if ($message->getSenderType() === 'user') {
                    $firstUserMessage = $message;
                    break;
                }
            }
            
            if ($firstUserMessage) {
                $user = $firstUserMessage->getSender();
                $userInfo = [
                    'id' => $user->getId(),
                    'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'email' => $user->getEmail(),
                    'createdAt' => $user->getCreatedAt(),
                ];
            }
        }

        return $this->render('admin_modern/chat_conversation.html.twig', [
            'conversationId' => $conversationId,
            'messages' => $messages,
            'userInfo' => $userInfo,
            'conversationStatus' => $conversation->getStatus(),
        ]);
    }

    #[Route('/chat/{conversationId}/toggle', name: 'admin_modern_chat_toggle', methods: ['POST'])]
    public function toggleChatStatus(string $conversationId, ChatConversationRepository $conversationRepo, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_chat_'.$conversationId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        $conversation = $conversationRepo->findOrCreate($conversationId);
        $newStatus = $conversation->getStatus() === ChatConversation::STATUS_OPEN
            ? ChatConversation::STATUS_CLOSED
            : ChatConversation::STATUS_OPEN;
        $conversation->setStatus($newStatus);
        $conversationRepo->save($conversation, true);
        $this->addFlash('success', $newStatus === ChatConversation::STATUS_OPEN ? 'Conversation réouverte.' : 'Conversation fermée.');
        return $this->redirectToRoute('admin_modern_chat_conversation', ['conversationId' => $conversationId]);
    }

    #[Route('/chat/{conversationId}/send', name: 'admin_modern_chat_send', methods: ['POST'])]
    public function chatSend(string $conversationId, Request $request): Response
    {
        $messageContent = trim($request->request->get('message', ''));
        
        if (empty($messageContent)) {
            $this->addFlash('error', 'Le message ne peut pas être vide.');
            return $this->redirectToRoute('admin_modern_chat_conversation', ['conversationId' => $conversationId]);
        }

        $em = $this->doctrine->getManager();
        $chatRepo = $em->getRepository(ChatMessage::class);
        
        // Récupérer l'utilisateur de la conversation
        $existingMessages = $chatRepo->findByConversation($conversationId, 1);
        if (empty($existingMessages)) {
            $this->addFlash('error', 'Conversation introuvable.');
            return $this->redirectToRoute('admin_modern_chat');
        }
        
        $user = null;
        foreach ($existingMessages as $msg) {
            if ($msg->getSenderType() === 'user') {
                $user = $msg->getSender();
                break;
            }
        }
        
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_modern_chat');
        }
        
        // Créer le message admin
        $chatMessage = new ChatMessage();
        $chatMessage->setSender($user); // On garde l'utilisateur comme référence
        $chatMessage->setMessage($messageContent);
        $chatMessage->setSenderType('admin');
        $chatMessage->setConversationId($conversationId);
        
        $chatRepo->save($chatMessage, true);

        // Réponse JSON si AJAX (utilisé par le poller)
        if ($request->isXmlHttpRequest()) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'success' => true,
                'message' => [
                    'id' => $chatMessage->getId(),
                    'message' => $chatMessage->getMessage(),
                    'senderType' => 'admin',
                    'senderInitials' => $chatMessage->getSenderInitials(),
                    'formattedTime' => $chatMessage->getFormattedTime(),
                    'isFromAdmin' => true,
                    'isFromUser' => false,
                ],
            ]);
        }

        $this->addFlash('success', 'Message envoyé avec succès.');
        return $this->redirectToRoute('admin_modern_chat_conversation', ['conversationId' => $conversationId]);
    }

    #[Route('/chat/{conversationId}/messages', name: 'admin_modern_chat_messages', methods: ['GET'])]
    public function chatMessages(string $conversationId, Request $request): Response
    {
        $em = $this->doctrine->getManager();
        $chatRepo = $em->getRepository(ChatMessage::class);
        $afterId = (int) $request->query->get('afterId', 0);
        $messages = $chatRepo->findByConversation($conversationId, 100);

        $messagesData = [];
        $lastId = 0;
        foreach ($messages as $message) {
            if ($afterId && $message->getId() <= $afterId) {
                continue;
            }
            $messagesData[] = [
                'id' => $message->getId(),
                'message' => $message->getMessage(),
                'senderType' => $message->getSenderType(),
                'senderInitials' => $message->getSenderInitials(),
                'formattedTime' => $message->getFormattedTime(),
                'isFromAdmin' => $message->isFromAdmin(),
                'isFromUser' => $message->isFromUser(),
            ];
            $lastId = max($lastId, (int) $message->getId());
        }

        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'messages' => $messagesData,
            'lastId' => $lastId,
        ]);
    }

    #[Route('/pros', name: 'admin_modern_pros', methods: ['GET'])]
    public function pros(Request $request): Response
    {
        $this->checkModuleAccess('pros');
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);

        // Filtres simples
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        $qb = $userRepo->createQueryBuilder('u')
            ->leftJoin('u.pro', 'p')
            ->addSelect('p')
            ->where('u.type = :type')
            ->setParameter('type', User::USER_TYPE_PRO)
            ->orderBy('u.createdAt', 'DESC');

        if (!empty($search)) {
            $qb->andWhere('u.email LIKE :q OR u.firstName LIKE :q OR u.lastName LIKE :q OR p.companyName LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }
        if ($status === 'active') {
            $qb->andWhere('u.isVerified = true');
        } elseif ($status === 'inactive') {
            $qb->andWhere('u.isVerified = false');
        }

        $pros = $qb->getQuery()->getResult();

        return $this->render('admin_modern/pros.html.twig', [
            'pros' => $pros,
            'currentFilters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    #[Route('/pros/{id}', name: 'admin_modern_pro_view', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function proView(int $id): Response
    {
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);
        if (!$user || !$user->isPro()) {
            throw $this->createNotFoundException('Professionnel introuvable');
        }

        $pro = $user->getPro();
        $shareholders = $pro ? $pro->getShareholdersInformations() : [];
        $kycDocs = $em->getRepository(KycDocument::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('admin_modern/pro_view.html.twig', [
            'user' => $user,
            'pro' => $pro,
            'shareholders' => $shareholders,
            'kycDocuments' => $kycDocs,
        ]);
    }

    #[Route('/settings', name: 'admin_modern_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $this->checkModuleAccess('dashboard');
        
        /** @var User $user */
        $user = $this->getUser();
        $em = $this->doctrine->getManager();
        
        if ($request->isMethod('POST')) {
            $darkMode = $request->request->getBoolean('dark_mode', false);
            
            // Sauvegarder la préférence dans les métadonnées utilisateur
            $user->setAdminDarkMode($darkMode);
            $em->flush();
            
            $this->addFlash('success', 'Paramètres sauvegardés avec succès.');
            return $this->redirectToRoute('admin_modern_settings');
        }
        
        return $this->render('admin_modern/settings.html.twig', [
            'darkMode' => $user->isAdminDarkMode(),
        ]);
    }

    // ==================== MONEYPITCH MANAGEMENT ====================

    /**
     * Liste des utilisateurs MoneyPitch avec possibilité de gérer le flag de redirection
     */
    #[Route('/moneypitch', name: 'admin_modern_moneypitch', methods: ['GET'])]
    public function moneypitch(Request $request): Response
    {
        $this->checkModuleAccess('users');
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        // Récupération des filtres
        $search = $request->query->get('search', '');
        $filter = $request->query->get('filter', 'all'); // all, moneypitch, adn
        
        // Construction de la requête avec filtres - afficher TOUS les utilisateurs
        $qb = $userRepo->createQueryBuilder('u')
            ->where('u.deletedAt IS NULL')
            ->orderBy('u.createdAt', 'DESC');
        
        // Filtre par recherche (nom, email)
        if (!empty($search)) {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par type de redirection
        if ($filter === 'moneypitch') {
            $qb->andWhere('u.redirectToMoneyPitch = true');
        } elseif ($filter === 'adn') {
            $qb->andWhere('u.redirectToMoneyPitch = false');
        }
        
        $users = $qb->getQuery()->getResult();
        
        // Statistiques
        $totalUsers = $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
            
        $moneypitchUsers = $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = true')
            ->getQuery()
            ->getSingleScalarResult();
            
        $adnUsers = $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = false')
            ->getQuery()
            ->getSingleScalarResult();

        // Compteurs pour la bascule groupée : seulement ceux avec un vrai email (non-placeholder, non-admin)
        $moneypitchWithEmail = $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = true')
            ->andWhere('u.email NOT LIKE :placeholder')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('placeholder', '%@placeholder.local')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        $moneypitchWithoutEmail = $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = true')
            ->andWhere('u.email LIKE :placeholder')
            ->setParameter('placeholder', '%@placeholder.local')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin_modern/moneypitch.html.twig', [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'moneypitchUsers' => $moneypitchUsers,
            'adnUsers' => $adnUsers,
            'moneypitchWithEmail' => $moneypitchWithEmail,
            'moneypitchWithoutEmail' => $moneypitchWithoutEmail,
            'currentFilters' => [
                'search' => $search,
                'filter' => $filter,
            ],
        ]);
    }

    /**
     * Bascule le flag redirectToMoneyPitch pour un utilisateur.
     *
     * Supporte deux modes :
     *  - Mode "toggle" (legacy) : inverse l'état actuel
     *  - Mode "set" (recommandé) : impose la valeur cible via le paramètre `target`
     *    (target=moneypitch|adn). Cela évite tout ping-pong en cas de double-clic
     *    ou de requêtes simultanées : on garantit l'état final voulu par l'admin.
     *
     * Pour les requêtes XHR la réponse est un JSON contenant le nouvel état,
     * un message de confirmation/avertissement et les compteurs actualisés.
     */
    #[Route('/moneypitch/{id}/toggle', name: 'admin_modern_moneypitch_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleMoneypitch(int $id, Request $request): Response
    {
        $this->checkModuleAccess('users');

        $isXhr = $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');

        if (!$this->isCsrfTokenValid('moneypitch_toggle_' . $id, $request->request->get('_token'))) {
            if ($isXhr) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Jeton CSRF invalide. Veuillez recharger la page.',
                    'messageType' => 'error',
                ], 403);
            }
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            if ($isXhr) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Utilisateur introuvable.',
                    'messageType' => 'error',
                ], 404);
            }
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Refus si l'utilisateur est admin (les admins ne sont jamais redirigés)
        if ($user->isAdmin()) {
            if ($isXhr) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Les administrateurs ne sont jamais redirigés vers MoneyPitch.',
                    'messageType' => 'warning',
                ], 422);
            }
            $this->addFlash('warning', 'Les administrateurs ne sont jamais redirigés vers MoneyPitch.');
            return $this->redirectToRoute('admin_modern_moneypitch');
        }

        $wasMoneyPitch = $user->isRedirectToMoneyPitch();

        // Détermine l'état cible : explicite via `target`, sinon inversion (mode legacy)
        $target = $request->request->get('target');
        if ($target === 'moneypitch') {
            $shouldRedirect = true;
        } elseif ($target === 'adn') {
            $shouldRedirect = false;
        } else {
            $shouldRedirect = !$wasMoneyPitch;
        }

        // No-op idempotent : si la cible est déjà l'état actuel, pas besoin de flush
        // (évite l'envoi d'un mail de transition pour rien sur un double clic)
        if ($shouldRedirect === $wasMoneyPitch) {
            $this->logger->info('MoneyPitch toggle : aucun changement (état déjà cible)', [
                'userId' => $user->getId(),
                'target' => $shouldRedirect ? 'moneypitch' : 'adn',
            ]);

            if ($isXhr) {
                return new JsonResponse([
                    'success' => true,
                    'noop' => true,
                    'redirectToMoneyPitch' => $shouldRedirect,
                    'message' => $user->getName() . ' est déjà redirigé vers ' . ($shouldRedirect ? 'MoneyPitch' : "l'espace ADN") . '.',
                    'messageType' => 'info',
                    'counters' => $this->computeMoneypitchCounters($em),
                ]);
            }

            return $this->redirectToRoute('admin_modern_moneypitch');
        }

        $user->setRedirectToMoneyPitch($shouldRedirect);
        $em->flush();

        $this->logger->info('MoneyPitch toggle effectué', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'before' => $wasMoneyPitch ? 'moneypitch' : 'adn',
            'after' => $shouldRedirect ? 'moneypitch' : 'adn',
            'adminId' => $this->getUser()?->getId(),
        ]);

        $messageType = 'success';
        $statusLabel = $shouldRedirect ? 'MoneyPitch' : "l'espace ADN";
        $message = $user->getName() . ' redirigé vers ' . $statusLabel . '.';

        // Bascule MoneyPitch → ADN : tentative d'envoi d'un email de réinitialisation
        // (l'utilisateur n'a souvent pas de mot de passe Symfony valide)
        if ($wasMoneyPitch && !$shouldRedirect) {
            $transitionInfo = $this->sendPasswordResetForTransition($user, $em);
            if ($transitionInfo !== null) {
                $message = $transitionInfo['message'];
                $messageType = $transitionInfo['type'];
            }
        }

        if ($isXhr) {
            return new JsonResponse([
                'success' => true,
                'noop' => false,
                'redirectToMoneyPitch' => $user->isRedirectToMoneyPitch(),
                'message' => $message,
                'messageType' => $messageType,
                'counters' => $this->computeMoneypitchCounters($em),
            ]);
        }

        $this->addFlash($messageType, $message);
        return $this->redirectToRoute('admin_modern_moneypitch');
    }

    /**
     * Calcule les compteurs affichés sur la page MoneyPitch admin.
     * Centralisé pour pouvoir les renvoyer dans la réponse AJAX du toggle.
     *
     * @return array{total:int, moneypitch:int, adn:int, moneypitchWithEmail:int, moneypitchWithoutEmail:int}
     */
    private function computeMoneypitchCounters(\Doctrine\ORM\EntityManagerInterface $em): array
    {
        $userRepo = $em->getRepository(User::class);

        $total = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $moneypitch = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = true')
            ->getQuery()
            ->getSingleScalarResult();

        $adn = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = false')
            ->getQuery()
            ->getSingleScalarResult();

        $moneypitchWithEmail = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = true')
            ->andWhere('u.email NOT LIKE :placeholder')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('placeholder', '%@placeholder.local')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        $moneypitchWithoutEmail = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deletedAt IS NULL')
            ->andWhere('u.redirectToMoneyPitch = true')
            ->andWhere('u.email LIKE :placeholder')
            ->setParameter('placeholder', '%@placeholder.local')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'moneypitch' => $moneypitch,
            'adn' => $adn,
            'moneypitchWithEmail' => $moneypitchWithEmail,
            'moneypitchWithoutEmail' => $moneypitchWithoutEmail,
        ];
    }
    
    /**
     * Gère la transition de mot de passe lors du passage MoneyPitch → ADN.
     *
     * Deux cas :
     * - Utilisateur inscrit directement sur la plateforme (a un vrai hash bcrypt/argon2) → peut se connecter directement
     * - Utilisateur importé depuis O2S/Harvest (mot de passe aléatoire invalide) → nécessite un email de réinitialisation
     *
     * @return array{message:string,type:string}|null  Information à propager à l'admin
     *         (null = pas d'information particulière, comportement par défaut)
     */
    private function sendPasswordResetForTransition(User $user, \Doctrine\ORM\EntityManagerInterface $em): ?array
    {
        $email = $user->getEmail();

        // Ne pas envoyer si l'email est un placeholder
        if (!$email || str_contains($email, '@placeholder.local')) {
            $info = [
                'message' => "⚠️ {$user->getName()} n'a pas d'adresse email valide. Veuillez lui en attribuer une pour qu'il puisse se connecter à l'espace ADN.",
                'type' => 'warning',
            ];
            $this->addFlash($info['type'], $info['message']);
            return $info;
        }

        // Vérifier si l'utilisateur a un vrai mot de passe hashé (bcrypt/argon2)
        $password = $user->getPassword();
        $hasValidPassword = $password && (
            str_starts_with($password, '$2y$') ||
            str_starts_with($password, '$2a$') ||
            str_starts_with($password, '$argon2')
        );

        if ($hasValidPassword) {
            $info = [
                'message' => "✅ {$user->getName()} peut se connecter à l'espace ADN avec son mot de passe existant.",
                'type' => 'info',
            ];
            $this->addFlash($info['type'], $info['message']);
            return $info;
        }

        // L'utilisateur a été importé depuis O2S/Harvest → envoyer un email de réinitialisation
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+72 hours'));
        $em->flush();

        try {
            $resetUrl = $this->generateUrl('app_reset_password', [
                'token' => $token
            ], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

            $sent = $this->mailManager->resetPassword($email, [
                'user' => $user,
                'resetUrl' => $resetUrl,
            ]);

            if ($sent === false) {
                throw new \RuntimeException("MailManager::resetPassword a renvoyé false (échec d'envoi).");
            }

            $info = [
                'message' => "📧 {$user->getName()} a été basculé vers ADN. Un email de création de mot de passe a été envoyé à {$email}.",
                'type' => 'info',
            ];
            $this->addFlash($info['type'], $info['message']);
            return $info;
        } catch (\Throwable $e) {
            // Échec d'envoi : on annule le token en BDD pour ne pas laisser un état incohérent.
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $em->flush();

            $this->logger->error('Erreur lors de l\'envoi de l\'email de réinitialisation pour la transition MoneyPitch→ADN', [
                'userId' => $user->getId(),
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $info = [
                'message' => "⚠️ {$user->getName()} a bien été basculé vers ADN mais l'email de réinitialisation n'a pas pu être envoyé à {$email}. L'utilisateur devra utiliser \"Mot de passe oublié\" depuis la page de connexion.",
                'type' => 'warning',
            ];
            $this->addFlash($info['type'], $info['message']);
            return $info;
        }
    }

    /**
     * Active la redirection MoneyPitch pour plusieurs utilisateurs
     */
    #[Route('/moneypitch/bulk-enable', name: 'admin_modern_moneypitch_bulk_enable', methods: ['POST'])]
    public function bulkEnableMoneypitch(Request $request): Response
    {
        $this->checkModuleAccess('users');
        
        if (!$this->isCsrfTokenValid('moneypitch_bulk', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        
        $userIds = $request->request->all('user_ids');
        if (empty($userIds)) {
            $this->addFlash('error', 'Aucun utilisateur sélectionné.');
            return $this->redirectToRoute('admin_modern_moneypitch');
        }
        
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        $count = 0;
        foreach ($userIds as $id) {
            $user = $userRepo->find($id);
            if ($user && !$user->isRedirectToMoneyPitch()) {
                $user->setRedirectToMoneyPitch(true);
                $count++;
            }
        }
        
        $em->flush();
        $this->addFlash('success', "{$count} utilisateur(s) redirigé(s) vers MoneyPitch.");
        
        return $this->redirectToRoute('admin_modern_moneypitch');
    }

    /**
     * Désactive la redirection MoneyPitch pour plusieurs utilisateurs
     */
    #[Route('/moneypitch/bulk-disable', name: 'admin_modern_moneypitch_bulk_disable', methods: ['POST'])]
    public function bulkDisableMoneypitch(Request $request): Response
    {
        $this->checkModuleAccess('users');
        
        if (!$this->isCsrfTokenValid('moneypitch_bulk', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        
        $userIds = $request->request->all('user_ids');
        if (empty($userIds)) {
            $this->addFlash('error', 'Aucun utilisateur sélectionné.');
            return $this->redirectToRoute('admin_modern_moneypitch');
        }
        
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        $count = 0;
        $transitionedUsers = [];
        foreach ($userIds as $id) {
            $user = $userRepo->find($id);
            if ($user && $user->isRedirectToMoneyPitch()) {
                $user->setRedirectToMoneyPitch(false);
                $transitionedUsers[] = $user;
                $count++;
            }
        }
        
        $em->flush();
        
        // Envoyer les emails de réinitialisation pour les utilisateurs basculés vers ADN
        foreach ($transitionedUsers as $user) {
            $this->sendPasswordResetForTransition($user, $em);
        }
        
        $this->addFlash('success', "{$count} utilisateur(s) redirigé(s) vers ADN.");
        
        return $this->redirectToRoute('admin_modern_moneypitch');
    }

    /**
     * Bascule TOUS les utilisateurs MoneyPitch vers l'espace ADN d'un coup
     */
    #[Route('/moneypitch/redirect-all-to-adn', name: 'admin_modern_moneypitch_redirect_all_to_adn', methods: ['POST'])]
    public function redirectAllToAdn(Request $request): Response
    {
        $this->checkModuleAccess('users');
        
        if (!$this->isCsrfTokenValid('moneypitch_redirect_all', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }
        
        $em = $this->doctrine->getManager();
        $userRepo = $em->getRepository(User::class);
        
        // Récupérer uniquement les utilisateurs MoneyPitch avec un VRAI email (non-placeholder, non-admin)
        $moneyPitchUsers = $userRepo->createQueryBuilder('u')
            ->where('u.redirectToMoneyPitch = true')
            ->andWhere('u.email NOT LIKE :placeholder')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('placeholder', '%@placeholder.local')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();
        
        if (empty($moneyPitchUsers)) {
            $this->addFlash('warning', 'Aucun utilisateur éligible trouvé (tous ont des emails placeholder ou sont des admins).');
            return $this->redirectToRoute('admin_modern_moneypitch');
        }
        
        $count = 0;
        
        foreach ($moneyPitchUsers as $user) {
            $user->setRedirectToMoneyPitch(false);
            $count++;
        }
        
        $em->flush();
        
        // Envoyer les emails de réinitialisation pour les utilisateurs qui en ont besoin
        foreach ($moneyPitchUsers as $user) {
            $this->sendPasswordResetForTransition($user, $em);
        }
        
        // Compter les utilisateurs restants (placeholder emails)
        $remaining = $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.redirectToMoneyPitch = true')
            ->andWhere('u.email LIKE :placeholder')
            ->setParameter('placeholder', '%@placeholder.local')
            ->getQuery()
            ->getSingleScalarResult();
        
        $message = "✅ {$count} utilisateur(s) basculé(s) vers l'espace ADN.";
        if ($remaining > 0) {
            $message .= " ⚠️ {$remaining} utilisateur(s) restent sur MoneyPitch car ils n'ont pas d'adresse email valide.";
        }
        
        $this->addFlash('success', $message);
        
        return $this->redirectToRoute('admin_modern_moneypitch');
    }
}
