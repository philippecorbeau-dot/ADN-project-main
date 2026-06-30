<?php

namespace App\Controller;

use App\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour la gestion de la redirection vers MoneyPitch
 */
class MoneyPitchController extends AbstractController
{
    // URL de l'API de connexion MoneyPitch
    private const MONEYPITCH_LOGIN_URL = 'https://www.moneypitch.fr/pitch/login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * Page de transition pour la connexion MoneyPitch (avec credentials)
     * Reçoit email et password en POST depuis la page de login
     */
    #[Route('/moneypitch/login', name: 'app_moneypitch_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        if (!$email || !$password) {
            $this->addFlash('error', 'Veuillez saisir votre email et mot de passe MoneyPitch.');
            return $this->redirectToRoute('app_login');
        }

        // URLs de callback pour MoneyPitch
        $redirectUrl = $this->urlGenerator->generate(
            'app_moneypitch_error',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $returnUrl = $this->urlGenerator->generate(
            'app_login',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->render('moneypitch/transition.html.twig', [
            'moneypitch_url' => self::MONEYPITCH_LOGIN_URL,
            'user_email' => $email,
            'user_password' => $password,
            'redirect_url' => $redirectUrl,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Page de redirection vers MoneyPitch
     * Affiche un formulaire qui s'auto-soumet vers MoneyPitch avec l'email pré-rempli
     */
    #[Route('/moneypitch/redirect', name: 'app_moneypitch_redirect')]
    #[IsGranted('ROLE_USER')]
    public function redirectToMoneypitch(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Si l'utilisateur n'a pas le flag MoneyPitch ou est admin, rediriger vers le dashboard
        if (!$user->shouldRedirectToMoneyPitch()) {
            return $this->redirectToRoute('user_dashboard');
        }

        // URLs de callback pour MoneyPitch
        $redirectUrl = $this->urlGenerator->generate(
            'app_moneypitch_error',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $returnUrl = $this->urlGenerator->generate(
            'app_login',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->render('moneypitch/redirect.html.twig', [
            'moneypitch_url' => self::MONEYPITCH_LOGIN_URL,
            'user_email' => $user->getEmail(),
            'redirect_url' => $redirectUrl,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Page d'erreur pour les retours MoneyPitch en cas d'échec d'authentification
     */
    #[Route('/moneypitch/error', name: 'app_moneypitch_error')]
    public function error(): Response
    {
        // Récupérer les paramètres d'erreur de MoneyPitch
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $errorCode = $request->query->get('error', 'UNKNOWN');
        $errorMessage = $request->query->get('message', '');

        // Mapper les codes d'erreur MoneyPitch vers des messages lisibles
        $errorMessages = [
            'AUTH' => 'Identifiants incorrects. Veuillez vérifier votre email et mot de passe MoneyPitch.',
            'USER_ALREADY_ONLINE' => 'Vous êtes déjà connecté sur une autre session MoneyPitch.',
            'THIRD_PART' => 'Erreur de connexion avec le service tiers.',
            'OTHER' => 'Une erreur inattendue s\'est produite.',
            'internal' => 'Erreur interne du serveur MoneyPitch.',
            'UNKNOWN' => 'Une erreur inconnue s\'est produite.',
        ];

        $displayMessage = $errorMessages[$errorCode] ?? $errorMessages['UNKNOWN'];
        if ($errorMessage) {
            $displayMessage .= ' (' . urldecode($errorMessage) . ')';
        }

        $this->addFlash('error', $displayMessage);

        return $this->render('moneypitch/error.html.twig', [
            'error_code' => $errorCode,
            'error_message' => $displayMessage,
        ]);
    }
}

