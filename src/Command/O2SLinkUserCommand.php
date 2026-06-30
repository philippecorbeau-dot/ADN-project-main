<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User\User;
use App\Integration\O2S\Service\ContactServiceInterface;
use App\Integration\O2S\Sync\O2SSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'o2s:link',
    description: 'Lie un utilisateur local à un contact O2S',
)]
class O2SLinkUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContactServiceInterface $contactService,
        private readonly O2SSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'ID de l\'utilisateur local')
            ->addArgument('o2s-contact-id', InputArgument::OPTIONAL, 'ID du contact O2S (ou auto pour recherche automatique)')
            ->addOption('auto', 'a', InputOption::VALUE_NONE, 'Rechercher automatiquement le contact O2S par email/nom')
            ->addOption('sync', 's', InputOption::VALUE_NONE, 'Synchroniser les comptes après liaison');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $userId = (int) $input->getArgument('user-id');
        $o2sContactId = $input->getArgument('o2s-contact-id');
        $autoFind = $input->getOption('auto');
        $syncAfter = $input->getOption('sync');

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            $io->error(sprintf('Utilisateur #%d introuvable', $userId));
            return Command::FAILURE;
        }

        $io->title(sprintf('Liaison O2S pour: %s (%s)', 
            $user->getFullName() ?: 'N/A',
            $user->getEmail()
        ));

        // Already linked?
        if ($user->getO2sContactId()) {
            $io->warning(sprintf('Cet utilisateur est déjà lié au contact O2S: %s', $user->getO2sContactId()));
            if (!$io->confirm('Voulez-vous modifier cette liaison?', false)) {
                return Command::SUCCESS;
            }
        }

        // Auto-find contact
        if ($autoFind || !$o2sContactId) {
            $io->section('Recherche automatique du contact O2S');
            
            $contacts = [];
            
            // Try by email
            if ($user->getEmail()) {
                $io->text(sprintf('Recherche par email: %s', $user->getEmail()));
                $contact = $this->contactService->findByEmail($user->getEmail());
                if ($contact) {
                    $contacts[$contact->getId()] = $contact;
                }
            }

            // Try by name
            if ($user->getLastname()) {
                $io->text(sprintf('Recherche par nom: %s', $user->getLastname()));
                // Get all contacts and filter by name
                $allContacts = $this->contactService->getAllContacts();
                foreach ($allContacts as $contact) {
                    if (strtolower($contact->getNom() ?? '') === strtolower($user->getLastname())) {
                        $contacts[$contact->getId()] = $contact;
                    }
                }
            }

            if (empty($contacts)) {
                $io->error('Aucun contact O2S correspondant trouvé');
                $io->text('Vous pouvez spécifier manuellement l\'ID du contact O2S:');
                $io->text('  php bin/console o2s:link ' . $userId . ' <o2s-contact-id>');
                return Command::FAILURE;
            }

            if (count($contacts) === 1) {
                $contact = reset($contacts);
                $o2sContactId = $contact->getId();
                $io->success(sprintf('Contact trouvé: %s (%s)', $contact->getFullName(), $o2sContactId));
            } else {
                // Multiple matches - let user choose
                $io->text(sprintf('Plusieurs contacts O2S trouvés (%d):', count($contacts)));
                
                $choices = [];
                foreach ($contacts as $contact) {
                    $label = sprintf('%s - %s (%s)', 
                        $contact->getId(),
                        $contact->getFullName(),
                        $contact->getEmail() ?? 'pas d\'email'
                    );
                    $choices[$contact->getId()] = $label;
                }

                $o2sContactId = $io->choice('Sélectionnez le contact O2S', $choices);
            }
        }

        // Verify contact exists
        $io->section('Vérification du contact O2S');
        try {
            $o2sContact = $this->contactService->getContact($o2sContactId);
            $io->table(
                ['Champ', 'Valeur'],
                [
                    ['ID', $o2sContact->getId()],
                    ['Nom', $o2sContact->getFullName()],
                    ['Email', $o2sContact->getEmail() ?? '-'],
                    ['Téléphone', $o2sContact->getTelephone() ?? '-'],
                ]
            );
        } catch (\Throwable $e) {
            $io->error(sprintf('Contact O2S introuvable: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        // Confirm and link
        if (!$io->confirm('Confirmer la liaison?', true)) {
            $io->text('Opération annulée');
            return Command::SUCCESS;
        }

        $user->setO2sContactId($o2sContactId);
        $user->setO2sSyncedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur #%d lié au contact O2S %s', $userId, $o2sContactId));

        // Sync comptes if requested
        if ($syncAfter) {
            $io->section('Synchronisation des comptes');
            $result = $this->syncService->syncComptesForUser($user);
            $this->entityManager->flush();

            $io->table(
                ['Statistique', 'Valeur'],
                [
                    ['Comptes créés', $result->getCreated()],
                    ['Comptes mis à jour', $result->getUpdated()],
                    ['Ignorés', $result->getSkipped()],
                ]
            );

            if ($result->hasErrors()) {
                $io->section('Erreurs');
                foreach ($result->getErrors() as $error) {
                    $io->text('  • ' . $error);
                }
            }
        }

        return Command::SUCCESS;
    }
}

