<?php

/**
 * CRON OVH - Synchronisation complète quotidienne O2S
 *
 * Resynchronise TOUS les contacts et comptes depuis O2S.
 * Filet de sécurité quotidien pour rattraper tout écart.
 *
 * Path OVH : www/cron/ovh/o2s_full.php
 * Fréquence recommandée : 1× par jour à 4h du matin (0 4 * * *)
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);
cron_log('full', 'INFO', 'START');

try {
    // Sans flag = sync contacts + comptes (cf. O2SSyncCommand)
    [$exitCode, $output] = run_console_command('o2s:sync', []);

    $duration = round(microtime(true) - $start, 1);

    if ($exitCode === 0) {
        cron_log('full', 'OK', "Terminé en {$duration}s");
    } else {
        cron_log('full', 'ERROR', "Exit code $exitCode après {$duration}s");
    }

    echo "\n--- Console output ---\n";
    echo $output;

    exit($exitCode);
} catch (\Throwable $e) {
    cron_log('full', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
