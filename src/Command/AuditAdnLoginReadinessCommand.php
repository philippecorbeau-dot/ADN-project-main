<?php

namespace App\Command;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Audit des utilisateurs en mode ADN qui ne peuvent pas se connecter.
 *
 * Trois groupes diagnostiqués :
 *  1) Hash de mot de passe invalide (ex : utilisateurs importés depuis O2S/Harvest avec mot de passe aléatoire bin2hex)
 *  2) Hash bcrypt valide mais jamais modifié depuis création (> 60 jours) -> l'utilisateur peut avoir oublié son mot de passe
 *  3) Token de reset actif (mail de reset déjà envoyé, en attente d'action utilisateur)
 *
 * À lancer notamment AVANT toute bascule en masse MoneyPitch -> ADN pour
 * identifier les utilisateurs qui ne pourront pas se connecter sans intervention.
 */
#[AsCommand(
    name: 'app:audit-adn-login-readiness',
    description: "Audite les utilisateurs en mode ADN et identifie ceux qui ne peuvent pas se connecter",
)]
class AuditAdnLoginReadinessCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('include-moneypitch', null, InputOption::VALUE_NONE,
                "Inclut aussi les utilisateurs en mode MoneyPitch (utile avant un bulk-redirect vers ADN)")
            ->addOption('min-age-days', null, InputOption::VALUE_OPTIONAL,
                "Âge minimum (jours) pour considérer un compte bcrypt comme 'à risque oubli'", 60)
            ->addOption('format', null, InputOption::VALUE_OPTIONAL,
                "Format de sortie : table | csv", 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $includeMp  = (bool) $input->getOption('include-moneypitch');
        $minAgeDays = max(1, (int) $input->getOption('min-age-days'));
        $format     = strtolower((string) $input->getOption('format'));

        $conn = $this->em->getConnection();

        $whereScope = $includeMp ? '1=1' : 'redirect_to_moneypitch = 0';
        $stats = $conn->fetchAssociative("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN redirect_to_moneypitch = 1 THEN 1 ELSE 0 END) AS mp,
                SUM(CASE WHEN redirect_to_moneypitch = 0 THEN 1 ELSE 0 END) AS adn,
                SUM(CASE WHEN password LIKE :p1 OR password LIKE :p2 OR password LIKE :p3 THEN 1 ELSE 0 END) AS valid_hash,
                SUM(CASE WHEN NOT (password LIKE :p1 OR password LIKE :p2 OR password LIKE :p3) AND LENGTH(password) > 0 THEN 1 ELSE 0 END) AS invalid_hash
            FROM users_adn
            WHERE deleted_at IS NULL
        ", ['p1' => '$2y$%', 'p2' => '$2a$%', 'p3' => '$argon2%']);

        $io->section('Vue d\'ensemble');
        $io->definitionList(
            ['Périmètre du scan'        => $includeMp ? 'TOUS les users (MP + ADN)' : 'Mode ADN uniquement'],
            ['Total users actifs'       => $stats['total']],
            ['     en MoneyPitch'       => $stats['mp']],
            ['     en ADN'              => $stats['adn']],
            ['Avec hash bcrypt valide'  => $stats['valid_hash']],
            ['Avec hash INVALIDE'       => $stats['invalid_hash'] . ($stats['invalid_hash'] > 0 ? '  ⚠️' : '')],
        );

        // GROUPE 1 — hash invalide (login impossible sans reset)
        $g1 = $conn->fetchAllAssociative("
            SELECT id, email, first_name, last_name, redirect_to_moneypitch,
                LEFT(password, 7) AS pw_prefix, LENGTH(password) AS pw_len,
                (reset_token IS NOT NULL) AS has_reset_token,
                DATE_FORMAT(reset_token_expires_at, '%Y-%m-%d %H:%i') AS reset_exp,
                DATE_FORMAT(created_at, '%Y-%m-%d') AS created_at,
                o2s_contact_id
            FROM users_adn
            WHERE deleted_at IS NULL
              AND $whereScope
              AND NOT (password LIKE :p1 OR password LIKE :p2 OR password LIKE :p3)
              AND LENGTH(password) > 0
            ORDER BY redirect_to_moneypitch, created_at DESC
        ", ['p1' => '$2y$%', 'p2' => '$2a$%', 'p3' => '$argon2%']);

        $this->renderGroup($io, $format, '🚨 GROUPE 1 — Hash invalide (login ADN impossible)', $g1, true);

        // GROUPE 2 — bcrypt valide mais jamais modifié depuis +N jours
        $g2 = $conn->fetchAllAssociative("
            SELECT id, email, first_name, last_name, redirect_to_moneypitch,
                LEFT(password, 7) AS pw_prefix, LENGTH(password) AS pw_len,
                (reset_token IS NOT NULL) AS has_reset_token,
                DATE_FORMAT(reset_token_expires_at, '%Y-%m-%d %H:%i') AS reset_exp,
                DATE_FORMAT(created_at, '%Y-%m-%d') AS created_at,
                DATEDIFF(NOW(), created_at) AS days_old,
                o2s_contact_id
            FROM users_adn
            WHERE deleted_at IS NULL
              AND $whereScope
              AND (password LIKE :p1 OR password LIKE :p2 OR password LIKE :p3)
              AND created_at = updated_at
              AND DATEDIFF(NOW(), created_at) > :age
            ORDER BY days_old DESC
        ", ['p1' => '$2y$%', 'p2' => '$2a$%', 'p3' => '$argon2%', 'age' => $minAgeDays]);

        $this->renderGroup($io, $format, sprintf("⚠️  GROUPE 2 — Bcrypt valide mais jamais reset (>%dj) — risque oubli mdp", $minAgeDays), $g2);

        // GROUPE 3 — reset_token actif
        $g3 = $conn->fetchAllAssociative("
            SELECT id, email, first_name, last_name, redirect_to_moneypitch,
                DATE_FORMAT(reset_token_expires_at, '%Y-%m-%d %H:%i') AS reset_exp,
                (reset_token_expires_at < NOW()) AS is_expired
            FROM users_adn
            WHERE deleted_at IS NULL
              AND $whereScope
              AND reset_token IS NOT NULL
            ORDER BY reset_token_expires_at DESC
        ");
        $this->renderGroup($io, $format, "ℹ️  GROUPE 3 — Token de reset actif (mail en attente d'action utilisateur)", $g3);

        $io->newLine();
        $io->writeln('<comment>Recommandations :</comment>');
        $io->writeln('  • Groupe 1 : avant toute bascule MoneyPitch→ADN, déclencher un mail de reset via <info>app:send-password-reset-email &lt;email&gt;</info>');
        $io->writeln('  • Groupe 2 : surveiller les retours utilisateur (oubli mdp courant), proposer un reset à la demande');
        $io->writeln('  • Groupe 3 : tokens en cours d\'utilisation, ne pas régénérer si non nécessaire');
        $io->writeln('  • Vérifier que MAILER_DSN utilise <info>mailjet+api://</info> (le SMTP est bloqué chez OVH mutualisé)');

        return Command::SUCCESS;
    }

    /** @param array<array<string,mixed>> $rows */
    private function renderGroup(SymfonyStyle $io, string $format, string $title, array $rows, bool $isCritical = false): void
    {
        $count = count($rows);
        $io->section(sprintf("%s — %d utilisateur(s)", $title, $count));

        if ($count === 0) {
            $io->writeln('  <info>Aucun utilisateur dans cette catégorie ✓</info>');
            return;
        }

        if ($format === 'csv') {
            $cols = array_keys($rows[0]);
            $io->writeln(implode(';', $cols));
            foreach ($rows as $r) {
                $io->writeln(implode(';', array_map(fn ($v) => (string) ($v ?? ''), $r)));
            }
            return;
        }

        $cols = array_keys($rows[0]);
        $table = new Table($io);
        $table->setHeaders($cols);
        foreach ($rows as $r) {
            $table->addRow(array_map(fn ($v) => is_bool($v) ? ($v ? 'OUI' : 'non') : (string) ($v ?? '-'), $r));
        }
        $table->render();
    }
}
