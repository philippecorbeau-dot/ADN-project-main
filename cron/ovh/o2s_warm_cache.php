<?php

/**
 * CRON OVH - Préchauffage du cache O2S
 *
 * Pour chaque utilisateur lié à Harvest, purge puis re-remplit le cache des
 * 3 méthodes API qui sont lentes au runtime du dashboard :
 *  - getCompte (versements)
 *  - getAccountDetails (liquidité)
 *  - getAccountDetailsHistory (courbe 6 mois)
 *  - getContactPatrimoine (patrimoine global du contact)
 *
 * Effet : pendant les 24h qui suivent, l'ouverture du dashboard d'un client
 * répond en quelques dizaines de ms au lieu de 30-120 s. Le cache est valable
 * 24h (cf. expiresAfter dans CompteService / ContactService).
 *
 * Délègue à la commande Symfony `o2s:warm-cache`.
 *
 * Path OVH : www/cron/ovh/o2s_warm_cache.php
 * Fréquence recommandée : 1× par jour à 6h du matin (0 6 * * *)
 *   - Tourne après o2s_full.php (4h) et o2s_fix_emails.php (5h)
 *   - Tourne avant les heures de forte connexion utilisateur
 *   - Durée typique : 5-30 min selon le nombre de contrats à préchauffer
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);
cron_log('warm-cache', 'INFO', 'START');

try {
    [$exitCode, $output] = run_console_command('o2s:warm-cache', []);

    $duration = round(microtime(true) - $start, 1);

    if ($exitCode === 0) {
        cron_log('warm-cache', 'OK', "Terminé en {$duration}s");
    } else {
        cron_log('warm-cache', 'ERROR', "Exit code $exitCode après {$duration}s");
    }

    echo "\n--- Console output ---\n";
    echo $output;

    exit($exitCode);
} catch (\Throwable $e) {
    cron_log('warm-cache', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
