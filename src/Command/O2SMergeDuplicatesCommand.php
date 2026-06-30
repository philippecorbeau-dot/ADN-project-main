<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'o2s:merge-duplicates',
    description: 'Détecte et fusionne les utilisateurs O2S en doublon avec les comptes manuels existants',
)]
class O2SMergeDuplicatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans effectuer de modifications')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Exécuter la fusion sans confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('🔍 Détection et fusion des doublons O2S');

        if ($dryRun) {
            $io->warning('Mode simulation activé — aucune modification ne sera effectuée');
        }

        // ====== ÉTAPE 1 : Détecter les doublons ======
        $io->section('Étape 1 : Détection des doublons');

        $duplicates = $this->findDuplicates();

        if (empty($duplicates)) {
            $io->success('Aucun doublon détecté — la base est propre !');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Trouvé <info>%d</info> paires de doublons à fusionner', count($duplicates)));
        $io->newLine();

        // Afficher le tableau des doublons
        $tableRows = [];
        foreach ($duplicates as $dup) {
            $tableRows[] = [
                $dup['manual_id'],
                $dup['manual_email'],
                $dup['manual_name'],
                $dup['o2s_id'],
                substr($dup['o2s_contact_id'], 0, 12) . '...',
                $dup['product_count'],
            ];
        }

        $io->table(
            ['ID Manuel', 'Email réel', 'Nom', 'ID O2S', 'Contact O2S', 'Produits'],
            $tableRows
        );

        // Résumé
        $totalProducts = array_sum(array_column($duplicates, 'product_count'));
        $io->text([
            sprintf('📊 <info>%d</info> comptes manuels à enrichir', count($duplicates)),
            sprintf('📦 <info>%d</info> produits à transférer', $totalProducts),
            sprintf('🗑️  <info>%d</info> comptes O2S placeholder à supprimer', count($duplicates)),
        ]);

        if ($dryRun) {
            $io->note('Mode --dry-run : aucune modification effectuée.');
            return Command::SUCCESS;
        }

        // Confirmation
        if (!$force && !$io->confirm('Voulez-vous fusionner ces doublons ?', false)) {
            $io->warning('Opération annulée.');
            return Command::SUCCESS;
        }

        // ====== ÉTAPE 2 : Fusionner les doublons ======
        $io->section('Étape 2 : Fusion des doublons');

        $merged = 0;
        $productsTransferred = 0;
        $errors = [];

        $progressBar = $io->createProgressBar(count($duplicates));
        $progressBar->start();

        foreach ($duplicates as $dup) {
            try {
                $result = $this->mergePair(
                    (int) $dup['manual_id'],
                    (int) $dup['o2s_id'],
                    $dup['o2s_contact_id']
                );
                $merged++;
                $productsTransferred += $result['products_transferred'];
            } catch (\Throwable $e) {
                $errors[] = sprintf(
                    'Erreur fusion [%d] ← [%d] : %s',
                    $dup['manual_id'],
                    $dup['o2s_id'],
                    $e->getMessage()
                );
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        // ====== RÉSULTATS ======
        $io->section('Résultats');
        $io->text([
            sprintf('✅ <info>%d</info> comptes fusionnés avec succès', $merged),
            sprintf('📦 <info>%d</info> produits transférés', $productsTransferred),
        ]);

        if (!empty($errors)) {
            $io->warning(sprintf('%d erreurs rencontrées :', count($errors)));
            foreach ($errors as $error) {
                $io->text('  ❌ ' . $error);
            }
        }

        // Vérification finale
        $remainingDuplicates = $this->findDuplicates();
        if (empty($remainingDuplicates)) {
            $io->success('🎉 Tous les doublons ont été fusionnés ! Base de données propre.');
        } else {
            $io->warning(sprintf('Il reste %d doublons non fusionnés.', count($remainingDuplicates)));
        }

        // Stats finales
        $totalUsers = $this->entityManager->getRepository(User::class)->count([]);
        $linkedUsers = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.o2sContactId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $io->newLine();
        $io->text([
            sprintf('👥 Total utilisateurs : <info>%d</info>', $totalUsers),
            sprintf('🔗 Liés à O2S : <info>%s</info>', $linkedUsers),
        ]);

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Trouve les paires de doublons : utilisateur manuel ↔ utilisateur O2S placeholder.
     * Matching sur nom + prénom (insensible à la casse et aux espaces).
     *
     * @return array<array{manual_id: int, manual_email: string, manual_name: string, o2s_id: int, o2s_contact_id: string, product_count: int}>
     */
    private function findDuplicates(): array
    {
        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT 
                a.id as manual_id, 
                a.email as manual_email, 
                CONCAT(a.first_name, ' ', a.last_name) as manual_name,
                b.id as o2s_id, 
                b.o2s_contact_id,
                (SELECT COUNT(*) FROM product_accounts pa WHERE pa.user_id = b.id) as product_count
            FROM users_adn a
            JOIN users_adn b ON (
                LOWER(REPLACE(REPLACE(TRIM(a.last_name), ' ', ''), '-', '')) 
                = LOWER(REPLACE(REPLACE(TRIM(b.last_name), ' ', ''), '-', ''))
                AND LOWER(REPLACE(REPLACE(TRIM(a.first_name), ' ', ''), '-', '')) 
                = LOWER(REPLACE(REPLACE(TRIM(b.first_name), ' ', ''), '-', ''))
            )
            WHERE a.o2s_contact_id IS NULL
              AND b.o2s_contact_id IS NOT NULL
              AND a.id != b.id
              AND b.email LIKE 'o2s_%@placeholder.local'
            ORDER BY a.last_name, a.first_name
        ";

        return $conn->fetchAllAssociative($sql);
    }

    /**
     * Fusionne un utilisateur O2S placeholder dans un utilisateur manuel.
     * 
     * 1. Transfère o2s_contact_id et o2s_synced_at vers le compte manuel
     * 2. Ré-assigne tous les product_accounts du compte O2S → compte manuel
     * 3. Supprime le compte O2S placeholder
     *
     * @return array{products_transferred: int}
     */
    private function mergePair(int $manualUserId, int $o2sUserId, string $o2sContactId): array
    {
        $conn = $this->entityManager->getConnection();

        $conn->beginTransaction();

        try {
            // 1. Transférer le o2s_contact_id vers le compte manuel (seulement s'il n'en a pas déjà un)
            $existingO2sId = $conn->fetchOne('SELECT o2s_contact_id FROM users_adn WHERE id = ?', [$manualUserId]);
            if (!$existingO2sId) {
                $conn->executeStatement(
                    'UPDATE users_adn SET o2s_contact_id = ?, o2s_synced_at = NOW() WHERE id = ?',
                    [$o2sContactId, $manualUserId]
                );
            }

            // 2. Transférer les product_accounts
            $productsTransferred = $conn->executeStatement(
                'UPDATE product_accounts SET user_id = ? WHERE user_id = ?',
                [$manualUserId, $o2sUserId]
            );

            // 3. Vérifier qu'il n'y a plus de données liées au compte O2S
            $remainingProducts = $conn->fetchOne(
                'SELECT COUNT(*) FROM product_accounts WHERE user_id = ?',
                [$o2sUserId]
            );

            if ((int) $remainingProducts > 0) {
                throw new \RuntimeException("Il reste $remainingProducts produits liés au compte O2S #$o2sUserId après transfert");
            }

            // 4. Supprimer le compte O2S placeholder
            $conn->executeStatement('DELETE FROM users_adn WHERE id = ?', [$o2sUserId]);

            $conn->commit();

            return ['products_transferred' => $productsTransferred];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}

