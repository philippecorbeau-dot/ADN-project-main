<?php

namespace App\Command;

use App\Entity\User\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:purge-database',
    description: 'Purge la base de données en conservant les articles et crée un Super Admin',
)]
class PurgeDatabaseCommand extends Command
{
    // Tables à conserver (articles et catégories)
    private const TABLES_TO_KEEP = [
        'blog_post',
        'blog_category',
        'cocoon_post',
        'doctrine_migration_versions', // Garder l'historique des migrations
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Cette commande purge la base de données tout en conservant les articles de blog et crée un utilisateur Super Admin.')
            ->addOption('admin-email', null, InputOption::VALUE_OPTIONAL, 'Email du Super Admin', 'admin@adn.com')
            ->addOption('admin-password', null, InputOption::VALUE_OPTIONAL, 'Mot de passe du Super Admin', 'SuperAdmin123!')
            ->addOption('admin-firstname', null, InputOption::VALUE_OPTIONAL, 'Prénom du Super Admin', 'Admin')
            ->addOption('admin-lastname', null, InputOption::VALUE_OPTIONAL, 'Nom du Super Admin', 'ADN')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Exécuter la purge sans confirmation')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher ce qui serait purgé sans exécuter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧹 Purge de la base de données pour déploiement en production');

        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        // Récupérer les infos du Super Admin
        $adminEmail = $input->getOption('admin-email');
        $adminPassword = $input->getOption('admin-password');
        $adminFirstName = $input->getOption('admin-firstname');
        $adminLastName = $input->getOption('admin-lastname');

        // Afficher la configuration
        $io->section('📋 Configuration');
        $io->table(
            ['Paramètre', 'Valeur'],
            [
                ['Email Super Admin', $adminEmail],
                ['Mot de passe', str_repeat('*', strlen($adminPassword))],
                ['Prénom', $adminFirstName],
                ['Nom', $adminLastName],
            ]
        );

        // Récupérer toutes les tables
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // Identifier les tables à purger
        $tablesToPurge = [];
        $tablesToKeep = [];
        
        foreach ($tables as $table) {
            if (in_array($table, self::TABLES_TO_KEEP)) {
                $tablesToKeep[] = $table;
            } else {
                $tablesToPurge[] = $table;
            }
        }

        // Afficher les tables
        $io->section('📊 Tables dans la base de données');
        
        $io->text('<fg=green>✓ Tables conservées :</>');
        foreach ($tablesToKeep as $table) {
            $count = $this->getRowCount($table);
            $io->text("  - {$table} ({$count} enregistrements)");
        }
        
        $io->newLine();
        $io->text('<fg=red>✗ Tables à purger :</>');
        foreach ($tablesToPurge as $table) {
            $count = $this->getRowCount($table);
            $io->text("  - {$table} ({$count} enregistrements)");
        }

        if ($dryRun) {
            $io->warning('Mode dry-run : aucune modification effectuée.');
            return Command::SUCCESS;
        }

        // Confirmation
        if (!$force) {
            $confirm = $io->confirm(
                '⚠️  ATTENTION : Cette action est IRRÉVERSIBLE. Voulez-vous continuer ?',
                false
            );
            
            if (!$confirm) {
                $io->info('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        $io->section('🔄 Exécution de la purge...');

        try {
            // Désactiver les vérifications de clés étrangères
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            $io->text('✓ Désactivation des contraintes de clés étrangères');

            // Purger les tables (dans l'ordre pour respecter les dépendances)
            $progressBar = $io->createProgressBar(count($tablesToPurge));
            $progressBar->start();

            foreach ($tablesToPurge as $table) {
                $this->connection->executeStatement("TRUNCATE TABLE `{$table}`");
                $progressBar->advance();
            }

            $progressBar->finish();
            $io->newLine(2);
            $io->text('✓ Tables purgées avec succès');

            // Mettre à jour les articles pour supprimer la référence à l'auteur
            $this->connection->executeStatement('UPDATE blog_post SET user_id = NULL');
            $io->text('✓ Références auteur des articles mises à NULL');

            // Réactiver les vérifications de clés étrangères
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $io->text('✓ Réactivation des contraintes de clés étrangères');

            // Créer le Super Admin
            $io->section('👑 Création du Super Admin');
            $this->createSuperAdmin($adminEmail, $adminPassword, $adminFirstName, $adminLastName, $io);

            $io->success([
                '✅ Purge de la base de données terminée avec succès !',
                '',
                '📝 Résumé :',
                "  - Tables purgées : " . count($tablesToPurge),
                "  - Tables conservées : " . count($tablesToKeep),
                "  - Super Admin créé : {$adminEmail}",
                '',
                '🔗 Vous pouvez vous connecter sur : /login',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            // Réactiver les contraintes en cas d'erreur
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            
            $io->error([
                'Erreur lors de la purge :',
                $e->getMessage(),
            ]);
            
            return Command::FAILURE;
        }
    }

    private function getRowCount(string $table): int
    {
        try {
            $result = $this->connection->executeQuery("SELECT COUNT(*) FROM `{$table}`");
            return (int) $result->fetchOne();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function createSuperAdmin(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        SymfonyStyle $io
    ): void {
        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setIsVerified(true);
        $user->setType(User::USER_TYPE_PRIVATE);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Persister l'utilisateur
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->text("✓ Super Admin créé : {$email}");
        $io->text("✓ Rôle : ROLE_SUPER_ADMIN");
    }
}

