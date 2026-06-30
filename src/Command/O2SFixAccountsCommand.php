<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ProductAccount;
use App\Integration\O2S\Service\CompteServiceInterface;
use App\Integration\O2S\Service\InstitutionServiceInterface;
use App\Integration\O2S\Service\ProductServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'o2s:fix-accounts',
    description: 'Corrige et met à jour TOUS les comptes O2S : valorisations, noms d\'établissements, versements, etc.',
)]
class O2SFixAccountsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompteServiceInterface $compteService,
        private readonly ProductServiceInterface $productService,
        private readonly InstitutionServiceInterface $institutionService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher les changements sans les appliquer')
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'Corriger uniquement les comptes d\'un utilisateur spécifique')
            ->addOption('verbose-report', null, InputOption::VALUE_NONE, 'Afficher un rapport détaillé pour chaque compte');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $userId = $input->getOption('user-id');
        $verboseReport = $input->getOption('verbose-report');

        $startTime = microtime(true);

        $io->title('O2S — Correction complète des comptes');
        if ($dryRun) {
            $io->warning('MODE DRY-RUN : aucune modification ne sera appliquée');
        }

        // 1. Pre-load O2S Products & Institutions maps (cached)
        $io->section('Chargement des référentiels O2S...');
        try {
            $productsMap = $this->productService->getProductsMap(); // productId => ProductDTO
            $institutionsMap = $this->institutionService->getInstitutionsMap(); // institutionId => label
            $io->text(sprintf('  ✓ %d produits, %d établissements chargés', count($productsMap), count($institutionsMap)));
        } catch (\Throwable $e) {
            $io->error('Impossible de charger les référentiels O2S : ' . $e->getMessage());
            return Command::FAILURE;
        }

        // 2. Fetch all O2S product accounts
        $qb = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->orderBy('p.id', 'ASC');

        if ($userId) {
            $qb->andWhere('p.user = :userId')->setParameter('userId', (int) $userId);
        }

        /** @var ProductAccount[] $accounts */
        $accounts = $qb->getQuery()->getResult();
        $totalAccounts = count($accounts);

        $io->section(sprintf('Traitement de %d comptes O2S...', $totalAccounts));

        $stats = [
            'processed' => 0,
            'valuation_updated' => 0,
            'distributor_updated' => 0,
            'name_updated' => 0,
            'errors' => [],
            'details' => [],
        ];

        $progressBar = $io->createProgressBar($totalAccounts);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Démarrage...');
        $progressBar->start();

        foreach ($accounts as $account) {
            $compteId = $account->getO2sCompteId();
            $productId = $account->getId();
            $oldAlias = $account->getDisplayAlias();
            $oldValuation = $account->getO2sValuation();
            $oldDistributor = $account->getDistributor();
            $changes = [];

            $progressBar->setMessage($oldAlias ?? "Compte #$productId");

            try {
                // 2a. Fetch CompteDTO from /comptes/{id}
                $compte = $this->compteService->getCompte($compteId);
                $apiMontant = $compte->getMontant();
                $apiDateValeur = $compte->getDateValeur();
                $apiLibelle = $compte->getLibelle();

                // 2b. Update display name/alias if different
                if ($apiLibelle && $apiLibelle !== $oldAlias) {
                    $changes[] = sprintf('Nom: "%s" → "%s"', $oldAlias, $apiLibelle);
                    if (!$dryRun) {
                        $account->setDisplayAlias($apiLibelle);
                        $account->setInternalName($compte->getDisplayName());
                    }
                    $stats['name_updated']++;
                }

                // 2c. Resolve actual institution name (instead of "O2S - Harvest")
                $resolvedDistributor = $this->resolveInstitutionLabel($compte, $productsMap, $institutionsMap);
                if ($resolvedDistributor && $resolvedDistributor !== $oldDistributor) {
                    $changes[] = sprintf('Établissement: "%s" → "%s"', $oldDistributor, $resolvedDistributor);
                    if (!$dryRun) {
                        $account->setDistributor($resolvedDistributor);
                    }
                    $stats['distributor_updated']++;
                }

                // 2d. Calculate correct valuation
                // Strategy: montant from /comptes + liquidity from /accounts/details
                $newValuation = null;
                $valuationSource = '';

                try {
                    $details = $this->compteService->getAccountDetails($compteId);
                    $liquidity = $details->getLiquidity() ?? 0.0;
                    $detailTotalValue = $details->getTotalValue() ?? 0.0;
                    $detailValuationDate = $details->getValuationDate();

                    if ($apiMontant !== null && $apiMontant > 0) {
                        // Best case: montant API (valeur titres/UC) + liquidité
                        $newValuation = $apiMontant + $liquidity;
                        $valuationSource = 'montant_api + liquidity';
                    } elseif ($detailTotalValue > 0 || $liquidity > 0) {
                        // Fallback: totalValue + liquidity from account-details
                        $newValuation = $detailTotalValue + $liquidity;
                        $valuationSource = 'account-details (totalValue + liquidity)';
                    } elseif ($apiMontant !== null && $apiMontant > 0) {
                        // Only montant, no account-details
                        $newValuation = $apiMontant;
                        $valuationSource = 'montant_api only';
                    }

                    if ($detailValuationDate && !$dryRun) {
                        $account->setO2sValuationDate($detailValuationDate);
                    }
                } catch (\Throwable $detailsError) {
                    // account-details not available (400 for some accounts like SwissLife)
                    if ($apiMontant !== null && $apiMontant > 0) {
                        $newValuation = $apiMontant;
                        $valuationSource = 'montant_api (account-details indisponible)';
                    }
                    $this->logger->debug('account-details error', [
                        'compteId' => $compteId,
                        'error' => $detailsError->getMessage(),
                    ]);
                }

                // Apply valuation if changed
                if ($newValuation !== null) {
                    $oldVal = $oldValuation !== null ? (float) $oldValuation : null;
                    $diff = $oldVal !== null ? $newValuation - $oldVal : null;

                    if ($oldVal === null || abs($newValuation - $oldVal) > 0.01) {
                        $changes[] = sprintf(
                            'Valorisation: %s → %s (%s) [%s]',
                            $oldVal !== null ? number_format($oldVal, 2, ',', ' ') . ' €' : 'N/A',
                            number_format($newValuation, 2, ',', ' ') . ' €',
                            $diff !== null ? sprintf('%+.2f €', $diff) : 'nouveau',
                            $valuationSource
                        );
                        if (!$dryRun) {
                            $account->setO2sValuation((string) $newValuation);
                        }
                        $stats['valuation_updated']++;
                    }
                }

                // Update valuation date from API
                if ($apiDateValeur && !$dryRun) {
                    $account->setO2sValuationDate($apiDateValeur);
                }

                // Update fiscal date
                if ($compte->getDateOuverture() && !$dryRun) {
                    $account->setFiscalDate($compte->getDateOuverture());
                }

                // Mark as synced
                if (!$dryRun) {
                    $account->setO2sSyncedAt(new \DateTimeImmutable());
                }

                $stats['processed']++;

                if (!empty($changes)) {
                    $stats['details'][] = [
                        'id' => $productId,
                        'alias' => $oldAlias ?? "Compte #$productId",
                        'compteId' => $compteId,
                        'changes' => $changes,
                    ];
                }

            } catch (\Throwable $e) {
                $stats['errors'][] = sprintf(
                    'Compte #%d (%s) [%s]: %s',
                    $productId,
                    $oldAlias ?? '?',
                    $compteId,
                    $e->getMessage()
                );
                $this->logger->error('Fix accounts error', [
                    'productId' => $productId,
                    'compteId' => $compteId,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        // Flush all changes
        if (!$dryRun) {
            try {
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $io->error('Erreur lors du flush: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        // 3. Report
        $io->section('Résumé');
        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Comptes traités', $stats['processed'] . '/' . $totalAccounts],
                ['Valorisations mises à jour', $stats['valuation_updated']],
                ['Établissements corrigés', $stats['distributor_updated']],
                ['Noms mis à jour', $stats['name_updated']],
                ['Erreurs', count($stats['errors'])],
            ]
        );

        // Detailed changes
        if (!empty($stats['details'])) {
            $io->section('Détail des modifications');
            foreach ($stats['details'] as $detail) {
                $io->text(sprintf(
                    '  <info>#%d</info> %s',
                    $detail['id'],
                    $detail['alias']
                ));
                foreach ($detail['changes'] as $change) {
                    $io->text('    → ' . $change);
                }
            }
        }

        // Errors
        if (!empty($stats['errors'])) {
            $io->section('Erreurs');
            foreach ($stats['errors'] as $error) {
                $io->text('  ⚠ ' . $error);
            }
        }

        $duration = round(microtime(true) - $startTime, 1);
        $io->success(sprintf(
            '%s en %ss — %d comptes traités, %d valorisations mises à jour, %d établissements corrigés',
            $dryRun ? 'Simulation terminée' : 'Correction terminée',
            $duration,
            $stats['processed'],
            $stats['valuation_updated'],
            $stats['distributor_updated']
        ));

        return empty($stats['errors']) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Resolves the actual institution label from O2S Products + Institutions APIs.
     */
    private function resolveInstitutionLabel(
        \App\Integration\O2S\DTO\Compte\CompteDTO $compte,
        array $productsMap,
        array $institutionsMap,
    ): ?string {
        $produitId = $compte->getProduitId();
        if (!$produitId || !isset($productsMap[$produitId])) {
            return null;
        }

        $product = $productsMap[$produitId];
        $institutionId = $product->getInstitutionId();
        if (!$institutionId || !isset($institutionsMap[$institutionId])) {
            return null;
        }

        return $institutionsMap[$institutionId];
    }
}

