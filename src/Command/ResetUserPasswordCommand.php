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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-user-password',
    description: 'Réinitialise le mot de passe d\'un utilisateur à partir de son email',
)]
class ResetUserPasswordCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Nouveau mot de passe (fort)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $plainPassword = (string) $input->getArgument('password');

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("Aucun utilisateur trouvé pour l'email: {$email}");
            return Command::FAILURE;
        }

        $hash = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hash);
        $this->entityManager->flush();

        $io->success("Mot de passe réinitialisé pour {$email}");
        return Command::SUCCESS;
    }
}



