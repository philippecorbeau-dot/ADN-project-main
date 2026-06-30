<?php
declare(strict_types=1);

/**
 * POC E2E — Comparaison O2S Web vs recalcul (quantités O2S × VL Boursorama + FX BCE).
 *
 * Utilise le MarketQuoteResolver de production. Ce POC démontre exactement ce qui
 * sera affiché dans l'UI ADN après intégration.
 *
 * Données entrée : `tests/Poc/accounts-fixtures.php` (captures écran prod ADN).
 *
 * Usage :
 *   php tests/Poc/compare-o2s-vs-bourso.php              # tous les comptes
 *   php tests/Poc/compare-o2s-vs-bourso.php OC830110468  # un seul compte
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Service\MarketData\BoursoramaQuoteProvider;
use App\Service\MarketData\EcbFxRateProvider;
use App\Service\MarketData\FxConverter;
use App\Service\MarketData\MarketQuoteResolver;

$fixtures = require __DIR__ . '/accounts-fixtures.php';

$only = $argv[1] ?? null;
if ($only !== null) {
    if (!isset($fixtures[$only])) {
        fwrite(STDERR, "Compte inconnu : $only\nDisponibles : " . implode(', ', array_keys($fixtures)) . "\n");
        exit(1);
    }
    $fixtures = [$only => $fixtures[$only]];
}

// Pipeline résolveur (sans cache pour le POC : on veut une vue fraîche à chaque run)
$ecb = new EcbFxRateProvider();
$fx = new FxConverter($ecb);
$bourso = new BoursoramaQuoteProvider();
$resolver = new MarketQuoteResolver([$bourso], $fx);

$globalCovered = 0;
$globalIsinCount = 0;
$globalAligned = 0;
$globalFresher = 0;
$summaryRows = [];

foreach ($fixtures as $accountId => $data) {
    echo str_repeat('=', 78) . "\n";
    printf(" Compte %s — %s\n", $accountId, $data['libelle']);
    printf(" Client : %s | Snapshot O2S : %s\n", $data['client'], $data['situationDate']);
    echo str_repeat('=', 78) . "\n\n";

    printf(
        "  %-13s %-30s %8s %12s %12s %12s %12s %5s %10s\n",
        'ISIN', 'Libellé', 'Qty', 'VL O2S', 'Date O2S', 'VL→EUR Bourso', 'Date Bourso', 'Δ j', 'Δ €'
    );
    echo str_repeat('-', 130) . "\n";

    $totalO2S = 0.0;
    $totalRecalc = 0.0;
    $coveredLines = 0;
    $isinLines = 0;
    $aligned = 0;
    $fresher = 0;

    foreach ($data['lignes'] as $row) {
        $isin = $row['isin'];
        $qty = (float) $row['qty'];
        $navO2S = (float) $row['navO2S'];
        $valO2S = (float) $row['valO2S'];
        $dateO2S = \DateTimeImmutable::createFromFormat('!d/m/Y', $row['navDateO2S']);

        $totalO2S += $valO2S;

        if (!$isin) {
            printf("  %-13s %-30s %8s %12s %12s %12s %12s %5s %10s\n",
                '—', substr($row['libelle'], 0, 30), '—', '—', '—', '—', '—', '—', '—');
            $totalRecalc += $valO2S; // fonds euros : pas de recalcul
            continue;
        }

        $isinLines++;
        $resolved = $resolver->resolveEur($isin);

        if ($resolved === null) {
            printf("  %-13s %-30s %8.2f %12.4f %12s %12s %12s %5s %10s\n",
                $isin, substr($row['libelle'], 0, 30), $qty, $navO2S, $row['navDateO2S'],
                '—', '—', '—', 'absent');
            $totalRecalc += $valO2S; // fallback O2S
            usleep(700_000);
            continue;
        }

        $coveredLines++;
        $eurQuote = $resolved->quote;
        $native = $resolved->nativeQuote;
        $valBourso = $qty * $eurQuote->nav;
        $delta = $valBourso - $valO2S;
        $deltaPct = $valO2S != 0.0 ? ($delta / $valO2S) * 100.0 : 0.0;
        $dayDelta = ($eurQuote->navDate && $dateO2S)
            ? (int) $dateO2S->diff($eurQuote->navDate)->format('%r%a')
            : 0;

        if (abs($deltaPct) < 1.0) {
            $aligned++;
        }
        if ($dayDelta > 0) {
            $fresher++;
        }

        $tag = $resolved->isConverted()
            ? sprintf('⇄%s', $native->currency)
            : '';

        printf(
            "  %-13s %-30s %8.2f %12.4f %12s %12.4f %12s %+5d %+9.2f %s\n",
            $isin,
            substr($row['libelle'], 0, 30),
            $qty,
            $navO2S,
            $row['navDateO2S'],
            $eurQuote->nav,
            $eurQuote->navDate?->format('d/m/Y') ?? '—',
            $dayDelta,
            $delta,
            $tag,
        );

        $totalRecalc += $valBourso;
        usleep(700_000);
    }

    $fondsEuros = isset($data['fondsEuros']) ? (float) $data['fondsEuros']['valeur'] : 0.0;
    $totalRecalcWithFunds = $totalRecalc + $fondsEuros;
    $totalO2SWithAll = $data['totalCompte'] ?? ($totalO2S + $fondsEuros);

    echo "\n";
    if ($fondsEuros > 0) {
        printf("  Fonds euros (gardé identique O2S)            : %15s €\n", number_format($fondsEuros, 2, '.', ' '));
    }
    printf("  TOTAL O2S Web (référence affichée à l'extranet) : %15s €\n", number_format($totalO2SWithAll, 2, '.', ' '));
    printf("  TOTAL recalculé (qtés O2S × VL Bourso EUR)      : %15s €  (Δ %+.2f € / %+.3f%%)\n",
        number_format($totalRecalcWithFunds, 2, '.', ' '),
        $totalRecalcWithFunds - $totalO2SWithAll,
        $totalO2SWithAll != 0.0 ? (($totalRecalcWithFunds - $totalO2SWithAll) / $totalO2SWithAll) * 100.0 : 0.0,
    );
    printf("  Couverture Boursorama : %d/%d ISIN (%d%%) | Alignés <1%% : %d | Plus frais qu'O2S : %d\n\n",
        $coveredLines, $isinLines,
        $isinLines > 0 ? (int) round($coveredLines * 100.0 / $isinLines) : 0,
        $aligned, $fresher
    );

    $globalCovered += $coveredLines;
    $globalIsinCount += $isinLines;
    $globalAligned += $aligned;
    $globalFresher += $fresher;

    $summaryRows[] = [
        'id' => $accountId,
        'client' => $data['client'],
        'totalO2S' => $totalO2SWithAll,
        'totalRecalc' => $totalRecalcWithFunds,
        'deltaPct' => $totalO2SWithAll != 0.0
            ? (($totalRecalcWithFunds - $totalO2SWithAll) / $totalO2SWithAll) * 100.0
            : 0.0,
    ];
}

echo str_repeat('=', 78) . "\n";
echo " SYNTHÈSE MULTI-COMPTES (pipeline résolveur production)\n";
echo str_repeat('=', 78) . "\n\n";

printf("  %-13s %-30s %15s %15s %10s\n", 'Compte', 'Client', 'O2S Web', 'Recalculé', 'Δ %');
echo str_repeat('-', 100) . "\n";
foreach ($summaryRows as $r) {
    printf("  %-13s %-30s %15s %15s %+9.2f%%\n",
        $r['id'],
        substr($r['client'], 0, 30),
        number_format($r['totalO2S'], 2, '.', ' '),
        number_format($r['totalRecalc'], 2, '.', ' '),
        $r['deltaPct'],
    );
}

echo "\n";
printf("  Couverture Boursorama globale : %d/%d ISIN (%d%%)\n",
    $globalCovered, $globalIsinCount,
    $globalIsinCount > 0 ? (int) round($globalCovered * 100.0 / $globalIsinCount) : 0);
printf("  ISIN alignés < 1%% avec O2S    : %d/%d\n", $globalAligned, $globalCovered);
printf("  ISIN plus frais que O2S       : %d/%d\n", $globalFresher, $globalCovered);
echo "\n";
