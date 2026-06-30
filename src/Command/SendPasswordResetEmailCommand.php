<?php

namespace App\Command;

use App\Repository\User\UserRepository;
use App\Services\Mail\MailManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:send-password-reset-email',
    description: 'Envoie un email de réinitialisation de mot de passe à un utilisateur (génère un token + envoie le mail)',
)]
class SendPasswordResetEmailCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly MailManager $mailManager,
        private readonly RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "Email de l'utilisateur cible")
            ->addOption('dry-run', null, InputOption::VALUE_NONE, "N'envoie pas le mail, montre seulement ce qui serait fait")
            ->addOption('ttl-hours', null, InputOption::VALUE_OPTIONAL, "Durée de validité du token en heures", 72)
            ->addOption('base-url', null, InputOption::VALUE_OPTIONAL, "URL absolue de base (ex: https://adnfamilyoffice.fr) pour générer le lien", 'https://adnfamilyoffice.fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $dryRun = (bool) $input->getOption('dry-run');
        $ttlHours = max(1, (int) $input->getOption('ttl-hours'));
        $baseUrl  = rtrim((string) $input->getOption('base-url'), '/');

        $parsed = parse_url($baseUrl);
        if (!$parsed || empty($parsed['host'])) {
            $io->error("base-url invalide : $baseUrl");
            return Command::FAILURE;
        }
        $ctx = $this->router->getContext();
        $ctx->setScheme($parsed['scheme'] ?? 'https');
        $ctx->setHost($parsed['host']);
        if (!empty($parsed['port'])) {
            if (($parsed['scheme'] ?? 'https') === 'https') $ctx->setHttpsPort((int) $parsed['port']);
            else $ctx->setHttpPort((int) $parsed['port']);
        }
        if (!empty($parsed['path'])) {
            $ctx->setBaseUrl(rtrim($parsed['path'], '/'));
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("Utilisateur introuvable : $email");
            return Command::FAILURE;
        }

        $io->section('Cible');
        $io->definitionList(
            ['ID' => (string) $user->getId()],
            ['Email' => $user->getEmail()],
            ['Nom' => trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''))],
            ['Redirect MoneyPitch' => $user->isRedirectToMoneyPitch() ? 'OUI' : 'non'],
            ['Vérifié' => method_exists($user, 'isVerified') && $user->isVerified() ? 'OUI' : 'non'],
        );

        $token = bin2hex(random_bytes(32));

        if ($dryRun) {
            $previewUrl = $this->router->generate('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            $io->warning('DRY-RUN : aucun token persisté, aucun mail envoyé');
            $io->writeln('Token qui aurait été généré : ' . $token);
            $io->writeln('URL qui aurait été envoyée  : ' . $previewUrl);
            return Command::SUCCESS;
        }

        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable("+$ttlHours hours"));
        $this->em->flush();
        $io->writeln(sprintf('  Token persisté (longueur=%d, expire dans %dh)', strlen($token), $ttlHours));

        $resetUrl = $this->router->generate(
            'app_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $io->writeln('  URL reset : ' . $resetUrl);

        try {
            $ok = $this->mailManager->resetPassword($user->getEmail(), [
                'user' => $user,
                'resetUrl' => $resetUrl,
            ]);
            $io->success(sprintf('Email de réinitialisation envoyé à %s (return=%s)', $user->getEmail(), var_export($ok, true)));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $this->em->flush();
            $io->error(sprintf("Échec envoi : %s : %s\nFichier : %s:%d\nToken rollback effectué.",
                get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
            return Command::FAILURE;
        }
    }
}
