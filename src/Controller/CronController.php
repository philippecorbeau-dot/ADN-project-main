<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ProductAccount;
use App\Integration\O2S\Sync\O2SSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Endpoints sécurisés pour les tâches planifiées (cron).
 * 
 * Sur OVH mutualisé, les commandes CLI (SSH) sont bloquées pour les appels
 * sortants vers Harvest/O2S. Les crons doivent donc passer par le serveur web
 * (Apache) via des appels HTTP, ce qui contourne le firewall OVH.
 * 
 * Sécurité : chaque appel doit inclure un token secret (CRON_SECRET).
 */
#[Route('/cron', name: 'cron_')]
class CronController extends AbstractController
{
    public function __construct(
        private readonly O2SSyncService $syncService,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Synchronisation incrémentale O2S (nouveaux contacts + comptes manquants).
     * Conçu pour être appelé toutes les 15 minutes par le cron OVH.
     * 
     * URL: /cron/o2s-sync-incremental?token=CRON_SECRET
     */
    #[Route('/o2s-sync-incremental', name: 'o2s_sync_incremental', methods: ['GET'])]
    public function syncIncremental(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->logger->info('[CRON] Starting incremental O2S sync');
        $startTime = microtime(true);

        try {
            set_time_limit(120);

            // Étape 1 : Détecter et créer les nouveaux contacts
            $contactsResult = $this->syncService->syncNewContacts();

            // Étape 2 : Synchroniser les comptes manquants
            $comptesResult = $this->syncService->syncMissingComptes(50);

            $duration = round(microtime(true) - $startTime, 1);

            $this->logger->info('[CRON] Incremental sync completed', [
                'contacts_created' => $contactsResult->getCreated(),
                'comptes_created' => $comptesResult->getCreated(),
                'comptes_updated' => $comptesResult->getUpdated(),
                'duration' => $duration,
            ]);

            return $this->json([
                'success' => true,
                'action' => 'incremental',
                'contacts' => [
                    'created' => $contactsResult->getCreated(),
                    'updated' => $contactsResult->getUpdated(),
                    'errors' => count($contactsResult->getErrors()),
                ],
                'comptes' => [
                    'created' => $comptesResult->getCreated(),
                    'updated' => $comptesResult->getUpdated(),
                    'errors' => count($comptesResult->getErrors()),
                ],
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Incremental sync failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronisation complète O2S (tous les contacts + tous les comptes).
     * Conçu pour être appelé une fois par jour (ex: 3h du matin).
     * 
     * URL: /cron/o2s-sync-full?token=CRON_SECRET
     */
    #[Route('/o2s-sync-full', name: 'o2s_sync_full', methods: ['GET'])]
    public function syncFull(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->logger->info('[CRON] Starting full O2S sync');
        $startTime = microtime(true);

        try {
            set_time_limit(600); // 10 minutes max

            $results = $this->syncService->syncAll();
            $duration = round(microtime(true) - $startTime, 1);

            $this->logger->info('[CRON] Full sync completed', [
                'contacts' => $results['contacts']->toArray(),
                'comptes' => $results['comptes']->toArray(),
                'duration' => $duration,
            ]);

            return $this->json([
                'success' => true,
                'action' => 'full',
                'contacts' => [
                    'created' => $results['contacts']->getCreated(),
                    'updated' => $results['contacts']->getUpdated(),
                    'skipped' => $results['contacts']->getSkipped(),
                    'errors' => count($results['contacts']->getErrors()),
                ],
                'comptes' => [
                    'created' => $results['comptes']->getCreated(),
                    'updated' => $results['comptes']->getUpdated(),
                    'skipped' => $results['comptes']->getSkipped(),
                    'errors' => count($results['comptes']->getErrors()),
                ],
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Full sync failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mise à jour des valorisations par lot.
     * Conçu pour être appelé toutes les 2 heures.
     * 
     * URL: /cron/o2s-sync-valuations?token=CRON_SECRET&batch_size=50
     */
    #[Route('/o2s-sync-valuations', name: 'o2s_sync_valuations', methods: ['GET'])]
    public function syncValuations(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $batchSize = min((int) ($request->query->get('batch_size', 50)), 100);

        $this->logger->info('[CRON] Starting valuations sync', ['batch_size' => $batchSize]);
        $startTime = microtime(true);

        try {
            set_time_limit(180);

            $result = $this->syncService->syncValuationsBatch($batchSize);
            $duration = round(microtime(true) - $startTime, 1);

            $this->logger->info('[CRON] Valuations sync completed', [
                'updated' => $result->getUpdated(),
                'errors' => count($result->getErrors()),
                'duration' => $duration,
            ]);

            return $this->json([
                'success' => true,
                'action' => 'valuations',
                'updated' => $result->getUpdated(),
                'errors' => count($result->getErrors()),
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Valuations sync failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Correction des emails placeholder.
     * 
     * URL: /cron/o2s-fix-emails?token=CRON_SECRET
     */
    #[Route('/o2s-fix-emails', name: 'o2s_fix_emails', methods: ['GET'])]
    public function fixEmails(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->logger->info('[CRON] Starting fix placeholder emails');
        $startTime = microtime(true);

        try {
            set_time_limit(300);

            $result = $this->syncService->fixPlaceholderEmails();
            $duration = round(microtime(true) - $startTime, 1);

            return $this->json([
                'success' => true,
                'action' => 'fix-emails',
                'fixed' => $result->getMetadata('fixed') ?? 0,
                'noEmail' => $result->getMetadata('noEmail') ?? 0,
                'conflicts' => $result->getMetadata('conflicts') ?? 0,
                'conflictsResolved' => $result->getMetadata('conflictsResolved') ?? 0,
                'remaining' => $result->getMetadata('remaining') ?? 0,
                'errors' => count($result->getErrors()),
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Fix emails failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Préchauffe le cache O2S de tous les users (versements, historiques 6 mois, patrimoine).
     *
     * Délègue à la commande Symfony `o2s:warm-cache`. Les données préchauffées
     * restent en cache 24h, ce qui rend le dashboard utilisateur quasi-instantané.
     *
     * Pour tester sur un user uniquement : ajouter &user_id=XXX
     *
     * URL: /cron/o2s-warm-cache?token=CRON_SECRET[&user_id=XXX][&skip_history=1][&limit=N]
     */
    #[Route('/o2s-warm-cache', name: 'o2s_warm_cache', methods: ['GET'])]
    public function warmCache(Request $request, \Symfony\Component\HttpKernel\KernelInterface $kernel): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $userId = $request->query->get('user_id');
        $skipHistory = (bool) $request->query->get('skip_history', false);
        $limit = $request->query->get('limit');
        $offset = $request->query->get('offset');

        $this->logger->info('[CRON] Starting O2S cache warming', [
            'user_id' => $userId,
            'skip_history' => $skipHistory,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        $startTime = microtime(true);

        try {
            set_time_limit(3000); // 50 min max

            $args = ['command' => 'o2s:warm-cache'];
            if ($userId) {
                $args['--user-id'] = (string) $userId;
            }
            if ($skipHistory) {
                $args['--skip-history'] = true;
            }
            if ($limit) {
                $args['--limit'] = (string) $limit;
            }
            if ($offset !== null && $offset !== '') {
                $args['--offset'] = (string) $offset;
            }

            $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
            $application->setAutoExit(false);
            $input = new \Symfony\Component\Console\Input\ArrayInput($args);
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            $exitCode = $application->run($input, $output);
            $consoleOutput = $output->fetch();

            $duration = round(microtime(true) - $startTime, 1);

            // Extraction rapide des stats depuis la sortie console (format SymfonyStyle table)
            $stats = [];
            foreach ([
                'users_done' => '/Utilisateurs traités\s+\|\s+(\d+)/',
                'users_with_errors' => '/Utilisateurs avec erreur\(s\)\s+\|\s+(\d+)/',
                'cache_keys_purged' => '/Caches purgés \(delete\)\s+\|\s+(\d+)/',
                'comptes_warmed' => '/Comptes préchauffés.*?\|\s+(\d+)/',
                'history_warmed' => '/Historiques préchauffés.*?\|\s+(\d+)/',
                'patrimoine_warmed' => '/Patrimoines préchauffés\s+\|\s+(\d+)/',
                'errors' => '/Erreurs cumulées\s+\|\s+(\d+)/',
            ] as $key => $regex) {
                if (preg_match($regex, $consoleOutput, $m)) {
                    $stats[$key] = (int) $m[1];
                }
            }

            return $this->json([
                'success' => $exitCode === 0,
                'action' => 'warm-cache',
                'exit_code' => $exitCode,
                'stats' => $stats,
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Cache warming failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Classify contacts (Client/Prospect) depuis Harvest, par lots.
     * 
     * Traite un lot de contacts à chaque appel pour rester dans les limites
     * de timeout d'OVH mutualisé (~60s). Le JS côté admin rappelle en boucle.
     * 
     * URL: /cron/o2s-classify-contacts?token=CRON_SECRET&batch=10&batch=10
     */
    #[Route('/o2s-classify-contacts', name: 'o2s_classify_contacts', methods: ['GET'])]
    public function classifyContacts(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $batchSize = min((int) $request->query->get('batch', 10), 30);
        $startTime = microtime(true);

        try {
            $stats = $this->syncService->backfillTypeContacts($batchSize);
            $duration = round(microtime(true) - $startTime, 1);

            return $this->json([
                'success' => true,
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
                'remaining' => $stats['remaining'],
                'total' => $stats['total'],
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Classify contacts failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force la synchronisation des comptes pour un utilisateur spécifique.
     * Utile quand un nouveau contrat est ajouté dans O2S et doit remonter immédiatement.
     * 
     * URL: /cron/o2s-sync-user-comptes?token=CRON_SECRET&email=xxx@yyy.com
     */
    #[Route('/o2s-sync-user-comptes', name: 'o2s_sync_user_comptes', methods: ['GET'])]
    public function syncUserComptes(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $email = $request->query->get('email', '');
        if (empty($email)) {
            return $this->json(['error' => 'Missing "email" parameter'], 400);
        }

        $startTime = microtime(true);

        try {
            set_time_limit(120);

            $user = $this->entityManager->getRepository(\App\Entity\User\User::class)
                ->findOneBy(['email' => $email]);

            if (!$user) {
                return $this->json(['error' => "User not found: $email"], 404);
            }

            if (!$user->getO2sContactId()) {
                return $this->json(['error' => "User $email has no O2S contact linked"], 400);
            }

            $existingBefore = $this->entityManager->getRepository(ProductAccount::class)
                ->findBy(['user' => $user]);
            $existingIds = array_map(fn($a) => $a->getO2sCompteId(), $existingBefore);

            $result = $this->syncService->syncComptesForUser($user);
            $this->entityManager->flush();

            $existingAfter = $this->entityManager->getRepository(ProductAccount::class)
                ->findBy(['user' => $user]);

            $accounts = [];
            foreach ($existingAfter as $account) {
                $accounts[] = [
                    'id' => $account->getId(),
                    'o2sCompteId' => $account->getO2sCompteId(),
                    'alias' => $account->getDisplayAlias(),
                    'distributor' => $account->getDistributor(),
                    'type' => $account->getProductType(),
                    'isNew' => !in_array($account->getO2sCompteId(), $existingIds),
                ];
            }

            $duration = round(microtime(true) - $startTime, 1);

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'o2sContactId' => $user->getO2sContactId(),
                ],
                'sync' => [
                    'created' => $result->getCreated(),
                    'updated' => $result->getUpdated(),
                    'errors' => $result->getErrors(),
                    'before_count' => count($existingBefore),
                    'after_count' => count($existingAfter),
                ],
                'accounts' => $accounts,
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Sync user comptes failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronise les comptes de TOUS les utilisateurs O2S par lot.
     * Détecte automatiquement les nouveaux contrats ajoutés dans O2S.
     * Utilise offset/batch_size pour paginer et éviter les timeouts OVH.
     * Relancer avec offset incrémenté tant que hasMore=true.
     *
     * URL: /cron/o2s-sync-all-comptes?token=CRON_SECRET&batch_size=15&offset=0
     */
    #[Route('/o2s-sync-all-comptes', name: 'o2s_sync_all_comptes', methods: ['GET'])]
    public function syncAllComptes(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $batchSize = min((int) ($request->query->get('batch_size', 15)), 50);
        $offset = max(0, (int) $request->query->get('offset', 0));
        $startTime = microtime(true);

        try {
            set_time_limit(180);

            $batch = $this->syncService->syncComptesBatch($offset, $batchSize);
            $result = $batch['result'];
            $duration = round(microtime(true) - $startTime, 1);

            return $this->json([
                'success' => true,
                'action' => 'sync-all-comptes',
                'offset' => $offset,
                'batch_size' => $batchSize,
                'processed' => $batch['processed'],
                'total' => $batch['total'],
                'hasMore' => $batch['hasMore'],
                'next_offset' => $batch['hasMore'] ? $batch['processed'] : null,
                'comptes' => [
                    'created' => $result->getCreated(),
                    'updated' => $result->getUpdated(),
                    'errors' => count($result->getErrors()),
                ],
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[CRON] Sync all comptes failed', [
                'offset' => $offset,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Diagnostic — retourne le JSON brut de /accounts/{id}/account-details
     * pour identifier la "vue" actuelle (SITUATION_VUE_O2S vs SITUATION_VUE_PARTENAIRE).
     * 
     * URL: /cron/o2s-debug-raw?token=CRON_SECRET&accountId=XXX
     * Optionnel : &view=SITUATION_VUE_O2S pour forcer la vue
     */
    #[Route('/o2s-debug-raw', name: 'o2s_debug_raw', methods: ['GET'])]
    public function debugRaw(
        Request $request,
        \App\Integration\O2S\Client\O2SClientInterface $client,
        \App\Integration\O2S\Config\O2SConfiguration $config,
    ): JsonResponse {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $accountId = $request->query->get('accountId', '');
        if (empty($accountId)) {
            return $this->json(['error' => 'Missing "accountId" parameter'], 400);
        }

        $view = $request->query->get('view'); // optionnel
        $endpointOverride = $request->query->get('endpoint'); // optionnel : tester un autre endpoint

        try {
            set_time_limit(60);
            $endpoint = $endpointOverride ?: '/accounts/' . $accountId . '/account-details';
            $params = [];
            if ($view) {
                $params['view'] = $view;
            }
            // Pass-through tous les autres params (date, dateValeur, referenceDate, etc.)
            // pour pouvoir tester n'importe quelle hypothèse
            foreach ($request->query->all() as $k => $v) {
                if (in_array($k, ['token', 'accountId', 'view', 'endpoint'], true)) {
                    continue;
                }
                $params[$k] = $v;
            }

            $data = $client->get($endpoint, $params, $config->getApiUrl());

            return $this->json([
                'success' => true,
                'accountId' => $accountId,
                'requestedView' => $view ?: '(default - no param)',
                'endpoint' => $endpoint,
                'queryParams' => $params,
                'raw' => $data,
            ], 200, [], ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'accountId' => $accountId,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check simple pour vérifier que le cron fonctionne.
     * 
     * URL: /cron/health?token=CRON_SECRET
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(Request $request): JsonResponse
    {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        return $this->json([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'php_version' => PHP_VERSION,
            'placeholder_emails' => $this->syncService->countPlaceholderEmails(),
            'linked_users' => $this->syncService->getLinkedUsersCount(),
        ]);
    }

    /**
     * Résout un batch d'assetIds O2S → {assetId: {name, isin}}.
     * Utile pour décoder en lot des codes assetId numériques en vraies métadonnées.
     *
     * URL: /cron/asset-lookup?token=CRON_SECRET&ids=948766,6312,813454
     */
    #[Route('/asset-lookup', name: 'asset_lookup', methods: ['GET'])]
    public function assetLookup(
        Request $request,
        \App\Integration\O2S\Service\AssetServiceInterface $assetService,
    ): JsonResponse {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }
        set_time_limit(120);

        $ids = array_filter(array_map('trim', explode(',', (string) $request->query->get('ids', ''))));
        if (empty($ids)) {
            return $this->json(['error' => 'Missing ids'], 400);
        }
        if (count($ids) > 100) {
            return $this->json(['error' => 'Max 100 ids per call'], 400);
        }

        $start = microtime(true);
        $out = [];
        $errors = 0;
        foreach ($ids as $id) {
            try {
                $a = $assetService->getAsset($id);
                $out[$id] = [
                    'name' => $a?->getLabel(),
                    'isin' => $a?->getIsin(),
                    'currency' => $a?->getCurrency(),
                ];
            } catch (\Throwable $e) {
                $out[$id] = ['error' => $e->getMessage()];
                $errors++;
            }
        }
        return $this->json([
            'ok' => true,
            'duration_seconds' => round(microtime(true) - $start, 1),
            'count' => count($ids),
            'errors' => $errors,
            'assets' => $out,
        ], 200, [], ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Cartographie tous les "ISIN-like" non couverts par Bourso (codes QS propriétaires,
     * codes internes assureur, etc.) à travers TOUS les comptes O2S. Permet de
     * construire un plan de mapping vers de vrais ISIN.
     *
     * URL: /cron/vl-unknown-codes?token=CRON_SECRET[&limit=100][&offset=0]
     */
    #[Route('/vl-unknown-codes', name: 'vl_unknown_codes', methods: ['GET'])]
    public function vlUnknownCodes(
        Request $request,
        \App\Integration\O2S\Service\CompteServiceInterface $compteService,
        \App\Integration\O2S\Service\AssetServiceInterface $assetService,
        \App\Service\MarketData\LiveQuoteService $liveQuoteService,
    ): JsonResponse {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }
        set_time_limit(120);

        $start = microtime(true);
        $limit = $request->query->getInt('limit', 80);
        $offset = $request->query->getInt('offset', 0);

        $qb = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $accounts = $qb->getQuery()->getResult();

        $isinRegex = '/^[A-Z]{2}[A-Z0-9]{9}\d$/';
        $byCode = [];
        $failed = 0;
        foreach ($accounts as $account) {
            try {
                $details = $compteService->getAccountDetails($account->getO2sCompteId());
                foreach ($details->getSituation() as $line) {
                    // On ne fait PAS d'appel AssetService ici (lent) — on s'appuie
                    // uniquement sur ce que le DTO situation expose directement.
                    $code = $line->getIsin() ?? $line->getAssetId() ?? '';
                    if ($code === '') continue;
                    $name = $line->getAssetName();
                    $val = (float) ($line->getValue() ?? 0);
                    $qty = (float) ($line->getQuantity() ?? 0);
                    $nav = (float) ($line->getNetAssetValue() ?? 0);
                    if ($qty <= 0 || $val <= 0) continue;

                    if (!isset($byCode[$code])) {
                        $byCode[$code] = [
                            'code' => $code,
                            'name' => $name,
                            'isIsinFormat' => (bool) preg_match($isinRegex, $code),
                            'accounts' => 0,
                            'totalValue' => 0.0,
                            'sample_navDate' => $line->getNetAssetValueDate()?->format('Y-m-d'),
                            'sample_nav' => $nav,
                            'sample_accountIds' => [],
                            'bourso_status' => null,
                        ];
                    }
                    $byCode[$code]['accounts']++;
                    $byCode[$code]['totalValue'] += $val;
                    if (count($byCode[$code]['sample_accountIds']) < 3) {
                        $byCode[$code]['sample_accountIds'][] = $account->getId();
                    }
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        // Tester chaque code via LiveQuoteService pour savoir s'il est résolvable
        foreach ($byCode as $code => &$info) {
            if (!$info['isIsinFormat']) {
                $info['bourso_status'] = 'not_isin_format';
                continue;
            }
            try {
                $r = $liveQuoteService->getLiveNavEur($code);
                $info['bourso_status'] = $r['nav'] !== null ? 'ok' : 'not_found_bourso';
            } catch (\Throwable) {
                $info['bourso_status'] = 'error';
            }
        }
        unset($info);

        // Tri : non résolus en haut, par valeur décroissante
        usort($byCode, function ($a, $b) {
            if ($a['bourso_status'] === 'ok' && $b['bourso_status'] !== 'ok') return 1;
            if ($a['bourso_status'] !== 'ok' && $b['bourso_status'] === 'ok') return -1;
            return $b['totalValue'] <=> $a['totalValue'];
        });

        $unresolved = array_filter($byCode, fn($i) => $i['bourso_status'] !== 'ok');
        $totalUnresolved = array_sum(array_column($unresolved, 'totalValue'));
        $totalAll = array_sum(array_column($byCode, 'totalValue'));

        $duration = round(microtime(true) - $start, 1);
        $totalAccounts = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')->select('COUNT(p.id)')
            ->where('p.o2sCompteId IS NOT NULL')->getQuery()->getSingleScalarResult();

        return $this->json([
            'ok' => true,
            'duration_seconds' => $duration,
            'offset' => $offset,
            'limit' => $limit,
            'accountsScanned' => count($accounts),
            'accountsFailed' => $failed,
            'totalAccountsInDb' => (int) $totalAccounts,
            'hasMore' => ($offset + count($accounts)) < (int) $totalAccounts,
            'nextOffset' => ($offset + count($accounts)) < (int) $totalAccounts ? $offset + count($accounts) : null,
            'codeCount' => count($byCode),
            'unresolvedCount' => count($unresolved),
            'totalValue_all' => round($totalAll, 2),
            'totalValue_unresolved' => round($totalUnresolved, 2),
            'unresolved_share_pct' => $totalAll > 0 ? round($totalUnresolved / $totalAll * 100, 2) : 0,
            'codes' => array_values($byCode),
        ], 200, [], ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Diagnostic — affiche le détail enrichi d'un compte (situation O2S + VL Bourso
     * résolues en EUR), exactement ce que le client voit dans son détail produit.
     *
     * URL: /cron/vl-account?token=CRON_SECRET&productId=72   (par id ProductAccount)
     *  ou: /cron/vl-account?token=CRON_SECRET&compteId=OC...  (par id O2S)
     */
    #[Route('/vl-account', name: 'vl_account', methods: ['GET'])]
    public function vlAccount(
        Request $request,
        \App\Integration\O2S\Service\CompteServiceInterface $compteService,
        \App\Integration\O2S\Service\AssetServiceInterface $assetService,
        \App\Service\MarketData\LiveQuoteService $liveQuoteService,
    ): JsonResponse {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }
        set_time_limit(60);

        $productId = $request->query->getInt('productId', 0);
        $compteId = (string) $request->query->get('compteId', '');

        if ($productId > 0) {
            $product = $this->entityManager->find(ProductAccount::class, $productId);
            if (!$product) {
                return $this->json(['error' => "Product $productId not found"], 404);
            }
            $compteId = (string) $product->getO2sCompteId();
            $displayAlias = $product->getDisplayAlias();
            $o2sValuation = (float) ($product->getO2sValuation() ?? 0);
            $userId = $product->getUser()?->getId();
        } else {
            if ($compteId === '') {
                return $this->json(['error' => 'Missing productId or compteId'], 400);
            }
            $displayAlias = null;
            $o2sValuation = null;
            $userId = null;
        }

        $details = $compteService->getAccountDetails($compteId);
        $situation = $details->getSituation();
        $valuationDate = $details->getValuationDate();

        $lines = [];
        $totalO2S = 0.0;
        $totalRecalc = 0.0;
        foreach ($situation as $line) {
            $assetId = $line->getAssetId();
            $isin = $line->getIsin();
            $name = $line->getAssetName();

            // Enrichissement via AssetService si ISIN manquant
            $assetInfo = null;
            if ($assetId && (!$isin || !$name)) {
                try {
                    $assetInfo = $assetService->getAsset($assetId);
                    $isin = $isin ?: $assetInfo?->getIsin();
                    $name = $name ?: $assetInfo?->getLabel();
                } catch (\Throwable) {}
            }

            $qty = (float) ($line->getQuantity() ?? 0);
            $navO2S = (float) ($line->getNetAssetValue() ?? 0);
            $valO2S = (float) ($line->getValue() ?? 0);
            $totalO2S += $valO2S;

            $row = [
                'isin' => $isin,
                'name' => $name,
                'assetId' => $assetId,
                'qty' => $qty,
                'navO2S' => $navO2S,
                'navDateO2S' => $line->getNetAssetValueDate()?->format('Y-m-d'),
                'valO2S' => $valO2S,
                'currency' => $line->getCurrency() ?? 'EUR',
            ];

            // On essaie d'abord l'ISIN ; si pas d'ISIN mais un code interne O2S
            // qui pourrait être aliasé, on tente quand même.
            $codeForLookup = $isin ?: $assetInfo?->getIsin() ?: null;
            if ($codeForLookup && $qty > 0) {
                try {
                    $r = $liveQuoteService->getLiveNavEur($codeForLookup);
                    if ($r['nav'] !== null) {
                        $valBourso = $qty * $r['nav'];
                        $row['navBourso'] = $r['nav'];
                        $row['navDateBourso'] = $r['navDate'];
                        $row['source'] = $r['source'];
                        $row['isConverted'] = $r['isConverted'];
                        $row['nativeCurrency'] = $r['nativeCurrency'];
                        $row['fxRate'] = $r['fxRate'];
                        if (isset($r['aliasedToIsin'])) {
                            $row['aliasedToIsin'] = $r['aliasedToIsin'];
                            $row['aliasNote'] = $r['aliasNote'] ?? null;
                        }
                        $row['valBourso'] = $valBourso;
                        $row['delta'] = $valBourso - $valO2S;
                        $row['deltaPct'] = $valO2S != 0.0 ? (($valBourso - $valO2S) / $valO2S) * 100.0 : null;
                        $totalRecalc += $valBourso;
                    } else {
                        $row['navBourso'] = null;
                        $row['source'] = 'not_found';
                        $totalRecalc += $valO2S;
                    }
                } catch (\Throwable $e) {
                    $row['navBourso'] = null;
                    $row['source'] = 'error';
                    $row['error'] = $e->getMessage();
                    $totalRecalc += $valO2S;
                }
            } else {
                $row['source'] = 'no_isin';
                $totalRecalc += $valO2S; // fonds euros etc.
            }

            $lines[] = $row;
        }

        return $this->json([
            'productId' => $productId ?: null,
            'compteId' => $compteId,
            'displayAlias' => $displayAlias,
            'userId' => $userId,
            'o2sValuation' => $o2sValuation,
            'o2sValuationDate' => $valuationDate?->format('Y-m-d'),
            'totalO2S_fromSituation' => round($totalO2S, 2),
            'totalRecalculated_with_bourso' => round($totalRecalc, 2),
            'lineCount' => count($lines),
            'lines' => $lines,
        ], 200, [], ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Préchauffe le cache des VL Boursorama pour tous les ISIN actifs.
     * Endpoint HTTP appelable manuellement (ou via cron OVH) en complément du
     * cron CLI `vl_warm_cache.php`.
     *
     * URL: /cron/vl-warm?token=CRON_SECRET[&force=1][&limit=200][&throttleMs=300]
     *  - force=1   : vide d'abord le cache Bourso+ECB (refetch complet)
     *  - limit     : limite le nombre de comptes traités (debug)
     *  - throttleMs : pause entre 2 fetchs Bourso (ms)
     */
    #[Route('/vl-warm', name: 'vl_warm', methods: ['GET'])]
    public function vlWarm(
        Request $request,
        \App\Integration\O2S\Service\CompteServiceInterface $compteService,
        \App\Integration\O2S\Service\AssetServiceInterface $assetService,
        \App\Service\MarketData\LiveQuoteService $liveQuoteService,
        \Symfony\Contracts\Cache\CacheInterface $cache,
    ): JsonResponse {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        set_time_limit(900);

        $force = (bool) $request->query->getInt('force', 0);
        $limit = $request->query->getInt('limit', 0);
        $throttleMs = max(0, $request->query->getInt('throttleMs', 300));
        $start = microtime(true);

        // 1. Vidage cache si force=1 — on cible uniquement nos clés via prune
        //    (cache.app est partagé avec d'autres services O2S, ne pas tout vider)
        if ($force && $cache instanceof \Symfony\Contracts\Cache\TagAwareCacheInterface) {
            // Pas de tags pour l'instant → on délete par clé connue dans la boucle
        }

        // 2. Collecter ISIN actifs via les ProductAccount
        $qb = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->orderBy('p.id', 'ASC');
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        /** @var ProductAccount[] $accounts */
        $accounts = $qb->getQuery()->getResult();

        $isins = [];
        $scanned = 0;
        $failedAccounts = 0;
        foreach ($accounts as $account) {
            $scanned++;
            try {
                $details = $compteService->getAccountDetails($account->getO2sCompteId());
                foreach ($details->getSituation() as $line) {
                    $isin = $line->getIsin();
                    if (!$isin && $line->getAssetId()) {
                        try {
                            $asset = $assetService->getAsset($line->getAssetId());
                            $isin = $asset?->getIsin();
                        } catch (\Throwable) {
                            $isin = null;
                        }
                    }
                    if ($isin && preg_match('/^[A-Z]{2}[A-Z0-9]{9}\d$/', $isin)) {
                        $isins[$isin] = true;
                    }
                }
            } catch (\Throwable $e) {
                $failedAccounts++;
            }
        }
        $isins = array_keys($isins);
        sort($isins);

        // 3. Si force=1, supprime explicitement la clé cache de chaque ISIN avant refetch
        if ($force) {
            foreach ($isins as $isin) {
                $cache->delete('bourso_quote_' . $isin);
            }
            $cache->delete('ecb_fx_rates_daily');
        }

        // 4. Pré-fetch toutes les VL via LiveQuoteService (qui met en cache)
        $stats = ['boursorama' => 0, 'boursorama+fx' => 0, 'fallback' => 0, 'none' => 0];
        $samples = []; // 10 premiers pour audit visuel
        foreach ($isins as $isin) {
            try {
                $r = $liveQuoteService->getLiveNavEur($isin);
                if ($r['nav'] === null) {
                    $stats['none']++;
                } elseif (str_starts_with((string) $r['source'], 'boursorama')) {
                    $stats[$r['isConverted'] ? 'boursorama+fx' : 'boursorama']++;
                } else {
                    $stats['fallback']++;
                }
                if (count($samples) < 10) {
                    $samples[$isin] = [
                        'nav' => $r['nav'],
                        'navDate' => $r['navDate'],
                        'source' => $r['source'],
                        'isConverted' => $r['isConverted'],
                    ];
                }
            } catch (\Throwable) {
                $stats['none']++;
            }
            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }
        }

        $duration = round(microtime(true) - $start, 1);
        $this->logger->info('[CRON] vl-warm completed', [
            'accountsScanned' => $scanned,
            'isinCount' => count($isins),
            'stats' => $stats,
            'force' => $force,
            'duration' => $duration,
        ]);

        return $this->json([
            'ok' => true,
            'duration_seconds' => $duration,
            'force_refresh' => $force,
            'accountsScanned' => $scanned,
            'accountsFailed' => $failedAccounts,
            'isinCount' => count($isins),
            'stats' => $stats,
            'samples' => $samples,
        ], 200, [], ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Diagnostic — vérifie la connectivité Boursorama + BCE et le pipeline
     * MarketQuoteResolver depuis le contexte web (= contexte CRON OVH).
     *
     * URL: /cron/vl-test?token=CRON_SECRET&isin=FR0010149302
     * Multi-ISIN : ?isin=FR0010149302,LU1989766289,IE00B5BMR087
     */
    #[Route('/vl-test', name: 'vl_test', methods: ['GET'])]
    public function vlTest(
        Request $request,
        \App\Service\MarketData\EcbFxRateProvider $ecb,
        \App\Service\MarketData\BoursoramaQuoteProvider $bourso,
        \App\Service\MarketData\MarketQuoteResolver $resolver,
        \App\Service\MarketData\LiveQuoteService $live,
    ): JsonResponse {
        if (!$this->validateCronToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        set_time_limit(60);

        $isinParam = (string) $request->query->get('isin', 'FR0010149302,LU1989766289');
        $isins = array_filter(array_map('trim', explode(',', $isinParam)));

        $started = microtime(true);
        $results = [];

        // 1. Connectivité BCE
        $rateBag = $ecb->getRates();
        $ecbStatus = [
            'available' => $rateBag !== null,
            'date' => $rateBag !== null ? $rateBag['date']->format('Y-m-d') : null,
            'sampleRates' => $rateBag !== null ? [
                'USD' => $rateBag['rates']['USD'] ?? null,
                'GBP' => $rateBag['rates']['GBP'] ?? null,
                'CHF' => $rateBag['rates']['CHF'] ?? null,
                'JPY' => $rateBag['rates']['JPY'] ?? null,
            ] : null,
        ];

        // 2. Connectivité Boursorama (direct provider, sans FX)
        $bourseResults = [];
        foreach ($isins as $isin) {
            try {
                $q = $bourso->getQuote($isin);
                $bourseResults[$isin] = $q ? [
                    'nav' => $q->nav,
                    'currency' => $q->currency,
                    'navDate' => $q->navDate?->format('Y-m-d'),
                ] : null;
            } catch (\Throwable $e) {
                $bourseResults[$isin] = ['error' => $e->getMessage()];
            }
        }

        // 3. Pipeline complet (résolveur + FX)
        $resolverResults = [];
        foreach ($isins as $isin) {
            try {
                $r = $resolver->resolveEur($isin);
                $resolverResults[$isin] = $r ? $r->toArray() : null;
            } catch (\Throwable $e) {
                $resolverResults[$isin] = ['error' => $e->getMessage()];
            }
        }

        // 4. LiveQuoteService (façade utilisée par les controllers)
        $liveResults = [];
        foreach ($isins as $isin) {
            try {
                $liveResults[$isin] = $live->getLiveNavEur($isin);
            } catch (\Throwable $e) {
                $liveResults[$isin] = ['error' => $e->getMessage()];
            }
        }

        $duration = round(microtime(true) - $started, 2);

        $overallOk = $ecbStatus['available']
            && count(array_filter($bourseResults)) > 0
            && count(array_filter($resolverResults)) > 0;

        return $this->json([
            'ok' => $overallOk,
            'duration_seconds' => $duration,
            'environment' => $_SERVER['APP_ENV'] ?? 'unknown',
            'ecb' => $ecbStatus,
            'boursorama_direct' => $bourseResults,
            'resolver_eur' => $resolverResults,
            'live_quote_service' => $liveResults,
        ], 200, [], ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Valide le token CRON_SECRET.
     */
    private function validateCronToken(Request $request): bool
    {
        $token = $request->query->get('token', '');
        $expectedToken = $_ENV['CRON_SECRET'] ?? $_SERVER['CRON_SECRET'] ?? '';

        if (empty($expectedToken)) {
            $this->logger->error('[CRON] CRON_SECRET not configured in environment');
            return false;
        }

        return hash_equals($expectedToken, $token);
    }
}

