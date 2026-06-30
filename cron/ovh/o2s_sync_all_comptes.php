<?php

/**
 * CRON OVH - Détection nouveaux contrats O2S (sync-all-comptes paginé)
 *
 * Parcourt tous les utilisateurs O2S par lots et resynchronise leurs comptes
 * pour détecter automatiquement les nouveaux contrats ajoutés dans O2S.
 *
 * Pagination interne pour rester sous la limite OVH de 60 min.
 *
 * Path OVH : www/cron/ovh/o2s_sync_all_comptes.php
 * Fréquence recommandée : toutes les 2 heures (30 *\/2 * * *)
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Integration\O2S\Sync\O2SSyncService;
use Doctrine\ORM\EntityManagerInterface;

$start = microtime(true);
$BATCH_SIZE = 15;
$MAX_DURATION = 3000; // 50 min max (laisse marge sur les 60 min OVH)

cron_log('sync-all-comptes', 'INFO', 'START', ['batch_size' => $BATCH_SIZE]);

try {
    $kernel = new App\Kernel('prod', false);
    $kernel->boot();
    $container = $kernel->getContainer();

    /** @var O2SSyncService $syncService */
    $syncService = $container->get(O2SSyncService::class);
    /** @var EntityManagerInterface $em */
    $em = $container->get('doctrine.orm.entity_manager');

    $offset = 0;
    $totalCreated = 0;
    $totalUpdated = 0;
    $totalErrors = 0;
    $batchCount = 0;
    $hasMore = true;

    while ($hasMore) {
        $elapsed = microtime(true) - $start;
        if ($elapsed > $MAX_DURATION) {
            cron_log('sync-all-comptes', 'WARN', "Timeout préventif à {$elapsed}s, offset $offset", [
                'reason' => 'max_duration_reached',
                'next_offset_to_resume' => $offset,
            ]);
            break;
        }

        $batch = $syncService->syncComptesBatch($offset, $BATCH_SIZE);
        $result = $batch['result'];

        $created = $result->getCreated();
        $updated = $result->getUpdated();
        $errors = count($result->getErrors());
        $totalCreated += $created;
        $totalUpdated += $updated;
        $totalErrors += $errors;
        $batchCount++;

        cron_log('sync-all-comptes', 'INFO', "Batch #{$batchCount} offset=$offset", [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'processed' => $batch['processed'],
            'total' => $batch['total'],
        ]);

        $em->clear(); // libère la mémoire entre les lots

        $hasMore = (bool) ($batch['hasMore'] ?? false);
        if ($hasMore) {
            $offset = (int) ($batch['processed'] ?? ($offset + $BATCH_SIZE));
        }
    }

    $duration = round(microtime(true) - $start, 1);
    cron_log('sync-all-comptes', 'OK', "Terminé en {$duration}s", [
        'batches' => $batchCount,
        'total_created' => $totalCreated,
        'total_updated' => $totalUpdated,
        'total_errors' => $totalErrors,
    ]);

    exit($totalErrors > 0 ? 1 : 0);
} catch (\Throwable $e) {
    cron_log('sync-all-comptes', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
