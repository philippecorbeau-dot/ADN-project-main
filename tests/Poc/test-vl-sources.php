<?php
declare(strict_types=1);

/**
 * POC v2 — Mesure la fiabilité de sources publiques de VL face à Quantalys/O2S.
 *
 * Évolutions par rapport à v1 :
 *  - Boursorama : abandon de la recherche (HTTP 500) au profit de l'URL directe /cours/{ISIN}/ (HTTP 200).
 *  - Extraction du prix via les patterns réels observés (c-faceplate__price + data-ist-last).
 *  - Détection de la devise affichée (EUR / USD) pour signaler les mismatches.
 *  - Ajout d'une troisième source : DOM lecture de Yahoo Finance via /quote/{symbol} (HTML pas API)
 *    pour contourner le 429 sur l'endpoint de recherche JSON.
 *  - JustETF abandonné (prix injecté en JS, non scrapable sans navigateur headless).
 *
 * Exécution : php tests/Poc/test-vl-sources.php
 */

$panel = [
    // === Panel des supports CNP Alysés Vie observés dans O2S Web (capture 26/05/2026) ===
    ['isin' => 'LU0273159177', 'name' => 'DWS Invest Gold and Precious Metals',  'expectedNav' => 291.22,   'expectedCcy' => 'EUR', 'expectedDate' => '22/05/2026', 'kind' => 'OPCVM'],
    ['isin' => 'LU1834983477', 'name' => 'Lyxor Index Fund (Multi Units)',       'expectedNav' => 65.0038,  'expectedCcy' => 'EUR', 'expectedDate' => '25/05/2026', 'kind' => 'OPCVM'],
    ['isin' => 'IE00B5BMR087', 'name' => 'iShares Core S&P 500 UCITS ETF',       'expectedNav' => 690.2122, 'expectedCcy' => 'EUR', 'expectedDate' => '22/05/2026', 'kind' => 'ETF'],
    ['isin' => 'FR0010149302', 'name' => 'Carmignac Emergents',                  'expectedNav' => 1865.46,  'expectedCcy' => 'EUR', 'expectedDate' => '22/05/2026', 'kind' => 'OPCVM'],
    ['isin' => 'IE00B53L3W79', 'name' => 'iShares Core EURO STOXX 50 UCITS ETF', 'expectedNav' => 233.6954, 'expectedCcy' => 'EUR', 'expectedDate' => '22/05/2026', 'kind' => 'ETF'],

    // === Panel élargi (sans VL attendue, juste couverture) ===
    ['isin' => 'FR0010135103', 'name' => 'Comgest Magellan C',          'expectedNav' => null, 'expectedCcy' => 'EUR', 'expectedDate' => null, 'kind' => 'OPCVM'],
    ['isin' => 'FR0010321802', 'name' => 'Carmignac Patrimoine A EUR',  'expectedNav' => null, 'expectedCcy' => 'EUR', 'expectedDate' => null, 'kind' => 'OPCVM'],
    ['isin' => 'LU0823421689', 'name' => 'Pictet-Robotics P EUR',       'expectedNav' => null, 'expectedCcy' => 'EUR', 'expectedDate' => null, 'kind' => 'OPCVM'],
    ['isin' => 'IE00BK5BQT80', 'name' => 'Vanguard FTSE All-World UCITS ETF', 'expectedNav' => null, 'expectedCcy' => null, 'expectedDate' => null, 'kind' => 'ETF'],

    // === Cas limite : fonds euros (pas d'ISIN public) — doit échouer partout ===
    ['isin' => '__FONDS_EURO__', 'name' => 'CNP Alysés Euro (fonds euros)', 'expectedNav' => null, 'expectedCcy' => null, 'expectedDate' => null, 'kind' => 'FONDS_EURO'],
];

// ----------------------------------------------------------------------------
// Outils HTTP
// ----------------------------------------------------------------------------

function ua(): string
{
    return 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
}

function httpGet(string $url, int $timeout = 12, array $extraHeaders = []): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => ua(),
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Upgrade-Insecure-Requests: 1',
        ], $extraHeaders),
    ]);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [
        'body' => is_string($body) ? $body : '',
        'status' => (int) ($info['http_code'] ?? 0),
        'finalUrl' => (string) ($info['url'] ?? $url),
    ];
}

