<?php

namespace App\Command;

use App\Service\MarketData\TwelveDataClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-search-symbols',
    description: 'Inspecte TwelveData symbol_search pour une requête donnée (q[, exchange])',
)]
class TestSearchSymbolsCommand extends Command
{
    public function __construct(private readonly TwelveDataClient $twelve)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('q', InputArgument::REQUIRED, 'Requête (ISIN, nom, symbole)')
            ->addOption('exchange', null, InputOption::VALUE_REQUIRED, 'Exchange filter (ex: XPAR)', null)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Afficher les N premiers', '5')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Utiliser searchSymbolsFresh (bypass cache)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $q = (string) $input->getArgument('q');
        $exchange = $input->getOption('exchange');
        $limit = (int) $input->getOption('limit');

        $io->title(sprintf('symbol_search "%s"%s', $q, $exchange ? " (exchange=$exchange)" : ''));
        $useFresh = (bool) $input->getOption('fresh');
        $res = $useFresh
            ? $this->twelve->searchSymbolsFresh($q, $exchange ? (string) $exchange : null)
            : $this->twelve->searchSymbols($q, $exchange ? (string) $exchange : null);
        if (empty($res)) {
            $io->writeln('lastError=' . (string) ($this->twelve->getLastError() ?? 'null'));
            $io->warning('Aucun résultat');
            return Command::SUCCESS;
        }
        $i = 0;
        foreach ($res as $r) {
            $io->writeln(sprintf('%-16s  %-8s  %-6s  %-6s  %s',
                (string) ($r['symbol'] ?? ''),
                (string) ($r['exchange'] ?? ''),
                (string) ($r['type'] ?? ''),
                (string) ($r['currency'] ?? ''),
                (string) ($r['name'] ?? ''),
            ));
            $i++; if ($i >= $limit) break;
        }
        return Command::SUCCESS;
    }
}


