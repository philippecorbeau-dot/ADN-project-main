<?php

/**
 * CRON OVH - Correction des emails placeholder O2S
 *
 * Récupère les vraies adresses email depuis Harvest pour les utilisateurs
 * qui ont encore un email "@placeholder.local" (créés sans email lors d'un
 * import précédent). Pour les couples/familles partageant la même adresse,
 * génère automatiquement une variante "prenom+xxx@domaine".
 *
 * Indispensable pour que la redirection vers MoneyPitch fonctionne :
 * MoneyPitch authentifie les clients par leur vraie adresse email.
 *
 * Délègue à la commande Symfony `o2s:sync --fix-emails`.
 *
 * Path OVH : www/cron/ovh/o2s_fix_emails.php
 * Fréquence recommandée : 1× par jour à 5h du matin (0 5 * * *)
 *   - Tourne après o2s_full.php (4h) qui peut créer de nouveaux placeholders
 *   - Tourne avant les heures ouvrées pour que MoneyPitch fonctionne en journée
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);
cron_log('fix-emails', 'INFO', 'START');

try {
    [$exitCode, $output] = run_console_command('o2s:sync', [
        '--fix-emails' => true,
    ]);

    $duration = round(microtime(true) - $start, 1);

    if ($exitCode === 0) {
        cron_log('fix-emails', 'OK', "Terminé en {$duration}s");
    } else {
        cron_log('fix-emails', 'ERROR', "Exit code $exitCode après {$duration}s");
    }

    echo "\n--- Console output ---\n";
    echo $output;

    exit($exitCode);
} catch (\Throwable $e) {
    cron_log('fix-emails', 'FATAL', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    exit(1);
}
