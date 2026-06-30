<?php

namespace App\Command;

use App\Services\TwelveDataService;
use App\Repository\StockRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-stock-data',
    description: 'Synchronise les données boursières depuis l\'API Twelve Data',
)]
class SyncStockDataCommand extends Command
{
    private $twelveDataService;
    private $stockRepository;

    public function __construct(TwelveDataService $twelveDataService, StockRepository $stockRepository)
    {
        parent::__construct();
        $this->twelveDataService = $twelveDataService;
        $this->stockRepository = $stockRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation des données boursières');

        if (!$this->twelveDataService->getApiKey()) {
            $io->error('Clé API Twelve Data non configurée');
            return Command::FAILURE;
        }

        $symbols = ['AAPL', 'TSLA', 'MSFT', 'GOOGL', 'AMZN'];
        $updatedCount = 0;

        foreach ($symbols as $symbol) {
            $io->text("Récupération des données pour {$symbol}...");
            
            $stockData = $this->twelveDataService->getStockQuote($symbol);
            
            if ($stockData) {
                $this->stockRepository->updateStockData($symbol, $stockData);
                $updatedCount++;
                $io->text("✓ {$symbol} mis à jour");
            } else {
                $io->text("✗ Erreur pour {$symbol}");
            }
        }

        $io->success("Synchronisation terminée : {$updatedCount} actions mises à jour");

        return Command::SUCCESS;
    }
} 