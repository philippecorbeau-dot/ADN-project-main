<?php

/**
 * CRON OVH - Préchauffage du cache des VL Boursorama
 *
 * Pour tous les ISIN détenus en portefeuille client (via O2S), pré-fetche la VL
 * Boursorama et la met en cache 6h. Effet : à la prochaine visite du détail
 * produit dans le dashboard, la VL est servie en ~5 ms (cache hit) au lieu de
 * ~800 ms (HTTP fetch + parse).
 *
 * Délègue à la commande Symfony `app:vl-warm-cache`.
 *
 * Path OVH : www/cron/ovh/vl_warm_cache.php
 *
 * Fréquence recommandée : 1× par jour à 14h (0 14 * * *)
 *  - Boursorama publie ses VL J+1 ouvré entre 11h et 13h CET
 *  - 14h garantit qu'on a les dernières publications
 *  - Tourne APRÈS o2s_warm_cache.php (6h) pour avoir le cache O2S prêt
 *    (la commande s'en sert via getAccountDetails)
 *
 * Durée typique : 5-15 min selon le nombre d'ISIN distincts (~200-500 supports actifs).
 *
 * Doc complète : docs/market-data-boursorama.md
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);
cron_log('vl-warm-cache', 'INFO', 'START');

try {
    // throttle-ms à 300 pour rester poli côté Boursorama tout en restant rapide
    [$exitCode, $output] = run_console_command('app:vl-warm-cache', [
        '--throttle-ms' => '300',
    ]);

    $duration = round(microtime(true) - $start, 1);

    if ($exitCode === 0) {
        cron_log('vl-warm-cache', 'OK', "Terminé en {$duration}s");
    } else {
        cron_log('vl-warm-cache', 'ERROR', "Exit code $exitCode après {$duration}s");
    }

    echo "\n--- Console output ---\n";
    echo $output;

    exit($exitCode);
} catch (\Throwable $e) {
    cron_log('vl-warm-cache', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
