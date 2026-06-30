<?php

namespace App\Command;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Crée un utilisateur Super Admin avec mot de passe haché',
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Cette commande crée un utilisateur Super Admin avec le rôle ROLE_SUPER_ADMIN')
            ->addArgument('email', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('password', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Mot de passe de l\'utilisateur')
            ->addArgument('firstName', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Prénom de l\'utilisateur')
            ->addArgument('lastName', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Nom de l\'utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création d\'un utilisateur Super Admin');

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getArgument('firstName');
        $lastName = $input->getArgument('lastName');

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if ($existingUser) {
            $io->warning("Un utilisateur avec l'email {$email} existe déjà.");
            
            if (!$existingUser->hasRole('ROLE_SUPER_ADMIN')) {
                $io->info("Ajout du rôle ROLE_SUPER_ADMIN à l'utilisateur existant...");
                $existingUser->addRole('ROLE_SUPER_ADMIN');
                $this->entityManager->flush();
                $io->success("✅ Rôle ROLE_SUPER_ADMIN ajouté à l'utilisateur {$email}");
            } else {
                $io->info("L'utilisateur {$email} a déjà le rôle ROLE_SUPER_ADMIN");
            }
            
            return Command::SUCCESS;
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setIsVerified(true);
        $user->setType(User::USER_TYPE_PRIVATE); // Par défaut, peut être modifié après

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Valider l'entité
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error('Erreurs de validation :');
            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }
            return Command::FAILURE;
        }

        // Persister l'utilisateur
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success([
            "✅ Utilisateur Super Admin créé avec succès !",
            "📧 Email: {$email}",
            "👤 Nom: {$firstName} {$lastName}",
            "🔑 Mot de passe: [masqué]",
            "👑 Rôle: ROLE_SUPER_ADMIN",
            "",
            "🔗 Vous pouvez maintenant vous connecter sur : http://127.0.0.1:8000/login"
        ]);

        return Command::SUCCESS;
    }
} 