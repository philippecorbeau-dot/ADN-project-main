<?php

/**
 * Script de test direct Twelve Data: résout ISIN/symbole puis récupère la dernière VL/date.
 * Usage:
 *   php bin/test_market_nav.php [IDENTIFIERS...]
 * Identifiers peuvent être des ISIN (FR...) ou des symboles (ex: MC.PA).
 *
 * Nécessite la variable d'environnement TWELVEDATA_API_KEY.
 */

declare(strict_types=1);

function eprintln(string $msg): void { fwrite(STDERR, $msg . PHP_EOL); }

function load_api_key(): ?string
{
    $key = getenv('TWELVEDATA_API_KEY') ?: null;
    if ($key) return $key;
    // Fallback: tenter de lire .env.local puis .env du projet
    $candidates = [
        dirname(__DIR__) . '/.env.local',
        dirname(__DIR__) . '/.env',
    ];
    foreach ($candidates as $file) {
        if (!is_file($file)) continue;
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            // Ignorer commentaires
            if (preg_match('/^\s*#/', $line)) continue;
            if (preg_match('/^\s*(?:export\s+)?TWELVEDATA_API_KEY\s*=\s*(.+)\s*$/', $line, $m)) {
                $val = trim($m[1]);
                // Retirer guillemets éventuels
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if ($val !== '') return $val;
            }
        }
    }
    return null;
}

$apiKey = load_api_key();
if (!$apiKey) {
    eprintln('Erreur: clé Twelve Data introuvable (TWELVEDATA_API_KEY).');
    eprintln('Définissez la variable d\'environnement ou ajoutez-la dans .env(.local).');
    exit(2);
}

$inputs = array_values(array_filter(array_slice($argv, 1)));
if (empty($inputs)) {
    // ISIN fréquemment testés (fonds de la liste)
    $inputs = [
        'FR0000987950','FR0007371703','FR0010077461','FR0010097642','LU1876459212','FR001400YMD8'
    ];
}

function http_get_json(string $url, array $query): array
{
    $q = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    $ch = curl_init($q);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status !== 200) {
        return ['ok' => false, 'status' => $status, 'error' => $err ?: 'HTTP '.$status];
    }
    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'error' => 'JSON invalide'];
    }
    return ['ok' => true, 'status' => $status, 'json' => $json];
}

function is_isin(string $s): bool
{
    return (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper(trim($s)));
}

function pick_best_symbol(array $data): ?array
{
    // Normaliser
    $items = [];
    foreach ($data as $row) {
        if (!isset($row['symbol'])) continue;
        $items[] = [
            'symbol' => (string) $row['symbol'],
            'name' => (string) ($row['instrument_name'] ?? $row['name'] ?? ''),
            'exchange' => (string) ($row['exchange'] ?? ''),
            'type' => (string) ($row['instrument_type'] ?? ''),
            'currency' => (string) ($row['currency'] ?? ''),
            'isin' => isset($row['isin']) ? (string) $row['isin'] : null,
        ];
    }
    if (empty($items)) return null;
    usort($items, function($a, $b){
        $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
        $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
        return $aw <=> $bw;
    });
    return $items[0];
}

function test_identifier(string $id, string $apiKey): array
{
    $idTrim = trim($id);
    $resolved = null;
    $search = null;

    if (is_isin($idTrim)) {
        $r = http_get_json('https://api.twelvedata.com/symbol_search', [
            'apikey' => $apiKey,
            'symbol' => $idTrim,
            'outputsize' => 50,
        ]);
        $search = $r;
        if (($r['ok'] ?? false) && !empty($r['json']['data'])) {
            $resolved = pick_best_symbol($r['json']['data']);
        }
    } else {
        // Essayer en tant que symbole
        $r = http_get_json('https://api.twelvedata.com/symbol_search', [
            'apikey' => $apiKey,
            'symbol' => $idTrim,
            'outputsize' => 10,
        ]);
        $search = $r;
        if (($r['ok'] ?? false) && !empty($r['json']['data'])) {
            $resolved = pick_best_symbol($r['json']['data']);
        } else {
            // fallback minimal
            $resolved = ['symbol' => $idTrim, 'exchange' => '', 'name' => $idTrim, 'type' => '', 'currency' => ''];
        }
    }

    $symbol = $resolved['symbol'] ?? null;
    $exchange = $resolved['exchange'] ?? null;
    $name = $resolved['name'] ?? null;
    $type = $resolved['type'] ?? null;

    $series = null;
    $quote = null;
    if ($symbol) {
        $query = ['symbol' => $symbol, 'interval' => '1day', 'outputsize' => '5', 'apikey' => $apiKey];
        if (!empty($exchange)) { $query['exchange'] = $exchange; }
        $ts = http_get_json('https://api.twelvedata.com/time_series', $query);
        if (($ts['ok'] ?? false) && !empty($ts['json']['values'])) {
            $series = $ts['json']['values'][0];
        }
        $q = http_get_json('https://api.twelvedata.com/quote', ['symbol' => $symbol, 'apikey' => $apiKey] + (!empty($exchange) ? ['exchange' => $exchange] : []));
        if (($q['ok'] ?? false) && isset($q['json']['symbol'])) {
            $quote = $q['json'];
        }
    }

    return [
        'input' => $idTrim,
        'resolved' => $resolved,
        'series_first' => $series,
        'quote' => $quote,
        'search_status' => $search['status'] ?? null,
        'search_error' => $search['error'] ?? null,
    ];
}

$results = [];
foreach ($inputs as $id) {
    $results[] = test_identifier($id, $apiKey);
}

// Affichage simple en tableau
echo str_pad('Input', 18) . str_pad('Symbol', 18) . str_pad('Exchange', 10) . str_pad('Type', 12) . str_pad('Date', 14) . str_pad('Close', 12) . "Change%\n";
echo str_repeat('-', 18+18+10+12+14+12+8) . "\n";
foreach ($results as $r) {
    $res = $r['resolved'] ?? [];
    $sf = $r['series_first'] ?? [];
    $q = $r['quote'] ?? [];
    $date = $sf['datetime'] ?? null;
    $close = isset($sf['close']) ? (string) $sf['close'] : null;
    $pct = isset($q['percent_change']) ? (string) $q['percent_change'] : '';
    echo str_pad((string)$r['input'], 18);
    echo str_pad((string)($res['symbol'] ?? ''), 18);
    echo str_pad((string)($res['exchange'] ?? ''), 10);
    echo str_pad((string)($res['type'] ?? ''), 12);
    echo str_pad($date ? substr($date, 0, 10) : '', 14);
    echo str_pad($close ?? '', 12);
    echo $pct . "\n";
}

// Détail JSON brut en cas de besoin (décommentez pour debug)
// echo json_encode($results, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . PHP_EOL;


