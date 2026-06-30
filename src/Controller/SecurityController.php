<?php

namespace App\Controller;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Services\Mail\MailManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Si il y a une erreur d'authentification, on ajoute un flash message approprié
        if ($error) {
            // Vérifier si c'est une erreur de compte suspendu ou autre erreur personnalisée
            $errorMessage = $error->getMessageKey();
            
            // Si c'est un message personnalisé du UserChecker (compte suspendu)
            if (str_contains($errorMessage, 'suspendu') || str_contains($errorMessage, 'contacter l\'administrateur')) {
                $this->addFlash('error', $errorMessage);
            } else {
                // Message générique pour les autres erreurs (mauvais identifiants, etc.)
                $this->addFlash('error', 'Identifiants incorrects. Veuillez vérifier votre email et mot de passe.');
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Vérifie si un email correspond à un utilisateur MoneyPitch
     * Utilisé pour adapter dynamiquement le formulaire de connexion
     */
    #[Route(path: '/api/check-user-type', name: 'api_check_user_type', methods: ['POST'])]
    public function checkUserType(Request $request, UserRepository $userRepository): JsonResponse
    {
        $email = $request->request->get('email');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'exists' => false,
                'type' => null,
            ]);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse([
                'exists' => false,
                'type' => null,
            ]);
        }

        // Vérifier si c'est un utilisateur MoneyPitch (et pas un admin)
        $isMoneyPitch = $user->shouldRedirectToMoneyPitch();

        return new JsonResponse([
            'exists' => true,
            'type' => $isMoneyPitch ? 'moneypitch' : 'adn',
        ]);
    }

    #[Route(path: '/forgot-password', name: 'app_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailManager $mailService
    ): Response {
        $email = $request->request->get('email');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Veuillez fournir une adresse email valide.'
            ], 400);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        // Pour des raisons de sécurité, on renvoie toujours un message de succès
        // même si l'utilisateur n'existe pas
        if (!$user) {
            return $this->json([
                'success' => true,
                'message' => 'Si cette adresse email existe dans notre système, vous recevrez un lien de réinitialisation.'
            ]);
        }

        // Générer un token sécurisé
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $em->flush();

        // Générer l'URL de réinitialisation
        $resetUrl = $this->generateUrl('app_reset_password', [
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // Envoyer l'email
        try {
            $mailService->resetPassword($user->getEmail(), [
                'user' => $user,
                'resetUrl' => $resetUrl
            ]);
        } catch (\Exception $e) {
            // Logger l'erreur mais ne pas la montrer à l'utilisateur
            // pour des raisons de sécurité
        }

        return $this->json([
            'success' => true,
            'message' => 'Si cette adresse email existe dans notre système, vous recevrez un lien de réinitialisation.'
        ]);
    }

    #[Route(path: '/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_login');
        }

        // Si c'est une requête POST, traiter la réinitialisation
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (!$newPassword || strlen($newPassword) < 8) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
                ], 400);
            }

            if ($newPassword !== $confirmPassword) {
                return $this->json([
                    'success' => false,
                    'message' => 'Les mots de passe ne correspondent pas.'
                ], 400);
            }

            // Hasher et enregistrer le nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès !');
            
            return $this->json([
                'success' => true,
                'redirect' => $this->generateUrl('app_login')
            ]);
        }

        // Afficher le formulaire de réinitialisation
        return $this->render('security/reset_password.html.twig', [
            'token' => $token
        ]);
    }
}
