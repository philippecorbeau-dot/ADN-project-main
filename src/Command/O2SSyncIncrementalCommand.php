<?php

declare(strict_types=1);

namespace App\Command;

use App\Integration\O2S\Sync\O2SSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'o2s:sync-incremental',
    description: 'Synchronisation incrémentale O2S : détecte et synchronise uniquement les nouveaux contacts/comptes. Conçu pour un cron toutes les 15 minutes.',
)]
class O2SSyncIncrementalCommand extends Command
{
    public function __construct(
        private readonly O2SSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('contacts-only', null, InputOption::VALUE_NONE, 'Synchroniser uniquement les nouveaux contacts (sans comptes)')
            ->addOption('valuations', null, InputOption::VALUE_NONE, 'Mettre à jour les valorisations par lot')
            ->addOption('missing-comptes', null, InputOption::VALUE_NONE, 'Synchroniser les comptes des utilisateurs O2S qui n\'en ont pas encore')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Taille du lot', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $valuationsOnly = $input->getOption('valuations');
        $contactsOnly = $input->getOption('contacts-only');
        $missingComptes = $input->getOption('missing-comptes');
        $batchSize = (int) $input->getOption('batch-size');

        $startTime = microtime(true);

        try {
            if ($valuationsOnly) {
                $io->title('O2S — Mise à jour des valorisations (lot)');
                $result = $this->syncService->syncValuationsBatch($batchSize);
                
                $io->table(
                    ['Statistique', 'Valeur'],
                    [
                        ['Mis à jour', $result->getUpdated()],
                        ['Erreurs', count($result->getErrors())],
                    ]
                );

                if ($result->hasErrors()) {
                    foreach ($result->getErrors() as $error) {
                        $io->text('  ⚠ ' . $error);
                    }
                }

                $duration = round(microtime(true) - $startTime, 1);
                $io->success(sprintf('Valorisations mises à jour en %ss', $duration));
                return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
            }

            if ($missingComptes) {
                $io->title('O2S — Synchronisation des comptes manquants');
                $result = $this->syncService->syncMissingComptes($batchSize);
                
                $io->table(
                    ['Statistique', 'Valeur'],
                    [
                        ['Comptes créés', $result->getCreated()],
                        ['Comptes mis à jour', $result->getUpdated()],
                        ['Ignorés', $result->getSkipped()],
                        ['Erreurs', count($result->getErrors())],
                    ]
                );

                if ($result->hasErrors()) {
                    foreach ($result->getErrors() as $error) {
                        $io->text('  ⚠ ' . $error);
                    }
                }

                $duration = round(microtime(true) - $startTime, 1);
                $io->success(sprintf('Comptes manquants synchronisés en %ss', $duration));
                return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
            }

            // Default: incremental contacts + comptes
            $io->title('O2S — Synchronisation incrémentale');
            $result = $this->syncService->syncNewContacts();

            $io->table(
                ['Statistique', 'Valeur'],
                [
                    ['Nouveaux contacts créés', $result->getCreated()],
                    ['Contacts mis à jour', $result->getUpdated()],
                    ['Erreurs', count($result->getErrors())],
                ]
            );

            if ($result->getCreated() === 0 && $result->getUpdated() === 0) {
                $io->info('Aucun nouveau contact détecté — base à jour.');
            }

            // Automatically sync missing comptes after contacts sync
            $missingResult = $this->syncService->syncMissingComptes($batchSize);
            if ($missingResult->getCreated() > 0 || $missingResult->getUpdated() > 0) {
                $io->section('Comptes manquants synchronisés');
                $io->table(
                    ['Statistique', 'Valeur'],
                    [
                        ['Comptes créés', $missingResult->getCreated()],
                        ['Comptes mis à jour', $missingResult->getUpdated()],
                        ['Erreurs', count($missingResult->getErrors())],
                    ]
                );
                $result->merge($missingResult);
            }

            if ($result->hasErrors()) {
                $io->section('Erreurs');
                foreach ($result->getErrors() as $error) {
                    $io->text('  ⚠ ' . $error);
                }
            }

            $duration = round(microtime(true) - $startTime, 1);
            $io->success(sprintf('Synchronisation incrémentale terminée en %ss', $duration));
            return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}

