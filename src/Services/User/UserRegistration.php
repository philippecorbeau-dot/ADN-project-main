<?php

namespace App\Services\User;

use App\Entity\User\Mailing;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class UserRegistration
{
    public function __construct(
        protected RegistrationManager    $registrationManager,
        protected EntityManagerInterface $em,
        protected SpamHandler            $spamHandler,
    ) {}

    public function registerUser(Request $request, FormInterface $form, User $user): void
    {
        $this->handleSpam($form, $request, $user);

        $user->setIp($request->getClientIp());

        $mailing = new Mailing();
        $mailing->setUser($user);
        // Enregistrer la préférence newsletter depuis le formulaire (par défaut true)
        $newsletter = (bool) ($form->get('newsletter')->getData() ?? true);
        $mailing->setNewsletter($newsletter);
        $this->em->persist($mailing);
        $this->em->persist($user);

        $this->registrationManager->setSource($user);


    }

    /** Honeypot anti-spam */
    private function handleSpam(FormInterface $form, Request $request, User $user): void
    {
        if ('' !== ($form->get('useremail')->getData() ?? '')) {
            $this->spamHandler->block($request, $user);
        }
    }
}