function parseFr(string $s): ?float
{
    $s = trim($s);
    $s = str_replace(["\xc2\xa0", ' ', "\u{202f}"], '', $s);
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',') && !str_contains($s, '.')) {
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',') && str_contains($s, '.')) {
        // US format avec , comme séparateur de milliers
        $s = str_replace(',', '', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

// ----------------------------------------------------------------------------
// Source 1 : Boursorama (URL directe par ISIN)
// ----------------------------------------------------------------------------

function fetchBoursorama(string $isin): array
{
    $start = microtime(true);
    $source = 'Boursorama';

    if (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}\d$/', $isin)) {
        return ['source' => $source, 'ok' => false, 'reason' => 'ISIN invalide', 'elapsedMs' => 0];
    }

    $r = httpGet('https://www.boursorama.com/cours/' . urlencode($isin) . '/');
    $elapsed = (int) round((microtime(true) - $start) * 1000);

    if ($r['status'] !== 200 || $r['body'] === '') {
        return ['source' => $source, 'ok' => false, 'reason' => 'HTTP ' . $r['status'], 'elapsedMs' => $elapsed];
    }

    // Garde-fou : Boursorama redirige vers /recherche/ ou home quand l'ISIN est inconnu.
    // Dans ce cas la page contient des prix d'autres instruments (futures, indices…)
    // → on rejette plutôt que de produire un faux positif (cf. POC initial).
    if (!str_contains($r['finalUrl'], '/cours/')) {
        return ['source' => $source, 'ok' => false, 'reason' => 'ISIN inconnu (redirection)', 'elapsedMs' => $elapsed];
    }

    $html = $r['body'];
    $nav = null;
    $ccy = null;
    $date = null;

    // Pattern strict (faceplate + devise sur la même séquence)
    if (preg_match(
        '#class="c-faceplate__price[^"]*".*?data-ist-last[^>]*>([0-9\s\xc2\xa0,\.]+)</span>.*?c-faceplate__price-currency[^>]*>\s*([A-Z]{3})#s',
        $html,
        $m
    )) {
        $nav = parseFr($m[1]);
        $ccy = $m[2];
    }
    // Pattern souple : on exige toujours le bloc faceplate, pas de fallback dangereux
    if ($nav === null && preg_match('#class="c-faceplate__price[^"]*".*?data-ist-last[^>]*>([0-9\s\xc2\xa0,\.]+)</span>#s', $html, $m)) {
        $nav = parseFr($m[1]);
        if (preg_match('#c-faceplate__price-currency[^>]*>\s*([A-Z]{3})#', $html, $m2)) {
            $ccy = $m2[1];
        }
    }
    if (preg_match('#(\d{2}/\d{2}/\d{4})#', $html, $dm)) {
        $date = $dm[1];
    }

    return [
        'source' => $source,
        'ok' => $nav !== null,
        'nav' => $nav,
        'ccy' => $ccy,
        'navDate' => $date,
        'url' => $r['finalUrl'],
        'elapsedMs' => $elapsed,
        'reason' => $nav === null ? 'Bloc faceplate introuvable' : null,
    ];
}

// ----------------------------------------------------------------------------
// Source 2 : Yahoo Finance via /quote/{symbol} (HTML)
// On résout d'abord l'ISIN -> symbole via la page de recherche HTML (pas l'API JSON qui 429).
// ----------------------------------------------------------------------------

function fetchYahooHtml(string $isin): array
{
    $start = microtime(true);
    $source = 'YahooHTML';

    if (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}\d$/', $isin)) {
        return ['source' => $source, 'ok' => false, 'reason' => 'ISIN invalide', 'elapsedMs' => 0];
    }

    // Étape 1 : recherche HTML
    $s = httpGet('https://finance.yahoo.com/lookup?s=' . urlencode($isin));
    if ($s['status'] !== 200) {
        return ['source' => $source, 'ok' => false, 'reason' => 'Search HTTP ' . $s['status'], 'elapsedMs' => (int) round((microtime(true) - $start) * 1000)];
    }

    $symbol = null;
    if (preg_match('#/quote/([A-Z0-9\.\-]+)#', $s['body'], $m)) {
        $symbol = $m[1];
    }
    if (!$symbol) {
        return ['source' => $source, 'ok' => false, 'reason' => 'Symbole non résolu', 'elapsedMs' => (int) round((microtime(true) - $start) * 1000)];
    }

    // Étape 2 : page de quote
    $q = httpGet('https://finance.yahoo.com/quote/' . urlencode($symbol));
    $elapsed = (int) round((microtime(true) - $start) * 1000);
    if ($q['status'] !== 200) {
        return ['source' => $source, 'ok' => false, 'reason' => 'Quote HTTP ' . $q['status'], 'symbol' => $symbol, 'elapsedMs' => $elapsed];
    }

    $nav = null;
    $ccy = null;
    // Pattern Yahoo : data-symbol="..." data-field="regularMarketPrice" value="..."
    if (preg_match('#data-symbol="' . preg_quote($symbol, '#') . '"[^>]*data-field="regularMarketPrice"[^>]*value="([0-9.,]+)"#', $q['body'], $m)) {
        $nav = parseFr($m[1]);
    } elseif (preg_match('#fin-streamer[^>]*data-symbol="' . preg_quote($symbol, '#') . '"[^>]*data-field="regularMarketPrice"[^>]*>([0-9,\.]+)</fin-streamer>#', $q['body'], $m)) {
        $nav = parseFr($m[1]);
    }
    if (preg_match('#"currency"\s*:\s*"([A-Z]{3})"#', $q['body'], $m)) {
        $ccy = $m[1];
    }

    return [
        'source' => $source,
        'ok' => $nav !== null,
        'nav' => $nav,
        'ccy' => $ccy,
        'symbol' => $symbol,
        'url' => $q['finalUrl'],
        'elapsedMs' => $elapsed,
        'reason' => $nav === null ? 'Pattern prix introuvable' : null,
    ];
}

