<?php

namespace App\Command;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:verify-user-password',
    description: 'Vérifie si un mot de passe en clair correspond au hash BDD d\'un utilisateur (debug login)',
)]
class VerifyUserPasswordCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "Email de l'utilisateur")
            ->addArgument('password', InputArgument::REQUIRED, "Mot de passe à tester");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $plain = (string) $input->getArgument('password');

        $users = $this->em->getRepository(User::class)->findBy(['email' => $email]);
        $io->writeln(sprintf("Utilisateurs trouvés pour email='%s' : %d", $email, count($users)));
        if (count($users) === 0) {
            $io->error("Aucun utilisateur. Vérifie l'orthographe exacte de l'email.");
            return Command::FAILURE;
        }
        if (count($users) > 1) {
            $io->warning("PLUSIEURS utilisateurs avec cet email ! Anomalie BDD.");
        }

        foreach ($users as $user) {
            $hash = $user->getPassword();
            $io->section(sprintf("User #%d  email=%s", $user->getId(), $user->getEmail()));
            $io->definitionList(
                ['ID'                  => (string) $user->getId()],
                ['Email'               => $user->getEmail()],
                ['Username'            => method_exists($user, 'getUsername') ? (string) $user->getUsername() : '(n/a)'],
                ['UserIdentifier'      => $user->getUserIdentifier()],
                ['Hash (prefix)'       => substr($hash, 0, 12) . '... (longueur ' . strlen($hash) . ')'],
                ['Roles'               => json_encode($user->getRoles())],
                ['isVerified'          => method_exists($user, 'isVerified')  && $user->isVerified()  ? 'OUI' : 'non'],
                ['isSuspended'         => method_exists($user, 'isSuspended') && $user->isSuspended() ? 'OUI' : 'non'],
                ['deleted_at'          => method_exists($user, 'getDeletedAt') && $user->getDeletedAt() ? $user->getDeletedAt()->format('Y-m-d H:i:s') : '(null)'],
                ['redirectToMP'        => $user->isRedirectToMoneyPitch() ? 'OUI' : 'non'],
            );

            // Test 1 : password_verify natif
            $ok1 = password_verify($plain, $hash);
            $io->writeln("Test 1 — password_verify natif PHP : " . ($ok1 ? '<info>✅ MATCH</info>' : '<error>❌ NO MATCH</error>'));

            // Test 2 : UserPasswordHasher (utilisé par Security)
            $ok2 = $this->passwordHasher->isPasswordValid($user, $plain);
            $io->writeln("Test 2 — UserPasswordHasher::isPasswordValid : " . ($ok2 ? '<info>✅ MATCH</info>' : '<error>❌ NO MATCH</error>'));

            // Test 3 : Hasher direct (algo configuré)
            $hasher = $this->hasherFactory->getPasswordHasher($user);
            $io->writeln("Test 3 — Hasher utilisé : " . get_class($hasher));
            $ok3 = $hasher->verify($hash, $plain);
            $io->writeln("Test 3 — Hasher::verify : " . ($ok3 ? '<info>✅ MATCH</info>' : '<error>❌ NO MATCH</error>'));
            $needsRehash = $hasher->needsRehash($hash);
            $io->writeln("Test 3 — needsRehash : " . ($needsRehash ? 'OUI (le hash devrait être migré)' : 'non'));

            // Re-hash et compare pour info
            $newHash = $hasher->hash($plain);
            $io->writeln("Test 4 — Re-hash du mdp (info) : " . substr($newHash, 0, 12) . '... (longueur ' . strlen($newHash) . ')');

            if ($ok2) {
                $io->success("✅ Cet utilisateur peut se connecter avec ce mot de passe (test Symfony Security).");
            } else {
                $io->error("❌ Le mot de passe ne matche PAS. Erreur de saisie utilisateur ou hash non synchronisé.");
            }
        }

        return Command::SUCCESS;
    }
}
