<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User\User;
use App\Integration\O2S\Sync\O2SSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'o2s:sync',
    description: 'Synchronise les données O2S (contacts et comptes) avec la base locale',
)]
class O2SSyncCommand extends Command
{
    public function __construct(
        private readonly O2SSyncService $syncService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('contacts', 'c', InputOption::VALUE_NONE, 'Synchroniser uniquement les contacts')
            ->addOption('comptes', null, InputOption::VALUE_NONE, 'Synchroniser uniquement les comptes')
            ->addOption('fix-emails', null, InputOption::VALUE_NONE, 'Corriger les emails placeholder (@placeholder.local) avec les vraies adresses O2S')
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'Synchroniser un utilisateur spécifique par ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans enregistrer les modifications');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $syncContacts = $input->getOption('contacts');
        $syncComptes = $input->getOption('comptes');
        $fixEmails = $input->getOption('fix-emails');
        $userId = $input->getOption('user-id');
        $dryRun = $input->getOption('dry-run');

        // If neither specified, sync both
        if (!$syncContacts && !$syncComptes && !$fixEmails) {
            $syncContacts = true;
            $syncComptes = true;
        }

        $io->title('Synchronisation O2S');

        if ($dryRun) {
            $io->warning('Mode simulation activé - aucune modification ne sera enregistrée');
        }

        try {
            // Sync specific user
            if ($userId) {
                return $this->syncUser((int) $userId, $io, $dryRun);
            }

            // Full sync
            if ($syncContacts && $syncComptes) {
                $io->section('Synchronisation complète (contacts + comptes)');
                $results = $this->syncService->syncAll();

                $io->section('Résultats - Contacts');
                $this->displayResult($io, $results['contacts']);

                $io->section('Résultats - Comptes');
                $this->displayResult($io, $results['comptes']);

                if ($results['contacts']->hasErrors() || $results['comptes']->hasErrors()) {
                    $io->warning('Synchronisation terminée avec des erreurs');
                    return Command::FAILURE;
                }

                $io->success('Synchronisation complète terminée avec succès!');
                return Command::SUCCESS;
            }

            // Contacts only
            if ($syncContacts) {
                $io->section('Synchronisation des contacts');
                $result = $this->syncService->syncAllContacts();
                $this->displayResult($io, $result);

                if ($result->hasErrors()) {
                    return Command::FAILURE;
                }

                $io->success('Synchronisation des contacts terminée!');
                return Command::SUCCESS;
            }

            // Comptes only
            if ($syncComptes) {
                $io->section('Synchronisation des comptes');
                $result = $this->syncService->syncAllComptes();
                $this->displayResult($io, $result);

                if ($result->hasErrors()) {
                    return Command::FAILURE;
                }

                $io->success('Synchronisation des comptes terminée!');
                return Command::SUCCESS;
            }

            // Fix placeholder emails
            if ($fixEmails) {
                return $this->fixPlaceholderEmails($io);
            }

        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur lors de la synchronisation: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function syncUser(int $userId, SymfonyStyle $io, bool $dryRun): int
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            $io->error(sprintf('Utilisateur #%d introuvable', $userId));
            return Command::FAILURE;
        }

        $io->section(sprintf('Synchronisation de l\'utilisateur: %s (%s)', 
            $user->getFullName() ?: 'N/A',
            $user->getEmail()
        ));

        if (!$user->getO2sContactId()) {
            $io->warning('Cet utilisateur n\'est pas lié à un contact O2S');
            $io->text('Utilisez la commande o2s:link pour le lier à un contact O2S');
            return Command::FAILURE;
        }

        $io->text(sprintf('Contact O2S: %s', $user->getO2sContactId()));

        $result = $this->syncService->syncComptesForUser($user);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $this->displayResult($io, $result);

        if ($result->hasErrors()) {
            return Command::FAILURE;
        }

        $io->success('Synchronisation utilisateur terminée!');
        return Command::SUCCESS;
    }

    private function fixPlaceholderEmails(SymfonyStyle $io): int
    {
        $total = $this->syncService->countPlaceholderEmails();
        $io->section(sprintf('Correction des emails placeholder (%d à traiter)', $total));

        if ($total === 0) {
            $io->success('Aucun email placeholder à corriger !');
            return Command::SUCCESS;
        }

        $io->text('Analyse de tous les contacts O2S en cours... (1 appel API par contact)');
        $io->newLine();

        // Single pass: process ALL placeholder users
        $result = $this->syncService->fixPlaceholderEmails();

        $fixed = $result->getMetadata('fixed') ?? 0;
        $noEmail = $result->getMetadata('noEmail') ?? 0;
        $conflicts = $result->getMetadata('conflicts') ?? 0;
        $conflictsResolved = $result->getMetadata('conflictsResolved') ?? 0;
        $remaining = $result->getMetadata('remaining') ?? 0;
        $errors = count($result->getErrors());

        $io->newLine();
        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Emails corrigés', $fixed],
                ['  ↳ dont conflits résolus (couples/familles)', $conflictsResolved],
                ['Contacts sans email dans O2S', $noEmail],
                ['Conflits non résolus', $conflicts],
                ['Erreurs', $errors],
                ['Placeholder restants', $remaining],
            ]
        );

        if (!empty($result->getErrors())) {
            $io->warning('Erreurs rencontrées :');
            foreach ($result->getErrors() as $error) {
                $io->text('  ⚠ ' . $error);
            }
        }

        if ($fixed > 0) {
            $io->success(sprintf('%d email(s) placeholder corrigé(s) avec les vraies adresses O2S !', $fixed));
        } elseif ($noEmail > 0) {
            $io->warning(sprintf(
                'Aucun email corrigé. %d contacts n\'ont pas d\'email dans O2S/Harvest (personnes morales, anciens contacts...).',
                $noEmail
            ));
        }

        return Command::SUCCESS;
    }

    private function displayResult(SymfonyStyle $io, \App\Integration\O2S\Sync\SyncResult $result): void
    {
        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Créés', $result->getCreated()],
                ['Mis à jour', $result->getUpdated()],
                ['Ignorés', $result->getSkipped()],
                ['Total traités', $result->getTotal()],
                ['Erreurs', count($result->getErrors())],
            ]
        );

        if ($result->hasErrors()) {
            $io->section('Erreurs rencontrées');
            foreach ($result->getErrors() as $error) {
                $io->text('  • ' . $error);
            }
        }
    }
}
