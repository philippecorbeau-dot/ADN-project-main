<?php

namespace App\Command;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-clients',
    description: 'Importer des clients depuis un fichier Excel',
)]
class ImportClientsCommand extends Command
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
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin vers le fichier Excel')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Mot de passe par défaut pour tous les clients', 'Test123456!')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans créer les utilisateurs')
            ->setHelp('Cette commande importe les clients depuis un fichier Excel (colonnes: Civilité, Nom, Prénom, Email)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');
        $defaultPassword = $input->getOption('password');
        $dryRun = $input->getOption('dry-run');

        if (!file_exists($filePath)) {
            $io->error("Le fichier n'existe pas : $filePath");
            return Command::FAILURE;
        }

        $io->title('Import des clients depuis Excel');
        $io->info("Fichier : $filePath");
        $io->info("Mot de passe par défaut : $defaultPassword");
        
        if ($dryRun) {
            $io->warning('Mode simulation activé - aucun utilisateur ne sera créé');
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (\Exception $e) {
            $io->error("Erreur lors de la lecture du fichier : " . $e->getMessage());
            return Command::FAILURE;
        }

        // Trouver les colonnes
        $header = array_map('strtolower', array_map('trim', $rows[0] ?? []));
        $colCivilite = $this->findColumn($header, ['civilité', 'civilite', 'titre', 'gender']);
        $colNom = $this->findColumn($header, ['nom', 'lastname', 'last_name', 'name']);
        $colPrenom = $this->findColumn($header, ['prénom', 'prenom', 'firstname', 'first_name']);
        $colEmail = $this->findColumn($header, ['email', 'mail', 'e-mail', 'adresse mail', 'adresse email']);

        $io->section('Colonnes détectées');
        $io->listing([
            "Civilité : colonne " . ($colCivilite !== null ? chr(65 + $colCivilite) : 'NON TROUVÉE'),
            "Nom : colonne " . ($colNom !== null ? chr(65 + $colNom) : 'NON TROUVÉE'),
            "Prénom : colonne " . ($colPrenom !== null ? chr(65 + $colPrenom) : 'NON TROUVÉE'),
            "Email : colonne " . ($colEmail !== null ? chr(65 + $colEmail) : 'NON TROUVÉE'),
        ]);

        if ($colEmail === null) {
            $io->error("Colonne Email non trouvée dans le fichier");
            return Command::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $noEmail = 0;
        $alreadyExists = 0;
        $duplicateInFile = 0;
        
        // Garder trace des emails déjà traités dans cette session
        $processedEmails = [];

        $io->progressStart(count($rows) - 1);

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $io->progressAdvance();

            $email = strtolower(trim($row[$colEmail] ?? ''));
            
            // Ignorer les lignes sans email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $noEmail++;
                continue;
            }

            // Vérifier si déjà traité dans cette session (doublon dans le fichier)
            if (in_array($email, $processedEmails)) {
                $duplicateInFile++;
                continue;
            }
            $processedEmails[] = $email;

            $civilite = $colCivilite !== null ? trim($row[$colCivilite] ?? '') : '';
            $nom = $colNom !== null ? trim($row[$colNom] ?? '') : '';
            $prenom = $colPrenom !== null ? trim($row[$colPrenom] ?? '') : '';

            // Ignorer si pas de nom
            if (empty($nom)) {
                $skipped++;
                continue;
            }

            // Vérifier si l'utilisateur existe déjà en base
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $alreadyExists++;
                continue;
            }

            if (!$dryRun) {
                $user = new User();
                $user->setEmail($email);
                $user->setLastName(ucfirst(strtolower($nom)));
                $user->setFirstName($prenom ? ucfirst(strtolower($prenom)) : null);
                $user->setRoles([User::ROLE_USER]);
                $user->setIsVerified(true);
                $user->setType(User::USER_TYPE_PRIVATE);

                // Déterminer le genre
                $civiliteNorm = strtolower($civilite);
                if (in_array($civiliteNorm, ['monsieur', 'mr', 'm.', 'm', 'homme'])) {
                    $user->setGender(User::GENDER_MAN);
                } elseif (in_array($civiliteNorm, ['madame', 'mme', 'mademoiselle', 'mlle', 'femme'])) {
                    $user->setGender(User::GENDER_WOMAN);
                }

                // Hasher le mot de passe
                $hashedPassword = $this->passwordHasher->hashPassword($user, $defaultPassword);
                $user->setPassword($hashedPassword);

                $this->entityManager->persist($user);
                
                // Flush par batch de 20 pour éviter les problèmes mémoire
                if ($created % 20 === 0) {
                    $this->entityManager->flush();
                }
            }

            $created++;
        }

        $io->progressFinish();

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine(2);
        $io->success("Import terminé !");
        $io->table(
            ['Statistique', 'Nombre'],
            [
                ['✅ Clients créés', $created],
                ['⏭️  Sans email valide', $noEmail],
                ['⚠️  Déjà existants en base', $alreadyExists],
                ['🔄 Doublons dans le fichier', $duplicateInFile],
                ['❌ Ignorés (données manquantes)', $skipped],
            ]
        );

        if ($dryRun) {
            $io->note("C'était une simulation. Relancez sans --dry-run pour créer les utilisateurs.");
        }

        return Command::SUCCESS;
    }

    private function findColumn(array $header, array $possibleNames): ?int
    {
        foreach ($header as $index => $colName) {
            $colNameNorm = strtolower(trim($colName));
            foreach ($possibleNames as $name) {
                if ($colNameNorm === $name || str_contains($colNameNorm, $name)) {
                    return $index;
                }
            }
        }
        return null;
    }
}

