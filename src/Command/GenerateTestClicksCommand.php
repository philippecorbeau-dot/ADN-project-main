<?php

namespace App\Command;

use App\Entity\InvestmentOpportunityClick;
use App\Repository\InvestmentOpportunityClickRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-test-clicks',
    description: 'Génère des données de test pour les clics sur les opportunités d\'investissement'
)]
class GenerateTestClicksCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private InvestmentOpportunityClickRepository $clickRepository;

    public function __construct(EntityManagerInterface $entityManager, InvestmentOpportunityClickRepository $clickRepository)
    {
        $this->entityManager = $entityManager;
        $this->clickRepository = $clickRepository;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $products = [
            InvestmentOpportunityClick::PRODUCT_SCPI,
            InvestmentOpportunityClick::PRODUCT_PEA_PME,
            InvestmentOpportunityClick::PRODUCT_ASSURANCE_VIE,
            InvestmentOpportunityClick::PRODUCT_PER
        ];

        $actions = [
            InvestmentOpportunityClick::ACTION_DISCOVER,
            InvestmentOpportunityClick::ACTION_DOCUMENTS
        ];

        $clicksGenerated = 0;

        // Générer des clics pour les 30 derniers jours
        for ($i = 30; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} days");
            
            // Nombre aléatoire de clics par jour (entre 5 et 25)
            $dailyClicks = rand(5, 25);
            
            for ($j = 0; $j < $dailyClicks; $j++) {
                $click = new InvestmentOpportunityClick();
                $click->setProductType($products[array_rand($products)]);
                $click->setAction($actions[array_rand($actions)]);
                $click->setIpAddress($this->generateRandomIp());
                $click->setUserAgent('Test User Agent');
                $click->setClickedAt($date->setTime(rand(8, 20), rand(0, 59), rand(0, 59)));
                
                $this->entityManager->persist($click);
                $clicksGenerated++;
            }
        }

        $this->entityManager->flush();

        $io->success("Génération terminée ! {$clicksGenerated} clics de test créés.");

        // Afficher les statistiques
        $stats = $this->clickRepository->getClickStatsByProduct();
        $io->table(['Produit', 'Clics'], array_map(function($stat) {
            return [$stat['productType'], $stat['clicks']];
        }, $stats));

        return Command::SUCCESS;
    }

    private function generateRandomIp(): string
    {
        return rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    }
}

