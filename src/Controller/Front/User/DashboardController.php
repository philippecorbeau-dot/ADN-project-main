<?php

namespace App\Controller\Front\User;

use App\Entity\User\User;
use App\Service\MarketData\TwelveDataClient;
use App\Entity\User\KycDocument;
use App\Entity\User\Info;
use App\Entity\User\Pro;
use App\Entity\Mail\UserMessage;
use App\Form\Front\User\UserMessageType;
use App\Repository\User\KycDocumentRepository;
use App\Repository\Mail\UserMessageRepository;
use App\Repository\Chat\ChatMessageRepository;
use App\Services\KycNotificationService;
use App\Services\KycNavigationService;
use App\Services\User\InvestorProfileScorer;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\MarketData\ProductValuationRefresher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\ProductAccount;
use App\Entity\Holding;
use App\Repository\User\ActivityRepository;
use App\Entity\User\Activity as UserActivity;
use Psr\Log\LoggerInterface;

#[Route("/user", name: "user_")]
#[IsGranted("ROLE_USER")]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly KycDocumentRepository $kycDocumentRepository,
        private readonly UserMessageRepository $userMessageRepository,
        private readonly KycNotificationService $kycNotificationService,
        private readonly KycNavigationService $kycNavigationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvestorProfileScorer $investorProfileScorer,
        private readonly RequestStack $requestStack,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly LoggerInterface $logger,
        private readonly \App\Integration\O2S\Service\CompteServiceInterface $compteService,
        private readonly \App\Integration\O2S\Service\InstitutionServiceInterface $institutionService,
        private readonly \App\Integration\O2S\Service\ProductServiceInterface $productService,
        private readonly \App\Integration\O2S\Service\ContactServiceInterface $contactService,
        private readonly \App\Integration\O2S\Sync\O2SSyncService $o2sSyncService,
    ) {}

    #[Route("/dashboard", name: "dashboard")]
    public function dashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = $user->getId();

        // Auto-réparation défensive des colonnes array KYC si hydratation échoue
        // (évite une 500 si une ancienne valeur sérialisée est corrompue)
        try {
            // Déclencher la lazy-load si besoin
            if ($user->getInfo()) { $user->getInfo()->getId(); }
        } catch (\Throwable $e) {
            $this->selfHealKycArraysForUser($userId);
            // Recharger l'utilisateur proprement
            $this->entityManager->clear();
            /** @var User $user */
            $user = $this->entityManager->getRepository(User::class)->find($userId);
        }
        
        // Calcul initial du profil investisseur si manquant mais données KYC présentes
        if ($user->getInvestorProfile() === null && $user->getInfo()) {
            try {
                $this->investorProfileScorer->calculateAndUpdateProfile($user);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                // on ne bloque pas l'affichage du dashboard
            }
        }

        // Agrégations produits/encours
        $productRepo = $this->entityManager->getRepository(ProductAccount::class);
        $holdingRepo = $this->entityManager->getRepository(Holding::class);

        /** @var ProductAccount[] $userProducts */
        $userProducts = $productRepo->findBy(['user' => $user]);

        // ─── NOTE PERF: Plus aucun appel API O2S ici ───
        // Les données sont lues depuis la BDD (ProductAccount + Holding).
        // Les données enrichies (historiques, patrimoine, versements) sont chargées via AJAX.

        $productsSummary = [];
        $totalsByType = [];
        $totalEncoursAll = 0.0;
        $totalInitialAll = 0.0;
        $totalVersementsAll = 0.0;

        foreach ($userProducts as $product) {
            $encours = 0.0;
            /** @var Holding[] $holdings */
            $holdings = $holdingRepo->findBy(['productAccount' => $product]);
            
            // Pour les comptes O2S sans holdings, utiliser la valorisation O2S
            $isO2SAccount = $product->getO2sCompteId() !== null;
            
            if (empty($holdings)) {
                // Si c'est un compte O2S, utiliser sa valorisation
                if ($isO2SAccount) {
                    $encours = $product->getO2sValuation() !== null 
                        ? (float) $product->getO2sValuation() 
                        : 0.0;
                } else {
                    // Pas de holdings ni de compte O2S → ignorer
                    continue;
                }
            } else {
                foreach ($holdings as $h) {
                    // Calcul dynamique: encours = unités × dernière VL
                    $units = $h->getUnits() !== null ? (float) $h->getUnits() : 0.0;
                    $price = $h->getLastPrice() !== null ? (float) $h->getLastPrice() : 0.0;
                    $computed = $units * $price;
                    // Fallback si données incomplètes: utiliser amount persisté
                    $amount = $computed > 0 ? $computed : (float) ($h->getAmount() ?? 0.0);
                    $encours += $amount;
                }
            }

            // Ajouter le fonds en euros du produit à l'encours affiché
            $euroFund = $product->getEuroFund() !== null ? (float) $product->getEuroFund() : 0.0;
            $encoursWithEuro = $encours + $euroFund;

            $totalEncoursAll += $encoursWithEuro;
            $initial = (float) $product->getTotalInvested();
            $totalInitialAll += $initial;

            // Versements : utiliser les données BDD (mises à jour par le sync/refresh)
            $compteId = $product->getO2sCompteId();
            $versements = null; // Les versements O2S seront enrichis via AJAX
            $totalVersementsAll += $initial;

            // Distributeur : utiliser celui en BDD (déjà résolu par le sync)
            $distributor = $product->getDistributor();

            $type = $product->getProductType();
            if (!isset($totalsByType[$type])) {
                $totalsByType[$type] = [
                    'encours' => 0.0,
                    'initial' => 0.0,
                    'count' => 0,
                ];
            }
            $totalsByType[$type]['encours'] += $encoursWithEuro;
            $totalsByType[$type]['initial'] += $initial;
            $totalsByType[$type]['count'] += 1;

            $productsSummary[] = [
                'id' => $product->getId(),
                'type' => $type,
                'distributor' => $distributor,
                'alias' => $product->getDisplayAlias() ?: $product->getInternalName(),
                'initial' => $initial,
                'versements' => $versements,
                'encours' => $encoursWithEuro,
                'euroFund' => $euroFund,
                'isO2S' => $isO2SAccount,
                'o2sSyncedAt' => $product->getO2sSyncedAt(),
                'o2sValuationDate' => $product->getO2sValuationDate(),
            ];
        }
        
        // Récupération des documents KYC manquants
        $arrayKycDocsMissing = $this->getDataLatestDocsByType($user);
        $lastDocsInvalidated = [];
        $allValidatedDocs = [];
        $arrayErrors = [];
        
        // Notifications KYC
        $kycStatus = $this->kycNotificationService->getKycNotificationStatus($user);
        $restartKycNotification = $this->kycNotificationService->getRestartKycNotification($user);

        // Compteurs header: messages non lus (chat) + notifications (KYC refusés + pending + activités)
        $conversationId = $this->chatMessageRepository->generateConversationId($user);
        $unreadMessagesCount = (int) $this->chatMessageRepository->countUnreadForUser($conversationId);
        
        // Récupérer toutes les activités récentes (pas seulement KYC)
        $recentActivities = $this->activityRepository->findLatestForUser($user, 20);
        $session = $this->requestStack->getSession();
        $readNotifications = $session->get('user_read_notifications', []);
        $unreadActivities = array_filter($recentActivities, function($activity) use ($readNotifications) {
            $activityKey = 'activity:' . $activity->getId();
            return !in_array($activityKey, $readNotifications);
        });
        
        $notificationsCount = count($unreadActivities);
        if (!empty($kycStatus)) {
            // Documents refusés non lus
            if ($kycStatus['hasRefusedDocuments'] ?? false) {
                foreach ($kycStatus['refusedDocuments'] ?? [] as $doc) {
                    $key = 'refused:' . $doc['id'];
                    if (!in_array($key, $readNotifications, true)) {
                        $notificationsCount++;
                    }
                }
            }
            // Documents en attente non lus
            if ($kycStatus['hasPendingDocuments'] ?? false) {
                foreach ($kycStatus['pendingDocuments'] ?? [] as $doc) {
                    $key = 'pending:' . $doc['id'];
                    if (!in_array($key, $readNotifications, true)) {
                        $notificationsCount++;
                    }
                }
            }
            // Notification de validation globale non lue
            if (($kycStatus['allDocumentsValidated'] ?? false) === true) {
                $key = 'validated:0';
                if (!in_array($key, $readNotifications, true)) {
                    $notificationsCount++;
                }
            }
        }

        // Préparation des informations de profil investisseur pour le front
        $investorProfile = $user->getInvestorProfile();
        $investorData = null;
        if ($investorProfile) {
            $labels = [
                InvestorProfileScorer::PROFILE_PRUDENT => 'Prudent',
                InvestorProfileScorer::PROFILE_EQUILIBRE => 'Équilibré',
                InvestorProfileScorer::PROFILE_DYNAMIQUE => 'Dynamique',
                InvestorProfileScorer::PROFILE_SPE => 'Sophistiqué',
            ];
            $investorData = [
                'type' => $investorProfile,
                'label' => $labels[$investorProfile] ?? $investorProfile,
                'score' => $user->getInvestorScore(),
                'calculatedAt' => $user->getInvestorProfileCalculatedAt(),
                'description' => $this->investorProfileScorer->getProfileDescription($investorProfile),
                'color' => $this->investorProfileScorer->getProfileColor($investorProfile),
                'recommendations' => $this->investorProfileScorer->getProductRecommendations($investorProfile),
                'questionnaireStatus' => $user->getQuestionnaireStatus(),
            ];
        }

        // Préparer un résumé Step 2 (Objectifs & Horizon)
        $kycStep2 = null;
        if ($user->getInfo()) {
            $info = $user->getInfo();
            $objectivesLabels = [];
            $objectivesStored = $info->getObjective() ?? [];
            $availableObjectives = method_exists($info, 'getObjectiveList') ? $info->getObjectiveList() : [];
            if (is_array($objectivesStored)) {
                foreach ($objectivesStored as $objKey) {
                    $objectivesLabels[] = $availableObjectives[$objKey] ?? (is_string($objKey) ? ucfirst($objKey) : (string) $objKey);
                }
            }
            $termsLabels = [];
            $termsMap = [
                '0' => 'Moins de 2 ans',
                '1' => 'Entre 2 et 6 ans',
                '2' => 'Plus de 6 ans',
                '3' => 'Je ne sais pas encore',
            ];
            $storedTerms = $info->getInvestmentTerm() ?? [];
            if (is_array($storedTerms)) {
                foreach ($storedTerms as $t) {
                    $termsLabels[] = $termsMap[(string) $t] ?? (string) $t;
                }
            }
            $liquidity = $info->getLiquidity();
            $sourceTxt = method_exists($info, 'getSourceOfFundsTxt') ? $info->getSourceOfFundsTxt() : null;

            $kycStep2 = [
                'objectives' => $objectivesLabels,
                'terms' => $termsLabels,
                'liquidity' => $liquidity,
                'source' => $sourceTxt,
                'hasData' => (count($objectivesLabels) + count($termsLabels)) > 0 || $liquidity !== null || !empty($sourceTxt),
            ];
        }

        // ─── Préparer les données du diagramme patrimoine (KYC uniquement, O2S chargé en AJAX) ───
        $patrimoineData = null;

        // Fallback : données KYC saisies par l'utilisateur (pas d'appel API O2S ici)
        if ($user->getInfo()) {
            $info = $user->getInfo();
            $patrimonyTotal = (float) ($info->getPatrimony() ?? 0);

            $realestate = (float) ($info->getRealestate() ?? 0);
            $kycLiquidity = (float) ($info->getLiquidity() ?? 0);
            $accountSecurities = (float) ($info->getAccountSecurities() ?? 0);
            $capitalisation = (float) ($info->getCapitalisation() ?? 0);
            $scpi = (float) ($info->getScpi() ?? 0);
            $rental = (float) ($info->getRental() ?? 0);
            $crowdfinance = method_exists($info, 'getCrowdfinance') ? (float) ($info->getCrowdfinance() ?? 0) : 0.0;
            $patrimonyOther = method_exists($info, 'getPatrimonyOther') ? (float) ($info->getPatrimonyOther() ?? 0) : 0.0;

            $hasAny = ($patrimonyTotal > 0)
                || ($realestate > 0) || ($kycLiquidity > 0) || ($accountSecurities > 0) || ($capitalisation > 0)
                || ($scpi > 0) || ($rental > 0) || ($crowdfinance > 0) || ($patrimonyOther > 0);

            if ($hasAny) {
                $realestateAmount = $realestate;
                $liquidityAmount = $kycLiquidity;
                $accountSecuritiesAmount = $accountSecurities;
                $capitalisationAmount = $capitalisation;
                $scpiAmount = $scpi;
                $rentalAmount = $rental;
                $crowdAmount = $crowdfinance;
                $otherAmount = $patrimonyOther;

                $looksLikePercent = ($patrimonyTotal > 0)
                    && ($realestate <= 100 && $kycLiquidity <= 100 && $accountSecurities <= 100 && $capitalisation <= 100
                        && $scpi <= 100 && $rental <= 100 && $crowdfinance <= 100 && $patrimonyOther <= 100);

                if ($looksLikePercent) {
                    $realestateAmount = ($patrimonyTotal * $realestate) / 100;
                    $liquidityAmount = ($patrimonyTotal * $kycLiquidity) / 100;
                    $accountSecuritiesAmount = ($patrimonyTotal * $accountSecurities) / 100;
                    $capitalisationAmount = ($patrimonyTotal * $capitalisation) / 100;
                    $scpiAmount = ($patrimonyTotal * $scpi) / 100;
                    $rentalAmount = ($patrimonyTotal * $rental) / 100;
                    $crowdAmount = ($patrimonyTotal * $crowdfinance) / 100;
                    $otherAmount = ($patrimonyTotal * $patrimonyOther) / 100;
                }

                $financialAssets = $accountSecuritiesAmount + $capitalisationAmount + $scpiAmount + $crowdAmount + $otherAmount + $liquidityAmount;
                $immobilierTotal = $realestateAmount + $rentalAmount;
                $totalActif = $immobilierTotal + $financialAssets;

                $charges = 0.0;
                $charges += (float) ($info->getRent() ?? 0);
                $charges += (float) ($info->getHousingLoad() ?? 0);
                $charges += (float) ($info->getExpenses() ?? 0);
                $charges += (float) ($info->getCompulsoryExpenses() ?? 0);
                $charges += (float) ($info->getOtherExpenses() ?? 0);
                $charges += (float) ($info->getTaxationAmount() ?? 0);
                if (($info->getTaxationAmount() ?? 0) === 0) {
                    $charges += (float) ($info->getTaxation() ?? 0);
                }
                
                $totalPassif = $charges > 0 ? $charges : ($patrimonyTotal > 0 ? max(0, $totalActif - $patrimonyTotal) : 0.0);
                $immobilierPercent = $totalActif > 0 ? (int) round(($immobilierTotal / $totalActif) * 100) : 0;
                $financialPercent = $totalActif > 0 ? (int) round(($financialAssets / $totalActif) * 100) : 0;

                $patrimoineData = [
                    'patrimoine' => $patrimonyTotal > 0 ? $patrimonyTotal : max(0, $totalActif - $totalPassif),
                    'actif' => $totalActif,
                    'passif' => $totalPassif,
                    'immobilier' => [
                        'montant' => $immobilierTotal,
                        'pourcentage' => $immobilierPercent,
                    ],
                    'actifs_financiers' => [
                        'montant' => $financialAssets,
                        'pourcentage' => $financialPercent,
                    ],
                    'hasData' => true,
                    'isDemoData' => false,
                ];
            }
        }

        // ─── NOTE PERF: Historiques + patrimoine O2S chargés via AJAX (endpoint api_dashboard_o2s_data) ───

        // Grouper les produits par distributeur (style MoneyPitch)
        $productsByDistributor = [];
        foreach ($productsSummary as $p) {
            $dist = $p['distributor'] ?? 'Autre';
            if (!isset($productsByDistributor[$dist])) {
                $productsByDistributor[$dist] = ['total' => 0.0, 'products' => []];
            }
            $productsByDistributor[$dist]['products'][] = $p;
            $productsByDistributor[$dist]['total'] += $p['encours'];
        }

        // Activités récentes
        $activities = $this->activityRepository->findLatestForUser($user, 5);

        return $this->render('front/user/dashboard/index.html.twig', [
            'mangopayInfo' => null,
            'totalEncoursAll' => $totalEncoursAll,
            'totalInitialAll' => $totalInitialAll,
            'totalVersementsAll' => $totalVersementsAll,
            'productsSummary' => $productsSummary,
            'totalsByType' => $totalsByType,
            // Historiques + gains initialement vides — enrichis via AJAX
            'historyChart' => [],
            'gainRealise' => null,
            'gainRealisePct' => null,
            'historyEvolutionPct' => null,
            'historyAccountsCount' => 0,
            'historyAccountsMissing' => 0,
            // variables historiques conservées pour compat mais neutralisées
            'refunds' => [],
            'refundDates' => [],
            'user' => $user,
            'kycDocsMissing' => $arrayKycDocsMissing,
            'lastDocsInvalidated' => $lastDocsInvalidated,
            'allValidatedDocs' => $allValidatedDocs,
            'arrayErrors' => $arrayErrors,
            'kycStatus' => $kycStatus,
            'restartKycNotification' => $restartKycNotification,
            'investor' => $investorData,
            'notificationsCount' => $notificationsCount,
            'kycStep2' => $kycStep2,
            'unreadMessagesCount' => $unreadMessagesCount,
            'patrimoineData' => $patrimoineData,
            'activities' => $activities,
            'productsByDistributor' => $productsByDistributor,
            'isO2SUser' => $user->isLinkedToO2S(),
        ]);
    }

    /**
     * Met à jour les colonnes array KYC (objective, investmentTerm, source_of_founds)
     * pour l'utilisateur donné si des chaînes sérialisées sont corrompues.
     */
    private function selfHealKycArraysForUser(int $userId): void
    {
        try {
            $conn = $this->entityManager->getConnection();
            $row = $conn->fetchAssociative('SELECT info_id FROM users_adn WHERE id = :id', ['id' => $userId]);
            if (!$row || empty($row['info_id'])) { return; }
            $infoId = (int) $row['info_id'];
            $info = $conn->fetchAssociative('SELECT objective, investmentTerm, source_of_founds FROM user_info WHERE id = :id', ['id' => $infoId]);
            if (!$info) { return; }
            $updates = [];
            foreach ([ 'objective', 'investmentTerm', 'source_of_founds' ] as $col) {
                $val = $info[$col] ?? null;
                if ($val === null || $val === '') { continue; }
                // Déjà valide ?
                $ok = @unserialize((string) $val);
                if ($ok !== false || $val === 'b:0;') { continue; }
                // Essayer JSON
                $json = json_decode((string) $val, true);
                if (is_array($json)) {
                    $updates[$col] = serialize(array_values($json));
                } else {
                    $updates[$col] = null; // fallback
                }
            }
            if (!empty($updates)) {
                $conn->update('user_info', $updates, ['id' => $infoId]);
            }
        } catch (\Throwable $e) {
            // Ne pas bloquer le dashboard
        }
    }

    // ─── Patrimoine detail pages ───

    #[Route('/patrimoine/immobilier', name: 'patrimoine_immobilier', methods: ['GET'])]
    public function patrimoineImmobilier(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $patrimoineData = $this->fetchPatrimoineData($user);
        if (!$patrimoineData || !$patrimoineData['hasData']) {
            $this->addFlash('warning', 'Aucune donnée patrimoine disponible.');
            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('front/user/dashboard/patrimoine_immobilier.html.twig', [
            'user' => $user,
            'patrimoineData' => $patrimoineData,
        ]);
    }

    #[Route('/patrimoine/financier', name: 'patrimoine_financier', methods: ['GET'])]
    public function patrimoineFinancier(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $patrimoineData = $this->fetchPatrimoineData($user);
        if (!$patrimoineData || !$patrimoineData['hasData']) {
            $this->addFlash('warning', 'Aucune donnée patrimoine disponible.');
            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('front/user/dashboard/patrimoine_financier.html.twig', [
            'user' => $user,
            'patrimoineData' => $patrimoineData,
        ]);
    }

    /**
     * Fetch patrimoine data (O2S or KYC fallback) for the given user.
     * @return array<string, mixed>|null
     */
    private function fetchPatrimoineData(User $user): ?array
    {
        // Priorité 1: O2S
        if ($user->isLinkedToO2S()) {
            try {
                $patrimoine = $this->contactService->getContactPatrimoine($user->getO2sContactId());
                if ($patrimoine->hasData()) {
                    return $patrimoine->toTemplateArray();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to fetch O2S patrimoine for detail page', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Priorité 2: KYC
        if ($user->getInfo()) {
            $info = $user->getInfo();
            $patrimonyTotal = (float) ($info->getPatrimony() ?? 0);
            $realestate = (float) ($info->getRealestate() ?? 0);
            $liquidity = (float) ($info->getLiquidity() ?? 0);
            $accountSecurities = (float) ($info->getAccountSecurities() ?? 0);
            $capitalisation = (float) ($info->getCapitalisation() ?? 0);
            $scpi = (float) ($info->getScpi() ?? 0);
            $rental = (float) ($info->getRental() ?? 0);

            $hasAny = ($patrimonyTotal > 0) || ($realestate > 0) || ($liquidity > 0) || ($accountSecurities > 0) || ($capitalisation > 0) || ($scpi > 0) || ($rental > 0);

            if ($hasAny) {
                $immobilierTotal = $realestate + $scpi + $rental;
                $financialAssets = $liquidity + $accountSecurities + $capitalisation;
                $totalActif = $immobilierTotal + $financialAssets;
                $totalPassif = 0;
                $immobilierPercent = $totalActif > 0 ? (int) round(($immobilierTotal / $totalActif) * 100) : 0;
                $financialPercent = $totalActif > 0 ? (int) round(($financialAssets / $totalActif) * 100) : 0;

                return [
                    'patrimoine' => $patrimonyTotal > 0 ? $patrimonyTotal : max(0, $totalActif - $totalPassif),
                    'actif' => $totalActif,
                    'passif' => $totalPassif,
                    'immobilier' => ['montant' => $immobilierTotal, 'pourcentage' => $immobilierPercent],
                    'actifs_financiers' => ['montant' => $financialAssets, 'pourcentage' => $financialPercent],
                    'hasData' => true,
                    'isDemoData' => false,
                    'isO2S' => false,
                ];
            }
        }

        return null;
    }

    #[Route('/produit/{id}', name: 'product_show', methods: ['GET'])]
    public function productShow(
        int $id,
        ProductValuationRefresher $refresher,
        TwelveDataClient $twelveDataClient,
        \App\Integration\O2S\Service\CompteServiceInterface $compteService,
        \App\Integration\O2S\Service\AssetServiceInterface $assetService,
        \App\Service\MarketData\QuoteAggregator $quoteAggregator,
        \App\Service\MarketData\LiveQuoteService $liveQuoteService,
        \App\Integration\O2S\Service\PamCalculationService $pamCalculationService,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $product = $this->entityManager->find(ProductAccount::class, $id);
        if (!$product || $product->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        // Vérifier si c'est un compte O2S
        $isO2SAccount = $product->getO2sCompteId() !== null;
        
        // Pour les comptes O2S, récupérer les données fraîches via l'API
        $o2sAccountDetails = null;
        $o2sCompte = null;
        $o2sAccountDetailsFailed = false;
        if ($isO2SAccount) {
            try {
                $o2sAccountDetails = $compteService->getAccountDetails($product->getO2sCompteId());
                $this->logger->debug('O2S account details fetched', [
                    'productId' => $id,
                    'totalValue' => $o2sAccountDetails->getTotalValue(),
                    'situationCount' => count($o2sAccountDetails->getSituation()),
                ]);
                // Si l'endpoint account-details a renvoyé un DTO vide (fallback 400/500), le signaler
                if (!$o2sAccountDetails->hasValuation() && empty($o2sAccountDetails->getSituation())) {
                    $o2sAccountDetailsFailed = true;
                }
            } catch (\Throwable $e) {
                $o2sAccountDetailsFailed = true;
                $this->logger->error('Failed to fetch O2S account details', [
                    'productId' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Récupérer aussi les infos CompteDTO pour versements / date ouverture
            try {
                $o2sCompte = $compteService->getCompte($product->getO2sCompteId());
                $this->logger->debug('O2S compte fetched', [
                    'productId' => $id,
                    'montant' => $o2sCompte->getMontant(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to fetch O2S compte', [
                    'productId' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ─── BRANCHE O2S : source de vérité = API ───
        if ($isO2SAccount) {
            return $this->renderO2SProduct(
                $user, $product, $o2sAccountDetails, $o2sCompte, $assetService, $quoteAggregator, $liveQuoteService, $pamCalculationService, $o2sAccountDetailsFailed
            );
        }

        // ─── BRANCHE CLASSIQUE (non-O2S) ───
            $refresher->refresh($product);

        $holdingRepo = $this->entityManager->getRepository(Holding::class);
        /** @var Holding[] $holdings */
        $holdings = $holdingRepo->findBy(['productAccount' => $product]);

        $encours = 0.0;
        $table = [];
        foreach ($holdings as $h) {
            $units = $h->getUnits() !== null ? (float) $h->getUnits() : 0.0;
            $price = $h->getLastPrice() !== null ? (float) $h->getLastPrice() : 0.0;
            $amount = $units * $price;
            if ($amount <= 0 && $h->getAmount() !== null) {
                $amount = (float) $h->getAmount();
            }
            $encours += $amount;

            $instr = $h->getInstrument();
            $chg = null;
            $chgPct = null;
            try {
                if ($instr && $instr->getSymbol()) {
                    $quote = $twelveDataClient->getQuote($instr->getSymbol());
                    if (is_array($quote) && isset($quote['percent_change'])) {
                        $chgPct = (float) $quote['percent_change'];
                    } else {
                        $series = $twelveDataClient->getTimeSeries($instr->getSymbol(), '1day', '1');
                        if (is_array($series) && count($series) >= 2) {
                            $last = isset($series[0]['close']) ? (float) $series[0]['close'] : null;
                            $prev = isset($series[1]['close']) ? (float) $series[1]['close'] : null;
                            if ($last !== null && $prev !== null && $prev != 0.0) {
                                $chg = $last - $prev;
                                $chgPct = ($chg / $prev) * 100.0;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // pas bloquant
            }
            $buyPrice = method_exists($h, 'getBuyPrice') ? ($h->getBuyPrice() !== null ? (float) $h->getBuyPrice() : null) : null;
            $buyCost = method_exists($h, 'getBuyCost') ? ($h->getBuyCost() !== null ? (float) $h->getBuyCost() : null) : null;
            $pnl = $buyCost !== null ? ($amount - $buyCost) : null;
            $dayChangeValue = null;
            if ($units > 0 && $chg !== null) {
                $dayChangeValue = $units * (float) $chg;
            } elseif ($chgPct !== null) {
                $dayChangeValue = $amount * ($chgPct / 100.0);
            }
            $table[] = [
                'isin' => $instr?->getIsin(),
                'name' => $instr?->getName(),
                'symbol' => $instr?->getSymbol(),
                'units' => $units,
                'price' => $price,
                'priceDate' => $h->getLastPriceDate(),
                'amount' => $amount,
                'change' => $chg,
                'changePct' => $chgPct,
                'buyPrice' => $buyPrice,
                'avgBuyPrice' => $buyPrice,
                'avgBuyPriceDate' => null,
                'pnl' => $pnl,
                'pnlPct' => ($buyCost !== null && $buyCost > 0) ? (($amount - $buyCost) / $buyCost * 100.0) : null,
                'percentage' => null, // sera calculé dans le template
                'dayChangeValue' => $dayChangeValue,
                'isO2S' => false,
                'assetType' => null,
                'assetClass' => null,
                'managementCompany' => null,
                'currency' => 'EUR',
            ];
        }

        $initial = (float) $product->getTotalInvested();
        $euroFund = $product->getEuroFund() !== null ? (float) $product->getEuroFund() : null;
        $ucCurrent = $encours;
        $encoursWithEuro = $encours + ($euroFund ?? 0.0);

        // Enrichir les lignes pour l'affichage avancé
        if ($ucCurrent > 0) {
            foreach ($table as $idx => $row) {
                $hasRealCost = isset($row['avgBuyPrice']) && $row['avgBuyPrice'] !== null;
                if (!$hasRealCost) {
                    $units = (float) ($row['units'] ?? 0.0);
                    $amount = (float) ($row['amount'] ?? 0.0);
                    $weight = $amount > 0 ? ($amount / $ucCurrent) : 0.0;
                    $estimatedCost = $initial > 0 ? ($initial * $weight) : null;
                    $avgBuyPrice = ($estimatedCost !== null && $units > 0) ? ($estimatedCost / $units) : null;
                    $pnl = $estimatedCost !== null ? ($amount - $estimatedCost) : null;
                    $table[$idx]['weight'] = $weight;
                    $table[$idx]['estimatedCost'] = $estimatedCost;
                    $table[$idx]['avgBuyPrice'] = $avgBuyPrice;
                    $table[$idx]['buyPrice'] = $avgBuyPrice;
                    $table[$idx]['pnl'] = $pnl;
                }
            }
        }

        return $this->render('front/user/product/show.html.twig', [
            'user' => $user,
            'product' => $product,
            'holdings' => $table,
            'encours' => $encoursWithEuro,
            'initial' => $initial,
            'plusMinus' => $encoursWithEuro - $initial,
            'plusMinusPct' => $initial > 0 ? (($encoursWithEuro - $initial) / $initial) * 100.0 : null,
            'euroFund' => $euroFund,
            'uc' => (float) $product->getInitialAmount(),
            'isO2S' => false,
            'o2sValuationDate' => null,
            'o2sLiquidity' => null,
            'versements' => null,
            'retraits' => null,
            'dateOuverture' => null,
            'accountNumber' => null,
            'hasPamComputed' => false,
        ]);
    }

    /**
     * Render détail produit O2S — source de vérité = API O2S.
     */
    private function renderO2SProduct(
        User $user,
        ProductAccount $product,
        ?\App\Integration\O2S\DTO\Compte\AccountDetailsDTO $accountDetails,
        ?\App\Integration\O2S\DTO\Compte\CompteDTO $compte,
        \App\Integration\O2S\Service\AssetServiceInterface $assetService,
        \App\Service\MarketData\QuoteAggregator $quoteAggregator,
        \App\Service\MarketData\LiveQuoteService $liveQuoteService,
        \App\Integration\O2S\Service\PamCalculationService $pamCalculationService,
        bool $accountDetailsFailed = false,
    ): Response
    {
        // ─── Valeurs principales depuis l'API ───
        $apiTotalValue = $accountDetails?->getTotalValue();
        $apiLiquidity = $accountDetails?->getLiquidity();
        $apiVersements = $accountDetails?->getVersements() ?? $compte?->getVersements();
        $apiRetraits = $accountDetails?->getRetraits() ?? $compte?->getRetraits();

        // ─── Valuation totale ───
        // Priorité : montant de /comptes/{id} (à jour) > totalValue de /account-details (snapshot potentiellement périmé) > cache local
        $compteMontant = $compte?->getMontant();
        $encours = $compteMontant ?? $apiTotalValue ?? (float) ($product->getO2sValuation() ?? 0);

        // Versements : API > 0 (on n'utilise PAS getTotalInvested() pour O2S car c'est pollué par la sync)
        $versements = $apiVersements;
        $retraits = $apiRetraits;
        
        // PnL calculé par support via : (cours actuel - prix moyen d'achat) × quantité
        // La donnée averagePriceValue est fournie par l'API Harvest (confirmé par leur support).
        $plusMinus = null;
        $plusMinusPct = null;
        $totalPnl = 0.0;
        $totalInvestedFromAvg = 0.0;
        $hasPnlData = false;

        // Date de valorisation
        // Priorité : dateValeur de /comptes/{id} (à jour, ex: 15/03/2026) > referenceDate du snapshot > cache local
        $valuationDate = $compte?->getDateValeur() ?? $accountDetails?->getValuationDate() ?? $product->getO2sValuationDate();
        // Date du snapshot : quand les supports ont été évalués individuellement (peut être plus ancienne)
        $snapshotDate = $accountDetails?->getValuationDate();
                
        // ─── Tableau des supports depuis la situation API ───
        $table = [];
        if ($accountDetails !== null && !empty($accountDetails->getSituation())) {
            // Collecter les assetIds pour enrichissement batch
                $assetIds = [];
            foreach ($accountDetails->getSituation() as $asset) {
                    if ($asset->getAssetId()) {
                        $assetIds[] = $asset->getAssetId();
                    }
                }
                
                $assetsInfo = [];
                if (!empty($assetIds)) {
                    try {
                        $assetsInfo = $assetService->getAssets($assetIds);
                    } catch (\Throwable $e) {
                        $this->logger->warning('Failed to fetch O2S assets info', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // ─── Résolution des cours actuels par ISIN via LiveQuoteService ───
                // L'API /account-details retourne un snapshot (possiblement ancien, ex: 10/02)
                // alors que les cours réels sont à jour. Le LiveQuoteService essaie d'abord
                // Boursorama (avec conversion FX BCE pour les fonds USD/GBP/CHF/...) puis
                // retombe sur TwelveData/Yahoo pour rétrocompat sur les fonds non couverts
                // par Boursorama.
                //
                // Voir : tests/Poc/test-resolver.php pour les benchmarks de fiabilité.
                $liveNavByIsin = [];
                foreach ($accountDetails->getSituation() as $asset) {
                    $isin = $assetsInfo[$asset->getAssetId() ?? '']?->getIsin() ?? $asset->getIsin();
                    $qty = $asset->getQuantity() ?? 0.0;
                    if ($isin && $qty > 0) {
                        try {
                            $liveQuote = $liveQuoteService->getLiveNavEur($isin);
                            if ($liveQuote['nav'] !== null) {
                                $liveNavByIsin[$isin] = [
                                    'nav' => $liveQuote['nav'],
                                    'navDate' => $liveQuote['navDate'],
                                    'source' => $liveQuote['source'],
                                    'isConverted' => $liveQuote['isConverted'],
                                    'nativeCurrency' => $liveQuote['nativeCurrency'],
                                    'fxRate' => $liveQuote['fxRate'],
                                ];
                            }
                        } catch (\Throwable $e) {
                            $this->logger->debug('LiveQuoteService: could not resolve ISIN', [
                                'isin' => $isin,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $assetIndex = 1;
            foreach ($accountDetails->getSituation() as $asset) {
                    $assetId = $asset->getAssetId();
                    $assetInfo = $assetsInfo[$assetId] ?? null;
                    
                    $assetName = $assetInfo?->getLabel() ?? $asset->getAssetName();
                    $assetIsin = $assetInfo?->getIsin() ?? $asset->getIsin();
                    if (!$assetName) {
                        $assetName = sprintf('Support UC #%d', $assetIndex);
                    }

                $snapshotValue = $asset->getValue() ?? 0.0;
                $qty = $asset->getQuantity() ?? 0.0;
                $snapshotNav = $asset->getNetAssetValue() ?? 0.0;

                $currentNav = $snapshotNav;
                $currentValue = $snapshotValue;
                $priceDate = $asset->getNetAssetValueDate() ?? $valuationDate;

                // Traçabilité de la source de prix utilisée (audit + badge UI)
                // null = snapshot O2S figé (cas par défaut)
                // 'boursorama' / 'boursorama+fx' / 'twelvedata|yahoo' = live nav
                $priceSource = null;
                $priceSourceNative = null;
                $priceSourceFxRate = null;

                // Si un cours actuel est disponible via LiveQuoteService et plus récent
                if ($qty > 0 && $assetIsin && isset($liveNavByIsin[$assetIsin])) {
                    $liveDate = null;
                    if ($liveNavByIsin[$assetIsin]['navDate']) {
                        try {
                            $liveDate = new \DateTimeImmutable($liveNavByIsin[$assetIsin]['navDate']);
                        } catch (\Exception) {}
                    }

                    $useLive = true;
                    if ($priceDate instanceof \DateTimeImmutable && $liveDate instanceof \DateTimeImmutable && $liveDate < $priceDate) {
                        $useLive = false;
                    }

                    if ($useLive) {
                        $currentNav = $liveNavByIsin[$assetIsin]['nav'];
                        $currentValue = round($currentNav * $qty, 2);
                        if ($liveDate) {
                            $priceDate = $liveDate;
                        }
                        $priceSource = $liveNavByIsin[$assetIsin]['source'] ?? null;
                        $priceSourceNative = $liveNavByIsin[$assetIsin]['nativeCurrency'] ?? null;
                        $priceSourceFxRate = $liveNavByIsin[$assetIsin]['fxRate'] ?? null;
                    }
                }

                $pct = $asset->getPercentage();
                if ($pct === null && $encours > 0 && $currentValue > 0) {
                    $pct = round(($currentValue / $encours) * 100.0, 2);
                }

                // PnL par support : (cours actuel - prix moyen) × quantité
                $avgPrice = $asset->getAverageBuyPrice();
                $avgPriceType = $asset->getAverageBuyPriceType();
                $linePnl = null;
                $linePnlPct = null;
                if ($avgPrice !== null && $avgPrice > 0 && $qty > 0) {
                    // PVDE = montant total investi (pas un prix par part)
                    // PAMD = prix moyen d'achat par part (le plus courant)
                    if ($avgPriceType === 'PVDE') {
                        $investedAmount = $avgPrice;
                    } else {
                        $investedAmount = $avgPrice * $qty;
                    }
                    $linePnl = round($currentValue - $investedAmount, 2);
                    if ($investedAmount > 0) {
                        $linePnlPct = round(($linePnl / $investedAmount) * 100.0, 2);
                    }
                    $totalPnl += $linePnl;
                    $totalInvestedFromAvg += $investedAmount;
                    $hasPnlData = true;
                }

                    $table[] = [
                        'isin' => $assetIsin,
                        'name' => $assetName,
                        'symbol' => $assetId,
                        'units' => $qty,
                        'price' => $currentNav,
                        'priceDate' => $priceDate,
                        'priceSource' => $priceSource,                       // 'boursorama' | 'boursorama+fx' | 'twelvedata|yahoo' | null (snapshot O2S)
                        'priceSourceNativeCurrency' => $priceSourceNative,   // ex: 'USD' si Bourso a converti
                        'priceSourceFxRate' => $priceSourceFxRate,           // taux appliqué si conversion
                        'amount' => round($currentValue, 2),
                        'avgBuyPrice' => $avgPrice,
                        'avgBuyPriceDate' => $asset->getAverageBuyPriceDate(),
                        'pnl' => $linePnl,
                        'pnlPct' => $linePnlPct,
                        'percentage' => $pct,
                        'change' => null,
                        'changePct' => null,
                        'dayChangeValue' => null,
                        'isO2S' => true,
                        'assetType' => $asset->getAssetType() ?? $assetInfo?->getAssetType(),
                        'assetClass' => $assetInfo?->getAssetClass(),
                        'managementCompany' => $assetInfo?->getManagementCompany(),
                        'currency' => $asset->getCurrency() ?? 'EUR',
                    ];
                    $assetIndex++;
                }
                
            $this->logger->debug('O2S holdings built', [
                'productId' => $product->getId(),
                'holdingsCount' => count($table),
                'liveNavCount' => count($liveNavByIsin),
            ]);

            // Fallback PAM: for supports without API-provided PAM, compute from history
            $needsComputedPam = false;
            foreach ($table as $row) {
                if ($row['avgBuyPrice'] === null && $row['units'] > 0) {
                    $needsComputedPam = true;
                    break;
                }
            }

            if ($needsComputedPam) {
                $computedPams = $pamCalculationService->computePamForAccount($product->getO2sCompteId());

                if (!empty($computedPams)) {
                    foreach ($table as $idx => &$row) {
                        if ($row['avgBuyPrice'] !== null || $row['units'] <= 0) {
                            continue;
                        }
                        $assetKey = $row['symbol'];
                        if (!isset($computedPams[$assetKey])) {
                            continue;
                        }

                        $cPam = $computedPams[$assetKey]['pam'];
                        $pamSource = $computedPams[$assetKey]['source'] ?? 'computed';
                        $qty = $row['units'];
                        $currentValue = $row['amount'];

                        $investedAmount = $cPam * $qty;
                        $linePnl = round($currentValue - $investedAmount, 2);
                        $linePnlPct = $investedAmount > 0
                            ? round(($linePnl / $investedAmount) * 100.0, 2)
                            : null;

                        $row['avgBuyPrice'] = $cPam;
                        $row['pnl'] = $linePnl;
                        $row['pnlPct'] = $linePnlPct;
                        $row['pamSource'] = $pamSource;
                        $row['pamComputed'] = ($pamSource === 'computed');

                        $totalPnl += $linePnl;
                        $totalInvestedFromAvg += $investedAmount;
                        $hasPnlData = true;
                    }
                    unset($row);
                }
            }
        }

        if ($hasPnlData) {
            $plusMinus = round($totalPnl, 2);
            $plusMinusPct = $totalInvestedFromAvg > 0
                ? round(($totalPnl / $totalInvestedFromAvg) * 100.0, 2)
                : null;
        }

        $hasPamComputed = false;
        foreach ($table as $row) {
            if (!empty($row['pamComputed'])) {
                $hasPamComputed = true;
                break;
            }
        }

        return $this->render('front/user/product/show.html.twig', [
            'user' => $user,
            'product' => $product,
            'holdings' => $table,
            'encours' => $encours,
            'initial' => null,
            'plusMinus' => $plusMinus,
            'plusMinusPct' => $plusMinusPct,
            'euroFund' => null,
            'uc' => null,
            'isO2S' => true,
            'o2sValuationDate' => $valuationDate,
            'o2sSnapshotDate' => $snapshotDate,
            'o2sLiquidity' => $apiLiquidity,
            'versements' => null,
            'retraits' => $retraits,
            'dateOuverture' => $compte?->getDateOuverture(),
            'accountNumber' => $compte?->getNumero() ?? $product->getO2sCompteId(),
            'accountDetailsFailed' => $accountDetailsFailed,
            'hasPamComputed' => $hasPamComputed,
        ]);
    }

    #[Route('/dashboard/notifications', name: 'dashboard_notifications', methods: ['GET'])]
    public function notifications(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $this->kycNotificationService->getKycNotificationStatus($user);

        // Récupérer toutes les activités récentes (pas seulement KYC)
        $recentActivities = $this->activityRepository->findLatestForUser($user, 20);
        
        // Récupérer les IDs des notifications déjà lues depuis la session
        $session = $request->getSession();
        $readNotifications = $session->get('user_read_notifications', []);
        
        // Filtrer les activités non lues
        $unreadActivities = array_filter($recentActivities, function($activity) use ($readNotifications) {
            $activityKey = 'activity:' . $activity->getId();
            return !in_array($activityKey, $readNotifications);
        });

        // Compteurs header
        $conversationId = $this->chatMessageRepository->generateConversationId($user);
        $unreadMessagesCount = (int) $this->chatMessageRepository->countUnreadForUser($conversationId);
        $notificationsCount = count($unreadActivities);
        
        // Ajouter les notifications KYC classiques (seulement si non lues)
        if (!empty($status)) {
            // Documents refusés non lus
            if ($status['hasRefusedDocuments'] ?? false) {
                foreach ($status['refusedDocuments'] ?? [] as $doc) {
                    $key = 'refused:' . $doc['id'];
                    if (!in_array($key, $readNotifications, true)) {
                        $notificationsCount++;
                    }
                }
            }
            // Documents en attente non lus
            if ($status['hasPendingDocuments'] ?? false) {
                foreach ($status['pendingDocuments'] ?? [] as $doc) {
                    $key = 'pending:' . $doc['id'];
                    if (!in_array($key, $readNotifications, true)) {
                        $notificationsCount++;
                    }
                }
            }
            // Notification de validation globale non lue
            if (($status['allDocumentsValidated'] ?? false) === true) {
                $key = 'validated:0';
                if (!in_array($key, $readNotifications, true)) {
                    $notificationsCount++;
                }
            }
        }

        return $this->render('front/user/dashboard/notifications.html.twig', [
            'user' => $user,
            'kycStatus' => $status,
            'notificationsCount' => $notificationsCount,
            'unreadMessagesCount' => $unreadMessagesCount,
            'recentActivities' => $recentActivities,
            'unreadActivities' => $unreadActivities,
        ]);
    }

    #[Route('/dashboard/notifications/read/{type}/{id}', name: 'dashboard_notifications_read', requirements: ['id' => '\\d+'])]
    public function notificationsRead(string $type, int $id, Request $request): Response
    {
        // Marque localement en "lu" via session (simple décrémentation côté UI)
        $session = $request->getSession();
        $read = $session->get('user_read_notifications', []);
        $read[] = $type . ':' . $id;
        $session->set('user_read_notifications', $read);

        // Redirection vers la page pertinente selon le type de notification
        if ($type === 'refused' || $type === 'pending' || $type === 'validated') {
            // Pour les notifications KYC, rediriger vers la page des documents
            // Si c'est un document spécifique, on peut ajouter un fragment pour le document
            if ($id > 0) {
                return $this->redirectToRoute('user_documents', ['_fragment' => 'document-' . $id]);
            }
            return $this->redirectToRoute('user_documents');
        }
        if ($type === 'activity') {
            // Pour les activités, rediriger vers le dashboard où se trouve le bloc d'activités
            // On ajoute un anchor pour scroller directement vers les activités
            return $this->redirectToRoute('user_dashboard', ['_fragment' => 'activities']);
        }
        // Par défaut, retourner au dashboard
        return $this->redirectToRoute('user_dashboard');
    }

    #[Route('/dashboard/notifications/read-all', name: 'dashboard_notifications_read_all', methods: ['POST'])]
    public function notificationsReadAll(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Vérification CSRF
        if (!$this->isCsrfTokenValid('read_all_notifications', $request->request->get('_token'))) {
            $this->addFlash('error', 'Action non autorisée');
            return $this->redirectToRoute('user_dashboard_notifications');
        }
        
        $session = $request->getSession();
        $read = $session->get('user_read_notifications', []);
        
        // Marquer toutes les activités comme lues
        $recentActivities = $this->activityRepository->findLatestForUser($user, 50);
        foreach ($recentActivities as $activity) {
            $activityKey = 'activity:' . $activity->getId();
            if (!in_array($activityKey, $read, true)) {
                $read[] = $activityKey;
            }
        }
        
        // Marquer aussi les notifications KYC comme lues
        $status = $this->kycNotificationService->getKycNotificationStatus($user);
        if (!empty($status['refusedDocuments'])) {
            foreach ($status['refusedDocuments'] as $doc) {
                $key = 'refused:' . $doc['id'];
                if (!in_array($key, $read, true)) {
                    $read[] = $key;
                }
            }
        }
        if (!empty($status['pendingDocuments'])) {
            foreach ($status['pendingDocuments'] as $doc) {
                $key = 'pending:' . $doc['id'];
                if (!in_array($key, $read, true)) {
                    $read[] = $key;
                }
            }
        }
        if (!empty($status['allDocumentsValidated']) && $status['allDocumentsValidated'] === true) {
            $key = 'validated:0';
            if (!in_array($key, $read, true)) {
                $read[] = $key;
            }
        }
        
        $session->set('user_read_notifications', $read);
        
        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues');
        return $this->redirectToRoute('user_dashboard_notifications');
    }

    #[Route("/documents", name: "documents")]
    public function documents(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Récupérer tous les documents KYC de l'utilisateur
        $kycDocuments = $this->kycDocumentRepository->findBy(
            ['user' => $user], 
            ['createdAt' => 'DESC']
        );
        
        // Grouper les documents par type
        $documentsByType = [];
        foreach ($kycDocuments as $document) {
            $type = $document->getType();
            if (!isset($documentsByType[$type])) {
                $documentsByType[$type] = [];
            }
            $documentsByType[$type][] = $document;
        }
        
        // Types de documents disponibles - dépend du type de compte
        if ($user->isPro()) {
            // Comptes professionnels
            $documentTypes = [
                'identity' => 'Pièce d\'identité',
                'registration_proof' => 'Extrait KBIS',
                'articles_of_association' => 'Statuts',
                'shareholder_declaration' => 'Déclaration des bénéficiaires effectifs',
            ];
        } else {
            // Comptes particuliers
            $documentTypes = [
                'identity' => 'Pièce d\'identité',
                'address' => 'Justificatif de domicile',
                // Certaines anciennes données peuvent avoir pour type ADDRESS_PROOF
                'ADDRESS_PROOF' => 'Justificatif d\'adresse',
            ];
        }
        
        return $this->render('front/user/dashboard/documents.html.twig', [
            'user' => $user,
            'kycDocuments' => $kycDocuments,
            'documentsByType' => $documentsByType,
            'documentTypes' => $documentTypes,
        ]);
    }

    #[Route('/documents/view/{id}', name: 'document_view', methods: ['GET'])]
    public function viewUserDocument(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $document = $this->kycDocumentRepository->find($id);
        if (!$document || $document->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès non autorisé à ce document.');
        }

        // Déterminer un chemin de fichier valide
        $filename = null;
        $filePath = null;

        $files = $document->getFiles();
        if (method_exists($files, 'isEmpty') && !$files->isEmpty()) {
            $file = $files->first();
            if (method_exists($file, 'getName')) {
                $filename = $file->getName();
                $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/' . $filename;
            }
        } elseif ($document->getFilename()) {
            $filename = $document->getFilename();
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
            $this->addFlash('error', "Le fichier n'existe pas sur le serveur.");
            return $this->redirectToRoute('user_documents');
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']) || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return $this->file($filePath, null, ResponseHeaderBag::DISPOSITION_INLINE);
        }

        if (in_array($mimeType, ['application/pdf']) || $extension === 'pdf') {
            return $this->file($filePath, $filename, ResponseHeaderBag::DISPOSITION_INLINE);
        }

        return $this->file($filePath, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    // Le préfixe de classe ajoute déjà "user_" au nom de route → garder un nom court ici
    #[Route('/documents/upload', name: 'document_upload', methods: ['POST'])]
    public function uploadDocument(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $type = (string) $request->request->get('type', '');
        $file = $request->files->get('file');

        $typeMap = [
            'identity' => KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF,
            'IDENTITY_PROOF' => KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF,
            'address' => KycDocument::DOCUMENT_TYPE_ADDRESS_PROOF,
            'ADDRESS_PROOF' => KycDocument::DOCUMENT_TYPE_ADDRESS_PROOF,
            'registration_proof' => KycDocument::DOCUMENT_TYPE_REGISTRATION_PROOF,
            'articles_of_association' => KycDocument::DOCUMENT_TYPE_ARTICLES_OF_ASSOCIATION,
            'shareholder_declaration' => KycDocument::DOCUMENT_TYPE_SHAREHOLDER_DECLARATION,
        ];

        if (!$file || !isset($typeMap[$type])) {
            return new JsonResponse(['ok' => false, 'error' => 'Paramètres invalides'], 400);
        }

        try {
            // Validation basique (taille/mime)
            $tmpDoc = new KycDocument();
            if ($tmpDoc->preValidate($file) === false) {
                return new JsonResponse(['ok' => false, 'error' => 'Fichier non valide'], 400);
            }

            // Sauvegarde physique simple (sans Vich)
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $originalName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '_', $originalName) ?: 'document';
            $extension = strtolower((string) $file->guessExtension() ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
            $finalName = sprintf('%s_%s.%s', $safeBase, uniqid('', true), $extension ?: 'bin');
            $file->move($uploadDir, $finalName);

            // Enregistrement BDD
            $doc = new KycDocument();
            $doc->setUser($user);
            $doc->setType($typeMap[$type]);
            $doc->setStatus(KycDocument::STATUS_PENDING);
            $doc->setUploadedAt(new \DateTime());
            $doc->setFilename($finalName);

            $this->entityManager->persist($doc);
            $this->entityManager->flush();

            // Feedback front + compatibilité non-AJAX
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => true]);
            }

            $this->addFlash('success', 'Document envoyé avec succès');
            return $this->redirectToRoute('user_documents');
        } catch (\Throwable $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'Erreur serveur'], 500);
            }
            $this->addFlash('error', 'Erreur lors de l\'envoi du document');
            return $this->redirectToRoute('user_documents');
        }
    }

    #[Route("/profile", name: "profile")]
    public function profile(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('front/user/dashboard/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route("/profile/edit", name: "profile_edit", methods: ["GET", "POST"])]
    public function profileEdit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            // Sauvegarder l'ancien email pour détecter le changement
            $oldEmail = $user->getEmail();
            
            // Mise à jour des informations de base
            // Note: firstName et lastName sont ignorés car ils sont verrouillés dans le formulaire
            if (isset($data['email'])) {
                $newEmail = trim($data['email']);
                if ($newEmail !== $oldEmail) {
                    $user->setEmail($newEmail);
                    // Marquer l'email comme non vérifié si changement
                    $user->setIsVerified(false);
                    
                    // Créer une notification Activity pour le back-office
                    $activity = new UserActivity();
                    $activity->setUser($user)
                        ->setTitle('Changement d\'adresse email')
                        ->setMessage(sprintf(
                            'L\'utilisateur %s %s (%s) a modifié son adresse email de "%s" vers "%s".',
                            $user->getFirstName(),
                            $user->getLastName(),
                            $user->getId(),
                            $oldEmail,
                            $newEmail
                        ))
                        ->setLevel('warning');
                    $this->entityManager->persist($activity);
                }
            }
            
            if (isset($data['phone'])) {
                $user->setPhone($data['phone']);
            }
            if (isset($data['address'])) {
                $user->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $user->setCity($data['city']);
            }
            if (isset($data['postalCode'])) {
                $user->setPostalCode($data['postalCode']);
            }
            if (isset($data['country'])) {
                $user->setCountry($data['country']);
            }
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Profil mis à jour avec succès');
            
            return $this->redirectToRoute('user_profile');
        }
        
        return $this->render('front/user/dashboard/profile_edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route("/settings", name: "settings")]
    public function settings(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('front/user/dashboard/settings.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route("/settings/password", name: "settings_password", methods: ["POST"])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');
        
        // Vérifications
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Mot de passe actuel incorrect');
            return $this->redirectToRoute('user_settings');
        }
        
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas');
            return $this->redirectToRoute('user_settings');
        }
        
        if (strlen($newPassword) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères');
            return $this->redirectToRoute('user_settings');
        }
        
        // Mise à jour du mot de passe
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Mot de passe modifié avec succès');
        
        return $this->redirectToRoute('user_settings');
    }

    #[Route("/kyc/restart", name: "kyc_restart")]
    public function kycRestart(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$this->kycNavigationService->canRestartKyc($user)) {
            $this->addFlash('error', 'Vous ne pouvez pas refaire le parcours KYC pour le moment');
            return $this->redirectToRoute('user_dashboard');
        }
        
        return $this->render('front/user/dashboard/kyc_restart.html.twig', [
            'user' => $user,
            'restartMessage' => $this->kycNavigationService->getRestartMessage($user),
        ]);
    }

    #[Route("/kyc/restart/confirm", name: "kyc_restart_confirm", methods: ["POST"])]
    public function kycRestartConfirm(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$this->kycNavigationService->canRestartKyc($user)) {
            $this->addFlash('error', 'Vous ne pouvez pas refaire le parcours KYC pour le moment');
            return $this->redirectToRoute('user_dashboard');
        }
        
        // Mode édition non destructif: on repart à l'étape 1 sans supprimer les données existantes
        $user->setStepKyc(User::STEP_KYC_PROFILE);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Activer le mode édition KYC dans la session
        $this->requestStack->getSession()->set('kyc_edit_mode', true);

        $this->addFlash('success', 'Édition KYC activée. Vous pouvez mettre à jour vos informations et documents sans perdre vos données.');
        
        return $this->redirectToRoute('user_create_profile', ['step' => 1]);
    }

    #[Route("/portfolio", name: "portfolio")]
    public function portfolio(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // ─── NOTE PERF: Plus aucun appel API O2S ou MarketData ici ───
        // Les données sont lues depuis la BDD (ProductAccount + Holding).
        // Les données enrichies (historiques, patrimoine, versements, gains) sont chargées via AJAX.
        
        $productRepo = $this->entityManager->getRepository(ProductAccount::class);
        /** @var ProductAccount[] $products */
        $products = $productRepo->findBy(['user' => $user], ['id' => 'ASC']);
        
        $holdingRepo = $this->entityManager->getRepository(Holding::class);
        
        $totalValue = 0.0;
        $totalInvested = 0.0;
        $productsSummary = [];
        
        foreach ($products as $product) {
            /** @var Holding[] $holdings */
            $holdings = $holdingRepo->findBy(['productAccount' => $product]);
            
            // Somme des valeurs UC (ISIN) basée sur lastPrice*units ou fallback amount persisté
            $ucValue = 0.0;
            foreach ($holdings as $h) {
                $units = $h->getUnits() !== null ? (float) $h->getUnits() : 0.0;
                $price = $h->getLastPrice() !== null ? (float) $h->getLastPrice() : 0.0;
                $amount = $units * $price;
                if ($amount <= 0 && $h->getAmount() !== null) {
                    $amount = (float) $h->getAmount();
                }
                $ucValue += $amount;
            }
            
            // O2S fallback : si pas de holdings mais produit O2S, utiliser la valorisation O2S
            $isO2SProduct = $product->getO2sCompteId() !== null;
            if ($ucValue <= 0 && $isO2SProduct) {
                $ucValue = $product->getO2sValuation() !== null
                    ? (float) $product->getO2sValuation()
                    : 0.0;
            }
            
            $euroFund = $product->getEuroFund() !== null ? (float) $product->getEuroFund() : 0.0;
            $encours = $ucValue + $euroFund;
            $initial = (float) $product->getTotalInvested();
            
            $totalValue += $encours;
            $totalInvested += $initial;

            // Distributeur : utiliser celui en BDD (déjà résolu par le sync)
            $distributor = $product->getDistributor();
            
            $productsSummary[] = [
                'id' => $product->getId(),
                'name' => $product->getDisplayAlias() ?: ('Produit #'.$product->getId()),
                'type' => $product->getProductType(),
                'distributor' => $distributor,
                'encours' => $encours,
                'initial' => $initial,
                'versements' => null, // Enrichi via AJAX
                'gain' => null,       // Enrichi via AJAX
                'perfPct' => null,    // Enrichi via AJAX
                'euroFund' => $product->getEuroFund() !== null ? (float) $product->getEuroFund() : null,
                'uc' => $ucValue,
                'isO2S' => $isO2SProduct,
                'o2sValuationDate' => $product->getO2sValuationDate(),
                'reference' => $product->getO2sCompteId(),
            ];
        }

        // Grouper par distributeur (style MoneyPitch)
        $productsByDistributor = [];
        foreach ($productsSummary as $p) {
            $dist = $p['distributor'] ?? 'Autre';
            if (!isset($productsByDistributor[$dist])) {
                $productsByDistributor[$dist] = ['total' => 0.0, 'products' => []];
            }
            $productsByDistributor[$dist]['products'][] = $p;
            $productsByDistributor[$dist]['total'] += $p['encours'];
        }

        // Totaux par type pour l'analyse
        $totalsByType = [];
        foreach ($productsSummary as $p) {
            $type = $p['type'];
            if (!isset($totalsByType[$type])) {
                $totalsByType[$type] = ['encours' => 0.0, 'count' => 0];
            }
            $totalsByType[$type]['encours'] += $p['encours'];
            $totalsByType[$type]['count'] += 1;
        }
        
        // Récupération des activités récentes
        $activities = $this->activityRepository->findLatestForUser($user, 5);

        return $this->render('front/user/dashboard/portfolio.html.twig', [
            'user' => $user,
            'products' => $productsSummary,
            'totalValue' => $totalValue,
            'totalInvested' => $totalInvested,
            'activities' => $activities,
            'productsByDistributor' => $productsByDistributor,
            'totalsByType' => $totalsByType,
            // Historiques + gains initialement vides — enrichis via AJAX
            'historyChart' => [],
            'gainRealise' => null,
            'gainRealisePct' => null,
            'historyEvolutionPct' => null,
            'historyAccountsCount' => 0,
            'historyAccountsMissing' => 0,
            'isO2SUser' => $user->isLinkedToO2S(),
        ]);
    }

    /**
     * AJAX endpoint: retourne les données O2S enrichies (historiques, patrimoine, versements, gains).
     * Appelé au chargement du dashboard pour enrichir la page sans bloquer le rendu initial.
     */
    #[Route("/api/dashboard-o2s-data", name: "api_dashboard_o2s_data", methods: ["GET"])]
    public function dashboardO2sData(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isLinkedToO2S()) {
            return new JsonResponse(['success' => true, 'hasO2S' => false]);
        }

        $productRepo = $this->entityManager->getRepository(ProductAccount::class);
        /** @var ProductAccount[] $userProducts */
        $userProducts = $productRepo->findBy(['user' => $user]);

        // ─── AUTO-SYNC: Si l'utilisateur O2S n'a aucun product_account, synchroniser automatiquement ───
        $hasO2SAccounts = false;
        foreach ($userProducts as $p) {
            if ($p->getO2sCompteId() !== null) {
                $hasO2SAccounts = true;
                break;
            }
        }

        if (!$hasO2SAccounts) {
            try {
                $this->logger->info('Auto-sync: User has O2S link but no O2S product accounts, triggering sync', [
                    'userId' => $user->getId(),
                    'o2sContactId' => $user->getO2sContactId(),
                ]);
                $syncResult = $this->o2sSyncService->syncComptesForUser($user);
                $this->entityManager->flush();

                if ($syncResult->getCreated() > 0) {
                    $this->logger->info('Auto-sync: Created product accounts for user', [
                        'userId' => $user->getId(),
                        'created' => $syncResult->getCreated(),
                    ]);
                    // Recharger les produits depuis la BDD
                    $userProducts = $productRepo->findBy(['user' => $user]);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Auto-sync failed for user', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ─── 1. Charger Products + Institutions maps (cached 24h) ───
        $productsMap = [];
        $institutionsMap = [];
        try {
            $productsMap = $this->productService->getProductsMap();
            $institutionsMap = $this->institutionService->getInstitutionsMap();
        } catch (\Throwable $e) {
            $this->logger->warning('AJAX: Failed to load O2S maps', ['error' => $e->getMessage()]);
        }

        // ─── 2. Charger versements + institutions depuis CompteDTO ───
        $o2sVersementsMap = [];
        $o2sInstitutionLabelMap = [];
        $o2sCompteIds = [];
        foreach ($userProducts as $product) {
            if ($product->getO2sCompteId() !== null) {
                $o2sCompteIds[$product->getO2sCompteId()] = $product;
            }
        }
        foreach ($o2sCompteIds as $compteId => $product) {
            try {
                $compte = $this->compteService->getCompte($compteId);
                $o2sVersementsMap[$compteId] = $compte->getVersements();

                $produitId = $compte->getProduitId();
                if ($produitId && isset($productsMap[$produitId])) {
                    $institutionId = $productsMap[$produitId]->getInstitutionId();
                    if ($institutionId && isset($institutionsMap[$institutionId])) {
                        $o2sInstitutionLabelMap[$compteId] = $institutionsMap[$institutionId];
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->debug('AJAX: Failed to fetch O2S compte', [
                    'compteId' => $compteId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ─── 3. Historiques (6 mois) ───
        $dateFrom = (new \DateTime('-6 months'))->format('Y-m-d');
        $dateTo = (new \DateTime())->format('Y-m-d');
        $startOfYear = (new \DateTime('first day of January this year'))->format('Y-m-d');

        $allHistories = [];
        foreach ($o2sCompteIds as $compteId => $product) {
            try {
                $history = $this->compteService->getAccountDetailsHistory($compteId, $dateFrom, $dateTo);
                if (!empty($history)) {
                    $allHistories[$compteId] = $history;
                }
            } catch (\Throwable $e) {
                // Certains comptes ne supportent pas l'historique
            }
        }

        $totalO2sAccounts = count($o2sCompteIds);
        $historyAccountsCount = count($allHistories);
        $historyAccountsMissing = $totalO2sAccounts - $historyAccountsCount;

        // Agréger l'historique global
        $historyChart = [];
        $gainRealise = null;
        $gainRealisePct = null;
        $historyEvolutionPct = null;

        if (!empty($allHistories)) {
            $allDates = [];
            foreach ($allHistories as $history) {
                foreach ($history as $point) {
                    $allDates[$point['date']] = true;
                }
            }
            ksort($allDates);

            $aggregated = [];
            foreach (array_keys($allDates) as $date) {
                $total = 0.0;
                $accountsInDate = 0;
                foreach ($allHistories as $history) {
                    $closest = null;
                    foreach ($history as $point) {
                        if ($point['date'] <= $date) {
                            $closest = $point;
                        }
                    }
                    if ($closest !== null) {
                        $total += $closest['totalValue'] + $closest['liquidity'];
                        $accountsInDate++;
                    }
                }
                if ($accountsInDate === $historyAccountsCount) {
                    $aggregated[] = ['date' => $date, 'value' => round($total, 2)];
                }
            }

            // Point "aujourd'hui"
            // o2sValuation = montant total de /comptes/{id} (inclut UC + fonds euros + liquidité)
            // NE PAS ajouter euroFund en plus — ce serait du double-comptage
            $currentHistoryTotal = 0.0;
            foreach ($allHistories as $cId => $history) {
                $p = $o2sCompteIds[$cId] ?? null;
                if ($p !== null) {
                    $currentHistoryTotal += (float) ($p->getO2sValuation() ?? 0);
                }
            }
            $today = (new \DateTime())->format('Y-m-d');
            if (!empty($aggregated) && $aggregated[count($aggregated) - 1]['date'] !== $today) {
                $aggregated[] = ['date' => $today, 'value' => round($currentHistoryTotal, 2)];
            }

            $historyChart = $aggregated;

            if (count($aggregated) >= 2) {
                $first = $aggregated[0]['value'];
                $last = $aggregated[count($aggregated) - 1]['value'];
                if ($first > 0) {
                    $historyEvolutionPct = round((($last - $first) / $first) * 100.0, 2);
                }
            }

            $startOfYearValue = null;
            foreach ($aggregated as $point) {
                if ($point['date'] >= $startOfYear) {
                    $startOfYearValue = $point['value'];
                    break;
                }
            }
            if ($startOfYearValue === null && !empty($aggregated)) {
                foreach (array_reverse($aggregated) as $point) {
                    if ($point['date'] < $startOfYear) {
                        $startOfYearValue = $point['value'];
                        break;
                    }
                }
            }

            if ($startOfYearValue !== null && $startOfYearValue > 0) {
                $currentValue = $aggregated[count($aggregated) - 1]['value'];
                $gainRealise = round($currentValue - $startOfYearValue, 2);
                $gainRealisePct = round((($currentValue - $startOfYearValue) / $startOfYearValue) * 100.0, 2);
            }
        }

        // ─── 4. Historiques par contrat ───
        $perProductHistories = [];
        foreach ($allHistories as $compteId => $history) {
            $product = $o2sCompteIds[$compteId] ?? null;
            if ($product === null) continue;
            $productId = $product->getId();

            // o2sValuation = montant total (inclut tout), ne pas ajouter euroFund
            $currentVal = (float) ($product->getO2sValuation() ?? 0);
            $points = array_map(fn($h) => ['date' => $h['date'], 'value' => round($h['totalValue'] + $h['liquidity'], 2)], $history);
            $today = (new \DateTime())->format('Y-m-d');
            if (!empty($points) && $points[count($points) - 1]['date'] !== $today) {
                $points[] = ['date' => $today, 'value' => round($currentVal, 2)];
            }

            $contractGain = null;
            $contractGainPct = null;
            if (count($points) >= 2) {
                $soyVal = null;
                foreach ($points as $pt) {
                    if ($pt['date'] >= $startOfYear) { $soyVal = $pt['value']; break; }
                }
                if ($soyVal === null) {
                    foreach (array_reverse($points) as $pt) {
                        if ($pt['date'] < $startOfYear) { $soyVal = $pt['value']; break; }
                    }
                }
                if ($soyVal !== null && $soyVal > 0) {
                    $lastVal = $points[count($points) - 1]['value'];
                    $contractGain = round($lastVal - $soyVal, 2);
                    $contractGainPct = round((($lastVal - $soyVal) / $soyVal) * 100.0, 2);
                }
            }

            // Évolution 6 mois : nécessite au moins 2 points API réels (pas juste le snapshot d'aujourd'hui)
            $contractEvoPct = null;
            $apiPointCount = count($history);
            if ($apiPointCount >= 2 && count($points) >= 2) {
                $f = $points[0]['value'];
                $l = $points[count($points) - 1]['value'];
                if ($f > 0) {
                    $contractEvoPct = round((($l - $f) / $f) * 100.0, 2);
                }
            }

            $perProductHistories[$productId] = [
                'chart' => $apiPointCount >= 2 ? $points : [],
                'gain' => $contractGain,
                'gainPct' => $contractGainPct,
                'evoPct' => $contractEvoPct,
            ];
        }

        // ─── 5. Versements par produit ───
        $productVersements = [];
        $productDistributors = [];
        foreach ($userProducts as $product) {
            $cId = $product->getO2sCompteId();
            if ($cId !== null) {
                if (isset($o2sVersementsMap[$cId])) {
                    $productVersements[$product->getId()] = $o2sVersementsMap[$cId];
                }
                if (isset($o2sInstitutionLabelMap[$cId])) {
                    $productDistributors[$product->getId()] = $o2sInstitutionLabelMap[$cId];
                }
            }
        }

        // ─── 6. Patrimoine O2S ───
        $patrimoineData = null;
        if ($user->getO2sContactId()) {
            try {
                $patrimoineO2S = $this->contactService->getContactPatrimoine($user->getO2sContactId());
                if ($patrimoineO2S->hasData()) {
                    $patrimoineData = $patrimoineO2S->toTemplateArray();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('AJAX: Failed to fetch O2S patrimoine', ['error' => $e->getMessage()]);
            }
        }

        // ─── Calculer le total des placements financiers (pour mise à jour dynamique après auto-sync) ───
        $totalPlacementsFinanciers = 0.0;
        foreach ($userProducts as $product) {
            if ($product->getO2sCompteId() !== null) {
                $totalPlacementsFinanciers += (float) ($product->getO2sValuation() ?? 0);
            } else {
                $euroFund = $product->getEuroFund() !== null ? (float) $product->getEuroFund() : 0.0;
                $totalPlacementsFinanciers += (float) ($product->getO2sValuation() ?? $product->getInitialAmount() ?? 0) + $euroFund;
            }
        }

        return new JsonResponse([
            'success' => true,
            'hasO2S' => true,
            'historyChart' => $historyChart,
            'gainRealise' => $gainRealise,
            'gainRealisePct' => $gainRealisePct,
            'historyEvolutionPct' => $historyEvolutionPct,
            'historyAccountsCount' => $historyAccountsCount,
            'historyAccountsMissing' => $historyAccountsMissing,
            'perProductHistories' => $perProductHistories,
            'productVersements' => $productVersements,
            'productDistributors' => $productDistributors,
            'patrimoineData' => $patrimoineData,
            'totalPlacementsFinanciers' => $totalPlacementsFinanciers,
            'autoSynced' => !$hasO2SAccounts && count($userProducts) > 0,
        ]);
    }

    /**
     * AJAX endpoint: rafraîchit les valorisations O2S depuis l'API et retourne les données à jour.
     * Appelé automatiquement au chargement du dashboard/portfolio pour mise à jour en arrière-plan.
     *
     * Logique de valorisation (alignée sur le portail O2S) :
     *   evaluation = /comptes/{id}.montant  (valeur des UC/titres, date fraîche)
     *                + /accounts/{id}/account-details.liquidity  (trésorerie/espèces)
     */
    #[Route("/api/refresh-valuations", name: "api_refresh_valuations", methods: ["POST"])]
    public function refreshValuations(
        \App\Integration\O2S\Service\CompteServiceInterface $compteService,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $productRepo = $this->entityManager->getRepository(ProductAccount::class);
        /** @var ProductAccount[] $products */
        $products = $productRepo->findBy(['user' => $user]);

        $updated = [];
        $totalEncours = 0.0;

        foreach ($products as $product) {
            $isO2S = $product->getO2sCompteId() !== null;
            if (!$isO2S) {
                // Produits non-O2S : garder la valeur existante
                $euroFund = $product->getEuroFund() !== null ? (float) $product->getEuroFund() : 0.0;
                $encours = (float) ($product->getO2sValuation() ?? $product->getInitialAmount() ?? 0) + $euroFund;
                $totalEncours += $encours;
                $updated[] = [
                    'id' => $product->getId(),
                    'encours' => $encours,
                    'valuationDate' => null,
                    'changed' => false,
                    'distributor' => $product->getDistributor(),
                ];
                continue;
            }

            $compteId = $product->getO2sCompteId();
            $oldVal = (float) ($product->getO2sValuation() ?? 0);
            $montantVal = 0.0;        // valeur titres/UC depuis /comptes/{id}
            $newDate = $product->getO2sValuationDate();
            $liquidity = 0.0;         // trésorerie/espèces depuis account-details
            $detailTotalValue = 0.0;  // totalValue depuis account-details (UC value)
            $hasMontant = false;

            try {
                // 1. /comptes/{id} → montant (valeur titres/UC) + dateValeur (la plus fraîche)
                $compte = $compteService->getCompte($compteId);
                if ($compte->getMontant() !== null && $compte->getMontant() > 0) {
                    $montantVal = $compte->getMontant();
                    $newDate = $compte->getDateValeur();
                    $hasMontant = true;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Refresh valuation failed for O2S account (comptes)', [
                    'productId' => $product->getId(),
                    'compteId' => $compteId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                // 2. /accounts/{id}/account-details → totalValue + liquidity
                //    Le portail O2S utilise : totalValue (UC) + liquidity (espèces)
                $details = $compteService->getAccountDetails($compteId);
                if ($details->hasValuation()) {
                    $detailTotalValue = $details->getTotalValue() ?? 0.0;
                    $apiLiquidity = $details->getLiquidity();
                    if ($apiLiquidity !== null && $apiLiquidity > 0) {
                        $liquidity = $apiLiquidity;
                    }
                    if (!$hasMontant && $details->getValuationDate()) {
                        $newDate = $details->getValuationDate();
                    }
                }
            } catch (\Throwable $e) {
                // account-details échoue pour certains contrats (ex. 400 blocage) → on continue sans liquidité
                $this->logger->debug('account-details unavailable for liquidity', [
                    'compteId' => $compteId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Évaluation totale :
            //   - montant de /comptes/{id} = valorisation TOTALE (UC + fonds euros + liquidité)
            //     → NE PAS ajouter liquidity dessus, sinon double comptage !
            //   - totalValue + liquidity de /accounts/account-details = reconstruction du total
            //     → Utilisé uniquement quand montant n'est pas disponible
            if ($hasMontant) {
                // montant = valeur totale du contrat (déjà tout inclus)
                $evaluation = $montantVal;
            } elseif ($detailTotalValue > 0 || $liquidity > 0) {
                // Reconstruction depuis account-details : UC + espèces
                $evaluation = $detailTotalValue + $liquidity;
            } else {
                $evaluation = 0.0;
            }

            // Si aucune API n'a retourné de valeur, garder l'ancienne
            if ($evaluation <= 0 && $oldVal > 0) {
                $evaluation = $oldVal;
            }

            // Mettre à jour le cache BDD avec l'évaluation complète
            $product->setO2sValuation((string) $evaluation);
            if ($newDate) {
                $product->setO2sValuationDate($newDate);
            }
            $product->setO2sSyncedAt(new \DateTimeImmutable());

            // Pour les produits O2S, o2sValuation contient DÉJÀ tout (UC + fonds euros + liquidité)
            // → ne PAS ajouter euroFund
            $encours = $evaluation;
            $totalEncours += $encours;

            $updated[] = [
                'id' => $product->getId(),
                'encours' => $encours,
                'valuationDate' => $newDate instanceof \DateTimeInterface ? $newDate->format('d/m/Y') : null,
                'changed' => abs($evaluation - $oldVal) > 0.01,
                'distributor' => $product->getDistributor(),
            ];
        }

        // Persister les nouvelles valeurs en BDD
        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to flush updated O2S valuations', [
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'totalEncours' => $totalEncours,
            'products' => $updated,
        ]);
    }

    #[Route("/opportunities", name: "opportunities")]
    public function opportunities(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('front/user/dashboard/opportunities.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route("/opportunities/download/{type}", name: "opportunities_download", methods: ["GET"])]
    public function opportunitiesDownload(string $type, Request $request): Response
    {
        $type = strtoupper($type);
        $em = $this->entityManager;
        $repo = $em->getRepository(\App\Entity\InvestmentProductDocument::class);

        // Récupérer le document le plus récent pour ce type
        $doc = $repo->findOneBy(['productType' => $type], ['updatedAt' => 'DESC']);
        if (!$doc) {
            $this->addFlash('error', "Aucun document disponible pour ce produit.");
            return $this->redirectToRoute('user_opportunities');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/product-documents/' . $doc->getFileName();
        if (!file_exists($filePath)) {
            $this->addFlash('error', "Le fichier est manquant sur le serveur.");
            return $this->redirectToRoute('user_opportunities');
        }

        // Mapper le type pour le tracking
        $productTypeMap = [
            'SCPI' => 'SCPI',
            'PEA_PME' => 'PEA-PME',
            'ASSURANCE_VIE' => 'Assurance-vie',
            'PER' => 'PER'
        ];
        $productType = $productTypeMap[$type] ?? $type;

        // Tracker le téléchargement
        try {
            $clickRepo = $em->getRepository(\App\Entity\InvestmentOpportunityClick::class);
            $clickRepo->recordClick(
                $productType,
                \App\Entity\InvestmentOpportunityClick::ACTION_DOCUMENTS,
                $this->getUser(),
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                $request->headers->get('Referer')
            );
        } catch (\Exception $e) {
            // Ne pas bloquer le téléchargement en cas d'erreur de tracking
            error_log('Erreur lors du tracking du téléchargement: ' . $e->getMessage());
        }

        // Inline pour PDF/images, sinon téléchargement
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $disposition = in_array($mime, ['application/pdf','image/jpeg','image/png','image/gif','image/webp'])
            ? \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE
            : \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $this->file($filePath, $doc->getFileName(), $disposition);
    }
    #[Route("/advisor", name: "advisor")]
    public function advisor(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->render('front/user/dashboard/advisor.html.twig', [
            'user' => $user,
        ]);
    }

    private function getDataLatestDocsByType(User $user): array
    {
        $arrayDataLatestKycDocs = [];
        
        // Récupérer les documents KYC manquants ou en attente
        $kycDocuments = $this->kycDocumentRepository->findBy(['user' => $user]);
        
        $requiredTypes = ['identity', 'address'];
        if ($user->isPro()) {
            $requiredTypes = array_merge($requiredTypes, ['registration_proof', 'articles_of_association', 'shareholder_declaration']);
        }
        
        foreach ($requiredTypes as $type) {
            $hasValidDocument = false;
            foreach ($kycDocuments as $doc) {
                if ($doc->getType() === $type && $doc->getStatus() === KycDocument::STATUS_VALIDATED) {
                    $hasValidDocument = true;
                    break;
                }
            }
            
            if (!$hasValidDocument) {
                $arrayDataLatestKycDocs[$type] = true;
            }
        }
        
        return $arrayDataLatestKycDocs;
    }

    #[Route("/message/new", name: "message_new", methods: ["GET", "POST"])]
    public function newMessage(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $message = new UserMessage();
        $message->setSender($user);
        
        $form = $this->createForm(UserMessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userMessageRepository->save($message, true);
            
            $this->addFlash('success', 'Votre message a été envoyé avec succès. Notre équipe vous répondra dans les plus brefs délais.');
            
            return $this->redirectToRoute('user_messages');
        }

        return $this->render('front/user/message/new.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route("/messages", name: "messages", methods: ["GET"])]
    public function messages(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $messages = $this->userMessageRepository->findByUser($user);

        return $this->render('front/user/message/list.html.twig', [
            'messages' => $messages,
            'user' => $user,
        ]);
    }

    #[Route("/message/{id}", name: "message_show", methods: ["GET"])]
    public function showMessage(UserMessage $message): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur connecté est bien l'expéditeur du message
        if ($message->getSender() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce message.');
        }

        return $this->render('front/user/message/show.html.twig', [
            'message' => $message,
            'user' => $user,
        ]);
    }
}

