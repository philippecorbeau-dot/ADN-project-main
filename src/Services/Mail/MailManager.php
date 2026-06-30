<?php

namespace App\Services\Mail;

use App\Entity\Mail\Mail;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Services\Mail\Type\AdminUserIdentityRefused;
use App\Services\Mail\Type\AdminUserWant;
use App\Services\Mail\Type\BankwireSuccess;
use App\Services\Mail\Type\CallForFunds;
use App\Services\Mail\Type\ContactForm;
use App\Services\Mail\Type\Exception;
use App\Services\Mail\Type\InvestmentRequest;
use App\Services\Mail\Type\InvestmentSigned;
use App\Services\Mail\Type\KycDocumentNotification;
use App\Services\Mail\Type\KycOutdated;
use App\Services\Mail\Type\KycStatusReport;
use App\Services\Mail\Type\KycValidation;
use App\Services\Mail\Type\LifeInsurance;
use App\Services\Mail\Type\LogginThrottling;
use App\Services\Mail\Type\NonValidatedUser;
use App\Services\Mail\Type\OldUser;
use App\Services\Mail\Type\ProjectContact;
use App\Services\Mail\Type\ProjectRelaunch;
use App\Services\Mail\Type\ProjectReport;
use App\Services\Mail\Type\ResetPassword;
use App\Services\Mail\Type\RibValidated;
use App\Services\Mail\Type\ScpiSimulation;
use App\Services\Mail\Type\ScpiSubscription;
use App\Services\Mail\Type\TaxationMessage;
use App\Services\Mail\Type\TwoFactorAuthentication;
use App\Services\Mail\Type\UnreceivedWire;
use App\Services\Mail\Type\UserIdentified;
use App\Services\Mail\Type\UserIdentityRefused;
use App\Services\Mail\Type\UserNotify;
use App\Services\Mail\Type\VefaContact;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

class MailManager
{
    use AdminUserIdentityRefused, AdminUserWant, ContactForm, ProjectContact, VefaContact, KycStatusReport, KycValidation, NonValidatedUser,
        BankwireSuccess, UnreceivedWire, OldUser, UserIdentityRefused, UserIdentified, ResetPassword,
        InvestmentRequest, InvestmentSigned, ProjectReport, Exception, TwoFactorAuthentication, RibValidated,
        KycOutdated, LogginThrottling, ProjectRelaunch, UserNotify, ScpiSimulation, ScpiSubscription, TaxationMessage, CallForFunds, LifeInsurance,
        KycDocumentNotification;
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly RequestStack $requestStack,

        #[Autowire('%kernel.project_dir%/var/mails')]
        private readonly string $mailDirectory,
        
        #[Autowire('%app.mail.sender_email%')]
        private readonly string $senderEmail,
        
        #[Autowire('%app.mail.sender_name%')]
        private readonly string $senderName,
        
        #[Autowire('%app.mail.admin_team_email%')]
        private readonly string $adminTeamEmail,
    ) {
        // Rétrocompatibilité avec les anciens traits d'email qui attendent une propriété "templating"
        // On expose simplement l'environnement Twig moderne avec ce nom.
        $this->templating = $this->twig;

        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->mailDirectory)) {
            $filesystem->mkdir($this->mailDirectory);
        }
    }

    public function send(string $subject, array $to, array $body, array $files = [], array $data = []): bool
    {
        $to = $this->filterRecipients($to);
        if (empty($to)) {
            $this->logger->warning('Email skipped: no valid recipient', ['subject' => $subject]);
            return false;
        }

        $email = (new Email())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(...$to)
            ->subject($subject)
            ->text($body['text'] ?? '')
            ->html($body['html'] ?? '');

        foreach ($files as $file) {
            $email->attachFromPath($file);
        }

        // Phase 1 : envoi effectif. Si ça échoue, on remonte l'erreur (retour false + log critical).
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->critical('Email send failed: ' . $e->getMessage(), [
                'subject' => $subject,
                'to' => $to,
                'exception' => $e,
            ]);
            return false;
        }

        // Phase 2 : persistance en BDD/disque. Une erreur ici NE DOIT PAS marquer
        // l'envoi comme échoué (le mail est déjà parti) — juste un warning.
        try {
            $this->saveMail($subject, $to, $body, $data);
        } catch (\Throwable $e) {
            $this->logger->warning('Email persisted incompletely (mail was sent): ' . $e->getMessage(), [
                'subject' => $subject,
                'to' => $to,
            ]);
        }

        return true;
    }

    private function saveMail(string $subject, array $to, array $body, array $data = []): void
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route') ?? null;
        $html  = $this->minifyHtml($body['html'] ?? '');

        $created = [];
        foreach ($to as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            $mail = new Mail();
            $mail->setUser($user)
                ->setSubject($subject)
                ->setSentTo($email)
                ->setCreatedAt(new \DateTime())
                ->setName($route)
                ->setToken(Uuid::v4());

            $this->em->persist($mail);
            $created[] = $mail;
        }

        // Flush AVANT d'utiliser getId() (sinon l'ID est null).
        $this->em->flush();

        foreach ($created as $mail) {
            $path = rtrim($this->mailDirectory, '/\\') . DIRECTORY_SEPARATOR . $mail->getId() . '.html';
            @file_put_contents($path, $html);
        }
    }

    private function minifyHtml(string $html): string
    {
        $search = [
            '/\>[^\S ]+/s',
            '/[^\S ]+\</s',
            '/(\s)+/s',
            '/<!--(.|\s)*?-->/'
        ];
        $replace = ['>', '<', '\\1', ''];
        return trim(preg_replace($search, $replace, $html));
    }

    private function filterRecipients(array $emails): array
    {
        $valid = [];

        foreach ($emails as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user && !in_array('ROLE_API_EXTERNAL', $user->getRoles(), true)) {
                $valid[] = $email;
            }
        }

        return $valid;
    }

    /**
     * Retourne l'adresse email de l'expéditeur
     */
    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    /**
     * Retourne le nom de l'expéditeur
     */
    public function getSenderName(): string
    {
        return $this->senderName;
    }

    /**
     * Retourne l'adresse email de l'équipe admin (pour compatibilité avec les anciens traits)
     */
    public function getAdminTeamAddress(): array
    {
        return [$this->adminTeamEmail];
    }

    /**
     * Constante pour compatibilité ascendante (dépréciée, utiliser getAdminTeamAddress())
     * @deprecated Utiliser getAdminTeamAddress() à la place
     */
    public const ADMIN_TEAM_ADDRESS = null; // Ne plus utiliser directement
}