// ----------------------------------------------------------------------------
// Exécution
// ----------------------------------------------------------------------------

echo "=====================================================\n";
echo " POC v2 — Sources VL alternatives à Quantalys / O2S \n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================\n\n";

$results = [];

foreach ($panel as $i => $entry) {
    printf("[%d/%d] %s — %s (%s)\n", $i + 1, count($panel), $entry['isin'], $entry['name'], $entry['kind']);
    if ($entry['expectedNav']) {
        printf("    O2S Web (réf) : %s %s @ %s\n", number_format($entry['expectedNav'], 4, '.', ' '), $entry['expectedCcy'], $entry['expectedDate']);
    }

    $sources = [
        fetchBoursorama($entry['isin']),
        fetchYahooHtml($entry['isin']),
    ];

    foreach ($sources as $s) {
        if ($s['ok']) {
            $msg = sprintf('✅ %s %s @ %s', number_format((float) $s['nav'], 4, '.', ' '), $s['ccy'] ?? '???', $s['navDate'] ?? '?');
            $delta = '';
            if ($entry['expectedNav']) {
                $d = $s['nav'] - $entry['expectedNav'];
                $pct = ($d / $entry['expectedNav']) * 100;
                $delta = sprintf(' [Δ %+0.4f / %+0.2f%%]', $d, $pct);
                if ($entry['expectedCcy'] && $s['ccy'] && $s['ccy'] !== $entry['expectedCcy']) {
                    $delta .= sprintf(' ⚠ devise %s≠%s', $s['ccy'], $entry['expectedCcy']);
                }
            }
            printf("    %-12s %s (%dms)%s\n", $s['source'], $msg, $s['elapsedMs'], $delta);
        } else {
            printf("    %-12s ❌ %s (%dms)\n", $s['source'], $s['reason'] ?? 'inconnu', $s['elapsedMs']);
        }
    }

    $results[] = ['entry' => $entry, 'sources' => $sources];
    echo "\n";

    usleep(800_000); // throttle 0.8s
}

// ----------------------------------------------------------------------------
// Synthèse
// ----------------------------------------------------------------------------

echo "==================================================================\n";
echo " SYNTHÈSE PAR SOURCE\n";
echo "==================================================================\n";

$srcNames = ['Boursorama', 'YahooHTML'];
$nbWithRef = 0;
foreach ($panel as $e) {
    if ($e['expectedNav']) {
        $nbWithRef++;
    }
}

foreach ($srcNames as $src) {
    $found = 0;
    $coherent1pct = 0;
    $coherent5pct = 0;
    $ccyMismatch = 0;
    $totalMs = 0;
    $cnt = 0;
    foreach ($results as $r) {
        foreach ($r['sources'] as $s) {
            if ($s['source'] !== $src) {
                continue;
            }
            $cnt++;
            $totalMs += $s['elapsedMs'] ?? 0;
            if ($s['ok']) {
                $found++;
            }
            if ($s['ok'] && $r['entry']['expectedNav']) {
                $delta = abs(($s['nav'] - $r['entry']['expectedNav']) / $r['entry']['expectedNav']);
                if ($delta < 0.01) {
                    $coherent1pct++;
                }
                if ($delta < 0.05) {
                    $coherent5pct++;
                }
                if ($r['entry']['expectedCcy'] && isset($s['ccy']) && $s['ccy'] !== $r['entry']['expectedCcy']) {
                    $ccyMismatch++;
                }
            }
        }
    }
    printf(
        "%-12s Couverture %2d/%-2d (%3d%%) | Cohérent <1%% %d/%d | <5%% %d/%d | Mismatch devise %d | Lat moy %d ms\n",
        $src,
        $found,
        $cnt,
        $cnt ? (int) round(100 * $found / $cnt) : 0,
        $coherent1pct,
        $nbWithRef,
        $coherent5pct,
        $nbWithRef,
        $ccyMismatch,
        $cnt ? (int) round($totalMs / $cnt) : 0
    );
}

$out = __DIR__ . '/test-vl-sources-results.json';
file_put_contents($out, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\nDétails complets : $out\n";
