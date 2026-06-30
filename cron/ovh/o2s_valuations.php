<?php

/**
 * CRON OVH - Mise à jour des valorisations O2S
 *
 * Met à jour les valorisations des comptes par lot de 50.
 * Priorise les comptes à 0€ qui n'ont jamais été valorisés.
 *
 * Path OVH : www/cron/ovh/o2s_valuations.php
 * Fréquence recommandée : toutes les heures (5 * * * *)
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);
cron_log('valuations', 'INFO', 'START');

try {
    [$exitCode, $output] = run_console_command('o2s:sync-incremental', [
        '--valuations' => true,
        '--batch-size' => 50,
    ]);

    $duration = round(microtime(true) - $start, 1);

    if ($exitCode === 0) {
        cron_log('valuations', 'OK', "Terminé en {$duration}s");
    } else {
        cron_log('valuations', 'ERROR', "Exit code $exitCode après {$duration}s");
    }

    echo "\n--- Console output ---\n";
    echo $output;

    exit($exitCode);
} catch (\Throwable $e) {
    cron_log('valuations', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
