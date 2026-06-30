<?php

/**
 * CRON OVH - Synchronisation incrémentale O2S
 *
 * Détecte les nouveaux contacts depuis O2S et synchronise les comptes manquants.
 * Délègue à la commande Symfony `o2s:sync-incremental`.
 *
 * Path OVH : www/cron/ovh/o2s_incremental.php
 * Fréquence recommandée : toutes les heures (0 * * * *)
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);
cron_log('incremental', 'INFO', 'START');

try {
    [$exitCode, $output] = run_console_command('o2s:sync-incremental', [
        '--batch-size' => 50,
    ]);

    $duration = round(microtime(true) - $start, 1);

    if ($exitCode === 0) {
        cron_log('incremental', 'OK', "Terminé en {$duration}s");
    } else {
        cron_log('incremental', 'ERROR', "Exit code $exitCode après {$duration}s");
    }

    // Garder la sortie console pour les logs OVH
    echo "\n--- Console output ---\n";
    echo $output;

    exit($exitCode);
} catch (\Throwable $e) {
    cron_log('incremental', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
