<?php

namespace App\Command;

use App\Services\TwelveDataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-all-markets',
    description: 'Synchronise les données de tous les marchés boursiers',
)]
class SyncAllMarketsCommand extends Command
{
    private $twelveDataService;

    public function __construct(TwelveDataService $twelveDataService)
    {
        parent::__construct();
        $this->twelveDataService = $twelveDataService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation de tous les marchés boursiers');

        if (!$this->twelveDataService->getApiKey()) {
            $io->error('Clé API Twelve Data non configurée');
            return Command::FAILURE;
        }

        $markets = [
            'US Tech' => $this->twelveDataService->getDefaultStocks(),
            'CAC 40' => $this->twelveDataService->getCAC40Stocks(),
            'Chine' => $this->twelveDataService->getChineseStocks(),
            'Allemagne' => $this->twelveDataService->getGermanStocks(),
            'Indices' => $this->twelveDataService->getIndices()
        ];

        $totalStocks = 0;
        foreach ($markets as $marketName => $stocks) {
            $io->text("📊 $marketName: " . count($stocks) . " actions synchronisées");
            $totalStocks += count($stocks);
        }

        $io->success("Synchronisation terminée : $totalStocks actions au total");

        return Command::SUCCESS;
    }
} 