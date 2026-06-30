<?php

namespace App\Command;

use App\Service\MarketData\FundNavClient;
use App\Service\MarketData\QuoteAggregator;
use App\Service\MarketData\ReferenceDataClient;
use App\Service\MarketData\TwelveDataClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-support-curation',
    description: 'Teste la curation de supports (recherche vide) et l\'enrichissement des VL',
)]
class TestSupportCurationCommand extends Command
{
    public function __construct(
        private readonly TwelveDataClient $twelve,
        private readonly QuoteAggregator $quotes,
        private readonly ReferenceDataClient $refdata,
        private readonly FundNavClient $fundNav,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('exchange', InputArgument::OPTIONAL, 'Code bourse (XPAR, XAMS, …)', 'XPAR')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max à afficher', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $exchange = strtoupper((string) $input->getArgument('exchange'));
        $limit = max(1, (int) $input->getOption('limit'));

        $io->title("Curation $exchange (limit=$limit)");

        $list = $this->twelve->getCuratedForExchange($exchange);
        $rows = [];
        foreach ($list as $it) {
            $row = $it; // symbol, name, exchange, currency, isin?, type?
            $q = $this->quotes->getLast($row['symbol'], $exchange);
            $row['nav'] = $q['nav'];
            $row['navDate'] = $q['navDate'];

            if (!empty($row['isin'])) {
                $loc = $this->fundNav->getByIsin($row['isin']);
                if ($loc['nav'] !== null) {
                    $row['nav'] = $loc['nav'];
                    $row['navDate'] = $loc['navDate'];
                }
            }

            // Compléments refdata
            $ref = $this->refdata->resolve($row['symbol'], $exchange);
            if (!empty($ref['isin'])) $row['isin'] = $ref['isin'];
            if (!empty($ref['type'])) $row['type'] = $ref['type'];
            if (!empty($ref['name']) && (empty($row['name']) || $row['name'] === $row['symbol'])) $row['name'] = $ref['name'];

            // Fallback ISIN -> TwelveData si VL absente
            if ((empty($row['nav']) || $row['nav'] === null) && !empty($row['isin'])) {
                try {
                    $matches = $this->twelve->searchSymbols($row['isin'], null);
                    if (!empty($matches)) {
                        usort($matches, function($a,$b){
                            $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            return $aw <=> $bw;
                        });
                        $alt = $matches[0];
                        $altSymbol = $alt['symbol'] ?? null;
                        $altExchange = $alt['exchange'] ?? null;
                        if ($altSymbol) {
                            $q2 = $this->quotes->getLast($altSymbol, $altExchange ?: null);
                            if ($q2['nav'] !== null) {
                                $row['nav'] = $q2['nav'];
                                $row['navDate'] = $q2['navDate'];
                                $row['symbol'] = $altSymbol;
                                if (!empty($altExchange)) $row['exchange'] = $altExchange;
                                if (empty($row['type']) && !empty($alt['type'])) $row['type'] = $alt['type'];
                                if (empty($row['currency']) && !empty($alt['currency'])) $row['currency'] = $alt['currency'];
                                if (empty($row['isin']) && !empty($alt['isin'])) $row['isin'] = $alt['isin'];
                            }
                        }
                    }
                } catch (\Throwable $e) { /* no-op */ }
            }

            // Fallback SYMBOL -> TwelveData si toujours vide
            if ((empty($row['nav']) || $row['nav'] === null) && !empty($row['symbol'])) {
                try {
                    $matches = $this->twelve->searchSymbols($row['symbol'], null);
                    if (!empty($matches)) {
                        usort($matches, function($a,$b){
                            $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            return $aw <=> $bw;
                        });
                        $alt = $matches[0];
                        $altSymbol = $alt['symbol'] ?? null;
                        $altExchange = $alt['exchange'] ?? null;
                        if ($altSymbol) {
                            $q2 = $this->quotes->getLast($altSymbol, $altExchange ?: null);
                            if ($q2['nav'] !== null) {
                                $row['nav'] = $q2['nav'];
                                $row['navDate'] = $q2['navDate'];
                                $row['symbol'] = $altSymbol;
                                if (!empty($altExchange)) $row['exchange'] = $altExchange;
                                if (empty($row['type']) && !empty($alt['type'])) $row['type'] = $alt['type'];
                                if (empty($row['currency']) && !empty($alt['currency'])) $row['currency'] = $alt['currency'];
                                if (empty($row['isin']) && !empty($alt['isin'])) $row['isin'] = $alt['isin'];
                            }
                        }
                    }
                } catch (\Throwable $e) { /* no-op */ }
            }

            // Fallback NAME -> TwelveData si toujours vide
            if ((empty($row['nav']) || $row['nav'] === null) && !empty($row['name'])) {
                try {
                    $matches = $this->twelve->searchSymbols($row['name'], null);
                    if (!empty($matches)) {
                        usort($matches, function($a,$b){
                            $aw = (stripos($a['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            $bw = (stripos($b['type'] ?? '', 'fund') !== false) ? 0 : 1;
                            return $aw <=> $bw;
                        });
                        $alt = $matches[0];
                        $altSymbol = $alt['symbol'] ?? null;
                        $altExchange = $alt['exchange'] ?? null;
                        if ($altSymbol) {
                            $q2 = $this->quotes->getLast($altSymbol, $altExchange ?: null);
                            if ($q2['nav'] !== null) {
                                $row['nav'] = $q2['nav'];
                                $row['navDate'] = $q2['navDate'];
                                $row['symbol'] = $altSymbol;
                                if (!empty($altExchange)) $row['exchange'] = $altExchange;
                                if (empty($row['type']) && !empty($alt['type'])) $row['type'] = $alt['type'];
                                if (empty($row['currency']) && !empty($alt['currency'])) $row['currency'] = $alt['currency'];
                                if (empty($row['isin']) && !empty($alt['isin'])) $row['isin'] = $alt['isin'];
                            }
                        }
                    }
                } catch (\Throwable $e) { /* no-op */ }
            }

            $rows[] = $row;
            if (count($rows) >= $limit) break;
        }

        foreach ($rows as $r) {
            $line = sprintf(
                "%-35s  %-14s  %-6s  %-6s  %-10s  %s",
                (string) ($r['name'] ?? $r['symbol']),
                (string) ($r['symbol'] ?? ''),
                (string) ($r['exchange'] ?? ''),
                (string) ($r['type'] ?? ''),
                ($r['nav'] !== null ? number_format((float)$r['nav'], 2, ',', ' ') : '—'),
                (string) ($r['navDate'] ?? '—'),
            );
            if (($r['nav'] === null || $r['nav'] === '') && (!empty($r['isin']) || !empty($r['symbol']))) {
                try {
                    $probe = $this->twelve->searchSymbols(!empty($r['isin']) ? $r['isin'] : $r['symbol'], null);
                    $alt = $probe[0]['symbol'] ?? '';
                    $altEx = $probe[0]['exchange'] ?? '';
                    $line .= sprintf("   [probe:%s@%s]", $alt, $altEx);
                    // Essai direct time_series sur le symbole courant (peut être un ISIN)
                    $series = $this->twelve->getTimeSeries($r['symbol']);
                    if (!empty($series)) {
                        $first = $series[0];
                        $line .= sprintf("   [ts:%s@%s]", (string)($first['close'] ?? ''), (string)($first['datetime'] ?? ''));
                    } else {
                        $line .= "   [ts:empty]";
                    }
                    $q = $this->twelve->getQuote($r['symbol']);
                    if (!empty($q) && isset($q['close'])) {
                        $line .= sprintf("   [quote:%s]", (string)($q['close'] ?? ''));
                    } else {
                        $line .= "   [quote:empty]";
                    }
                } catch (\Throwable $e) {
                    $line .= "   [probe:error]";
                }
            }
            $io->writeln($line);
        }

        return Command::SUCCESS;
    }
}


