<?php

declare(strict_types=1);

namespace App\Command;

use App\Integration\O2S\Service\CompteService;
use App\Service\MarketData\MarketQuoteResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Compare la valorisation détaillée O2S (situation figée + VL O2S/Quantalys)
 * à un recalcul "quantités O2S × VL Boursorama du jour".
 *
 * Permet de mesurer concrètement la pertinence de Boursorama comme source
 * gratuite alternative à l'API Data Quantalys.
 *
 * Exemples :
 *   php bin/console app:vl-compare COC000056
 *   php bin/console app:vl-compare COC000056 --json   # sortie JSON brute
 *   php bin/console app:vl-compare COC000056 --no-cache
 */
#[AsCommand(
    name: 'app:vl-compare',
    description: 'Compare la valorisation O2S vs (qtés O2S × VL Boursorama).',
)]
final class VlCompareCommand extends Command
{
    public function __construct(
        private readonly CompteService $compteService,
        private readonly MarketQuoteResolver $resolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('compteId', InputArgument::REQUIRED, 'O2S account ID (ex: COC000056)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Sortie JSON brute (intégrable dans un pipeline)')
            ->addOption('throttle-ms', null, InputOption::VALUE_REQUIRED, 'Pause entre requêtes Boursorama en ms', 700);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $compteId = (string) $input->getArgument('compteId');
        $jsonOut = (bool) $input->getOption('json');
        $throttleMs = (int) $input->getOption('throttle-ms');

        if (!$jsonOut) {
            $io->title(sprintf('Comparaison VL O2S vs Boursorama — compte %s', $compteId));
        }

        // 1) Charger les détails O2S
        try {
            $details = $this->compteService->getAccountDetails($compteId);
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec récupération détails O2S : %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $situation = $details->getSituation();
        if (count($situation) === 0) {
            $io->warning('Aucune ligne d\'actif dans la situation O2S pour ce compte.');
            return Command::SUCCESS;
        }

        $valuationDate = $details->getValuationDate()?->format('d/m/Y') ?? '?';
        $totalO2S = $details->getTotalValue() ?? 0.0;
        $liquidityO2S = $details->getLiquidity() ?? 0.0;

        if (!$jsonOut) {
            $io->section('Données O2S');
            $io->table(
                ['Champ', 'Valeur'],
                [
                    ['referenceDate (snapshot)', $valuationDate],
                    ['totalValue O2S', number_format($totalO2S, 2, '.', ' ') . ' €'],
                    ['liquidity O2S', number_format($liquidityO2S, 2, '.', ' ') . ' €'],
                    ['Nb lignes', (string) count($situation)],
                ]
            );
        }

        // 2) Pour chaque ligne avec ISIN, fetch Boursorama
        $rows = [];
        $totalRecalcule = 0.0;
        $totalIsinO2S = 0.0;
        $totalIsinBourso = 0.0;
        $covered = 0;
        $missing = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($situation as $line) {
            $isin = $line->getIsin();
            $qty = $line->getQuantity() ?? 0.0;
            $valO2S = $line->getValue() ?? 0.0;
            $navO2S = $line->getNetAssetValue();
            $navDateO2S = $line->getNetAssetValueDate();
            $assetType = $line->getAssetType();

            $totalIsinO2S += $valO2S;

            $row = [
                'isin' => $isin,
                'name' => $line->getAssetName(),
                'assetType' => $assetType,
                'qty' => $qty,
                'navO2S' => $navO2S,
                'navDateO2S' => $navDateO2S?->format('d/m/Y'),
                'valO2S' => $valO2S,
                'navBourso' => null,
                'currencyBourso' => null,
                'navDateBourso' => null,
                'valBourso' => null,
                'deltaAbs' => null,
                'deltaPct' => null,
                'daysFresher' => null,
                'status' => null,
            ];

            // Fonds euros / sans ISIN : on garde la valeur O2S, pas de scraping possible
            if (!$isin || !preg_match('/^[A-Z]{2}[A-Z0-9]{9}\d$/', $isin)) {
                $row['status'] = 'no_isin';
                $totalRecalcule += $valO2S;
                $rows[] = $row;
                continue;
            }

            $resolved = $this->resolver->resolveEur($isin);
            if ($resolved === null) {
                $row['status'] = 'not_found';
                $missing++;
                $totalRecalcule += $valO2S; // fallback sur valeur O2S
                $rows[] = $row;
                if ($throttleMs > 0) {
                    usleep($throttleMs * 1000);
                }
                continue;
            }

            $covered++;
            $eurQuote = $resolved->quote;        // VL en EUR
            $native = $resolved->nativeQuote;    // VL d'origine (peut être USD/GBP/...)

            $row['navBourso'] = $eurQuote->nav;
            $row['currencyBourso'] = $eurQuote->currency;
            $row['navDateBourso'] = $eurQuote->navDate?->format('d/m/Y');
            $row['nativeNav'] = $resolved->isConverted() ? $native->nav : null;
            $row['nativeCurrency'] = $resolved->isConverted() ? $native->currency : null;
            $row['fxRate'] = $resolved->fxRate;
            $row['source'] = $eurQuote->source;

            $valBourso = $qty * $eurQuote->nav;
            $row['valBourso'] = $valBourso;
            $row['deltaAbs'] = $valBourso - $valO2S;
            $row['deltaPct'] = $valO2S != 0.0 ? (($valBourso - $valO2S) / $valO2S) * 100.0 : null;

            if ($eurQuote->navDate && $navDateO2S) {
                $row['daysFresher'] = (int) $navDateO2S->diff($eurQuote->navDate)->format('%r%a');
            }

            $row['status'] = $resolved->isConverted() ? 'converted_fx' : 'ok';

            $totalIsinBourso += $valBourso;
            $totalRecalcule += $valBourso;

            $rows[] = $row;

            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }
        }

        // 3) On ajoute la part "liquidity" (qui correspond souvent au fonds euros côté assurance vie)
        if ($liquidityO2S > 0 && $totalO2S > $totalIsinO2S) {
            $delta = $totalO2S - $totalIsinO2S;
            if ($delta > 0) {
                $totalRecalcule += $delta;
            }
        }

        // 4) Affichage
        if ($jsonOut) {
            $output->writeln(json_encode([
                'compteId' => $compteId,
                'snapshotDate' => $valuationDate,
                'totalO2S' => $totalO2S,
                'totalRecalcule' => $totalRecalcule,
                'coverage' => ['covered' => $covered, 'missing' => $missing, 'noIsin' => count($rows) - $covered - $missing],
                'lines' => $rows,
                'fetchedAt' => $today->format('Y-m-d'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->section('Comparaison ligne à ligne');
        $tableRows = [];
        foreach ($rows as $r) {
            $statusIcon = match ($r['status']) {
                'ok' => '✓',
                'converted_fx' => sprintf('⇄ %s→EUR', $r['nativeCurrency'] ?? '?'),
                'no_isin' => '— fonds €',
                'not_found' => '✗ absent',
                default => '?',
            };
            $tableRows[] = [
                substr($r['isin'] ?? '—', 0, 12),
                substr($r['name'] ?? '', 0, 26),
                $r['qty'] !== null ? number_format($r['qty'], 2, '.', ' ') : '—',
                $r['navO2S'] !== null ? number_format($r['navO2S'], 4, '.', ' ') : '—',
                $r['navDateO2S'] ?? '—',
                $r['navBourso'] !== null ? number_format($r['navBourso'], 4, '.', ' ') : '—',
                $r['navDateBourso'] ?? '—',
                $r['daysFresher'] !== null ? sprintf('%+d', $r['daysFresher']) : '—',
                $r['deltaPct'] !== null ? sprintf('%+0.2f%%', $r['deltaPct']) : '—',
                $statusIcon,
            ];
        }
        $io->table(
            ['ISIN', 'Libellé', 'Qty', 'VL O2S', 'Date O2S', 'VL Bourso', 'Date Bourso', 'Δ j', 'Δ %', 'Statut'],
            $tableRows
        );

        $deltaTotal = $totalRecalcule - $totalO2S;
        $deltaPctTotal = $totalO2S != 0.0 ? ($deltaTotal / $totalO2S) * 100.0 : null;

        $io->section('Synthèse');
        $io->table(
            ['Indicateur', 'Valeur'],
            [
                ['Total O2S',                  number_format($totalO2S, 2, '.', ' ') . ' €'],
                ['Total recalculé (Bourso)',   number_format($totalRecalcule, 2, '.', ' ') . ' €'],
                ['Écart total',                sprintf('%+0.2f € (%s)', $deltaTotal, $deltaPctTotal !== null ? sprintf('%+0.3f%%', $deltaPctTotal) : '—')],
                ['Couverture Boursorama',      sprintf('%d/%d (%d%% des lignes avec ISIN)', $covered, $covered + $missing, ($covered + $missing) ? round(100 * $covered / max(1, $covered + $missing)) : 0)],
                ['Lignes sans ISIN',           sprintf('%d (fonds euros, gardent valeur O2S)', count($rows) - $covered - $missing)],
            ]
        );

        if ($covered === 0) {
            $io->warning('Aucune VL récupérée depuis Boursorama — vérifier l\'accès réseau ou le compte (fonds institutionnels ?).');
        } else {
            $freshCount = 0;
            $coherentCount = 0;
            foreach ($rows as $r) {
                if ($r['status'] !== 'ok') {
                    continue;
                }
                if (($r['daysFresher'] ?? 0) > 0) {
                    $freshCount++;
                }
                if ($r['deltaPct'] !== null && abs($r['deltaPct']) < 1.0) {
                    $coherentCount++;
                }
            }
            $io->success(sprintf(
                'Boursorama a fourni %d/%d VL alignées (<1%% d\'écart) et %d VL plus fraîches que O2S.',
                $coherentCount,
                $covered,
                $freshCount
            ));
        }

        return Command::SUCCESS;
    }
}
