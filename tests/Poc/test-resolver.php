<?php
declare(strict_types=1);

/**
 * POC du MarketQuoteResolver — vérifie le pipeline complet :
 *   Boursorama (cours natif) → FxConverter (BCE) → Quote EUR
 *
 * Sans bootstrap Symfony (HttpClient natif + classes du projet via require).
 *
 * Usage :
 *   php tests/Poc/test-resolver.php
 *   php tests/Poc/test-resolver.php LU1989766289
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Service\MarketData\BoursoramaQuoteProvider;
use App\Service\MarketData\EcbFxRateProvider;
use App\Service\MarketData\FxConverter;
use App\Service\MarketData\MarketQuoteResolver;

// Sans cache pour le POC (chaque exécution refait l'appel)
$ecb = new EcbFxRateProvider();
$fx = new FxConverter($ecb);
$bourso = new BoursoramaQuoteProvider();
$resolver = new MarketQuoteResolver([$bourso], $fx);

$isins = $argv[1] ?? null
    ? [$argv[1]]
    : [
        'FR0010622514', // Ostrum SRI Cash (EUR direct)
        'FR0013345709', // LBPAM ISR Actions Euro L (EUR direct)
        'FR0010149302', // Carmignac Emergents (EUR direct)
        'LU0336083497', // Carmignac Portfolio Global Bond (EUR direct)
        'LU1876459303', // Axiom European Banks (EUR direct)
        'LU1989766289', // CPR Invest Global Gold Mines (USD natif → FX requis)
        'IE00B5BMR087', // iShares Core S&P 500 (USD par défaut, EUR via mapping si configuré)
        'FR001400H5L2', // Solstice Selection (EUR direct)
    ];

echo "=====================================================\n";
echo " POC MarketQuoteResolver — Pipeline complet (Bourso + FX BCE)\n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================\n\n";

// D'abord, charge les taux BCE pour info
$rateBag = $ecb->getRates();
if ($rateBag === null) {
    echo "⚠ Impossible de charger les taux BCE — la conversion FX ne fonctionnera pas\n\n";
} else {
    echo sprintf("Taux BCE chargés (date publication : %s)\n", $rateBag['date']->format('Y-m-d'));
    printf("  1 EUR = %.4f USD | %.4f GBP | %.4f CHF | %.4f JPY\n\n",
        $rateBag['rates']['USD'] ?? 0,
        $rateBag['rates']['GBP'] ?? 0,
        $rateBag['rates']['CHF'] ?? 0,
        $rateBag['rates']['JPY'] ?? 0,
    );
}

printf("  %-13s %12s %4s %12s %12s %s\n", 'ISIN', 'VL native', 'Ccy', 'VL → EUR', 'Date', 'Status');
echo str_repeat('-', 100) . "\n";

foreach ($isins as $isin) {
    $r = $resolver->resolveEur($isin);
    if ($r === null) {
        printf("  %-13s %12s %4s %12s %12s %s\n", $isin, '—', '—', '—', '—', '✗ non trouvé');
        usleep(800_000);
        continue;
    }
    $status = $r->isConverted()
        ? sprintf('⇄ %s→EUR (taux %.6f)', $r->nativeQuote->currency, $r->fxRate ?? 0)
        : '✓ EUR direct';
    printf(
        "  %-13s %12s %4s %12s %12s %s\n",
        $isin,
        number_format($r->nativeQuote->nav, 4, '.', ' '),
        $r->nativeQuote->currency,
        number_format($r->quote->nav, 4, '.', ' '),
        $r->quote->navDate?->format('d/m/Y') ?? '?',
        $status
    );
    usleep(800_000);
}

echo "\nOK — résolveur opérationnel.\n";
