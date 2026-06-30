<?php

namespace App\Services\User;

use App\Entity\User\Marketing;
use App\Entity\User\User;
use App\Services\Mail\MailManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RegistrationManager
{
    private $Twig;

    public function __construct(
        protected RequestStack $requestStack,
        protected EntityManagerInterface $em,
        protected TranslatorInterface $translator,
        protected MailManager $mailer,
        protected Environment $twig,
        protected VerifyEmailHelperInterface $verifyEmailHelper,
        protected UrlGeneratorInterface $urlGenerator,
    )
    {}
    public function setSource(User $user): void
    {
        $cookies = $this->getRequest()->cookies;

        if ($cookies->has('user_source')) {
            $user->setSource($cookies->get('user_source'));
        }

        if ($cookies->has('utm_source')) {
            $marketing = new Marketing();
            $marketing->setUtmSource($cookies->get('utm_source'));
            $marketing->setUtmMedium($cookies->get('utm_medium'));
            $marketing->setUtmCampaign((string) $cookies->get('utm_campaign'));
            $marketing->setUtmContent((string) $cookies->get('utm_content'));
            $user->setMarketing($marketing);
        }

        $this->em->persist($user);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendConfirmationEmail(User $user): bool
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        return $this->mailer->send(
            $this->translator->trans('Félicitations, vous faites partie de la communauté ADN !'),
            [$user->getEmail()],
            [
                'html' => $this->twig->render('emails/registration.html.twig', [
                    'user'                 => $user,
                    'signedUrl'            => $signature->getSignedUrl(),
                    'expiresAt'            => $signature->getExpiresAt(),
                    'expiresAtMessageKey'  => $signature->getExpirationMessageKey(),
                    'expiresAtMessageData' => $signature->getExpirationMessageData(),
                    'title'                => 'Bienvenue chez ADN !',
                ]),
                'text' => $this->twig->render('registration/registration.txt.twig', [
                    'user'       => $user,
                    'signedUrl'  => $signature->getSignedUrl(),
                ]),
            ],
            files: [],
            data: ['type' => 'registration_confirmation']
        );
    }


    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @deprecated Utiliser notifyAdminOfNewRegistration() à la place
     */
    public function notifyAdminsOfProRegistration($user): bool
    {
        $subject = $this->translator->trans("Un compte pro vient de s'inscrire !");

        return $this->mailer->send(
            $subject,
            $this->mailer->getAdminTeamAddress(),
            [
                'html' => $this->twig->render('emails/team/new-pro.html.twig', [
                    'user' => $user,
                    'title' => $subject
                ]),
                'text' => $this->twig->render('emails/team/new-pro.txt.twig', [
                    'user' => $user,
                    'title' => $subject
                ]),
            ]
        );
    }

    /**
     * Notifie l'administrateur de chaque nouvelle inscription
     * pour qu'il puisse créer/activer le compte MoneyPitch correspondant
     *
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function notifyAdminOfNewRegistration(User $user): bool
    {
        $subject = $this->translator->trans("🆕 Nouvelle inscription : {name}", [
            '{name}' => $user->getFullName()
        ]);

        // Générer l'URL vers la fiche utilisateur dans le back-office
        $adminUrl = $this->urlGenerator->generate(
            'admin_modern_user_view',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->mailer->send(
            $subject,
            $this->mailer->getAdminTeamAddress(),
            [
                'html' => $this->twig->render('emails/admin_new_registration.html.twig', [
                    'user' => $user,
                    'admin_url' => $adminUrl,
                ]),
                'text' => $this->twig->render('emails/admin_new_registration.txt.twig', [
                    'user' => $user,
                    'admin_url' => $adminUrl,
                ]),
            ],
            files: [],
            data: ['type' => 'admin_new_registration']
        );
    }

    private function getRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}