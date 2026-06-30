<?php

namespace App\Controller;

use App\Entity\User\User;
use App\Form\Front\User\RegistrationFormType;
use App\Repository\User\UserRepository;
use App\Security\EmailVerifier;
use App\Services\User\RegistrationManager;
use App\Services\User\UserRegistration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier       $emailVerifier,
        private readonly RegistrationManager $registrationManager,
        private EntityManagerInterface $em,
    )
    {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(path: '/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, UserRegistration $userRegistration, RegistrationManager $registrationManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $userRegistration->registerUser($request, $form, $user);
            $entityManager->flush();
            
            // generate a signed url and email it to the user
            $this->registrationManager->sendConfirmationEmail($user);
            
            // Notifier l'administrateur de la nouvelle inscription pour activation MoneyPitch
            $this->registrationManager->notifyAdminOfNewRegistration($user);

            $this->addFlash('success', 'Consultez vos e-mails pour confirmer votre compte.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(path: '/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }
/*        //$this->registrationManager->notifyAdminsOfProRegistration($user);*/
        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Votre adresse mail a été vérifié, vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }
}
