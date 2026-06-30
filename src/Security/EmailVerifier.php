<?php

namespace App\Security;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

readonly class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface            $mailer,
        private EntityManagerInterface     $entityManager,
        #[Autowire('%app.mail.sender_email%')]
        private string $senderEmail,
        #[Autowire('%app.mail.sender_name%')]
        private string $senderName,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendEmailConfirmation(string $verifyEmailRouteName, User $user, TemplatedEmail $email): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()]
        );

        $context = $email->getContext();
        $context['signedUrl'] = $signatureComponents->getSignedUrl();
        $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

        $email->context($context);

        // S'assurer que l'adresse d'expéditeur est définie
        if ($email->getFrom() === []) {
            $email->from(new Address($this->senderEmail, $this->senderName));
        }

        $this->mailer->send($email);
    }

    public function handleEmailConfirmation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());

        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
