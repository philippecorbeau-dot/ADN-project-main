<?php

/**
 * Script wrapper pour les tâches cron O2S sur OVH mutualisé.
 * 
 * POURQUOI CE SCRIPT ?
 * Sur OVH mutualisé, les processus CLI (SSH, cron CLI) ne peuvent pas
 * joindre les serveurs Harvest/O2S (firewall OVH). En revanche, les requêtes
 * passant par le serveur web (Apache) fonctionnent parfaitement.
 * 
 * Ce script est exécuté par le cron OVH en CLI, mais il fait un appel HTTP
 * vers le propre site web → la requête transite par Apache → pas de blocage.
 * 
 * CONFIGURATION OVH :
 *   Commande : php cron/o2s_cron.php incremental
 *   Ou :       php cron/o2s_cron.php full
 *   Ou :       php cron/o2s_cron.php valuations
 *   Ou :       php cron/o2s_cron.php fix-emails
 *   Ou :       php cron/o2s_cron.php classify-contacts
 *   Ou :       php cron/o2s_cron.php health
 * 
 * VARIABLES D'ENVIRONNEMENT requises dans .env.local :
 *   CRON_SECRET=votre_token_secret_ici
 *   CRON_BASE_URL=https://staging.adnfamilyoffice.fr  (ou prod)
 */

// ─── Configuration ───────────────────────────────────────────────
// Charger les variables d'environnement depuis .env.local
$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env.local';
$envVars = [];

// Parse .env.local pour récupérer CRON_SECRET et CRON_BASE_URL
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value, "\"' ");
            $envVars[trim($key)] = $value;
        }
    }
}

$cronSecret  = $envVars['CRON_SECRET']   ?? getenv('CRON_SECRET')   ?: '';
$baseUrl     = $envVars['CRON_BASE_URL'] ?? getenv('CRON_BASE_URL') ?: '';

if (empty($cronSecret) || empty($baseUrl)) {
    fwrite(STDERR, "[ERREUR] CRON_SECRET et CRON_BASE_URL doivent être définis dans .env.local\n");
    exit(1);
}

// ─── Mapping des actions ─────────────────────────────────────────
$actions = [
    'incremental'          => '/cron/o2s-sync-incremental',
    'full'                 => '/cron/o2s-sync-full',
    'valuations'           => '/cron/o2s-sync-valuations',
    'fix-emails'           => '/cron/o2s-fix-emails',
    'classify-contacts'    => '/cron/o2s-classify-contacts',
    'sync-all-comptes'     => '/cron/o2s-sync-all-comptes',
    'health'               => '/cron/health',
];

// ─── Lecture de l'action demandée ────────────────────────────────
$action = $argv[1] ?? 'incremental';

if (!isset($actions[$action])) {
    fwrite(STDERR, sprintf(
        "[ERREUR] Action inconnue : '%s'. Actions valides : %s\n",
        $action,
        implode(', ', array_keys($actions))
    ));
    exit(1);
}

// ─── Paramètres supplémentaires ──────────────────────────────────
$extraParams = '';
if ($action === 'valuations' && isset($argv[2])) {
    $extraParams = '&batch_size=' . (int) $argv[2];
}
if ($action === 'sync-all-comptes') {
    $batchSize = (int) ($argv[2] ?? 15);
    $extraParams = '&batch_size=' . $batchSize;
    $offset = (int) ($argv[3] ?? 0);
    if ($offset > 0) {
        $extraParams .= '&offset=' . $offset;
    }
}

// ─── Appel HTTP vers l'endpoint cron ─────────────────────────────
$url = rtrim($baseUrl, '/') . $actions[$action] . '?token=' . urlencode($cronSecret) . $extraParams;

$timestamp = date('Y-m-d H:i:s');
echo "[$timestamp] Cron O2S : action=$action\n";
echo "[$timestamp] URL : " . preg_replace('/token=[^&]+/', 'token=***', $url) . "\n";

$context = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 300, // 5 minutes max
        'header'  => "User-Agent: ADN-Cron/1.0\r\n",
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    $error = error_get_last();
    fwrite(STDERR, "[$timestamp] ERREUR : Impossible de joindre l'endpoint cron\n");
    fwrite(STDERR, "[$timestamp] Détail : " . ($error['message'] ?? 'Inconnu') . "\n");
    exit(1);
}

// ─── Traitement de la réponse ────────────────────────────────────
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "[$timestamp] Réponse brute : " . substr($response, 0, 500) . "\n";
    fwrite(STDERR, "[$timestamp] ERREUR : Réponse non-JSON reçue\n");
    exit(1);
}

// Afficher le résultat
echo "[$timestamp] Résultat :\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if (!($data['success'] ?? false)) {
    fwrite(STDERR, "[$timestamp] ERREUR : " . ($data['error'] ?? 'Erreur inconnue') . "\n");
    exit(1);
}

echo "[$timestamp] OK - Terminé en " . ($data['duration_seconds'] ?? '?') . "s\n";

// Auto-loop pour sync-all-comptes : relance automatiquement tant qu'il reste des utilisateurs
if ($action === 'sync-all-comptes' && ($data['hasMore'] ?? false) && ($data['next_offset'] ?? null)) {
    $nextOffset = (int) $data['next_offset'];
    $total = $data['total'] ?? '?';
    $processed = $data['processed'] ?? $nextOffset;
    echo "[$timestamp] Progression : $processed / $total — lot suivant (offset=$nextOffset)...\n";
    sleep(2);

    $nextUrl = rtrim($baseUrl, '/') . $actions[$action]
        . '?token=' . urlencode($cronSecret)
        . '&batch_size=' . $batchSize
        . '&offset=' . $nextOffset;

    $response = @file_get_contents($nextUrl, false, $context);
    while ($response !== false) {
        $data = json_decode($response, true);
        if (!$data || !($data['success'] ?? false)) {
            fwrite(STDERR, "[$timestamp] ERREUR batch offset=$nextOffset : " . ($data['error'] ?? 'Erreur inconnue') . "\n");
            break;
        }
        $created = $data['comptes']['created'] ?? 0;
        $updated = $data['comptes']['updated'] ?? 0;
        $processed = $data['processed'] ?? '?';
        $total = $data['total'] ?? '?';
        $dur = $data['duration_seconds'] ?? '?';
        echo "[" . date('Y-m-d H:i:s') . "] Batch offset=$nextOffset : +$created créé(s), $updated maj — $processed/$total ({$dur}s)\n";

        if (!($data['hasMore'] ?? false)) {
            echo "[" . date('Y-m-d H:i:s') . "] Tous les utilisateurs ont été traités.\n";
            break;
        }

        $nextOffset = (int) $data['next_offset'];
        sleep(2);
        $nextUrl = rtrim($baseUrl, '/') . $actions[$action]
            . '?token=' . urlencode($cronSecret)
            . '&batch_size=' . $batchSize
            . '&offset=' . $nextOffset;
        $response = @file_get_contents($nextUrl, false, $context);
    }
}

exit(0);

