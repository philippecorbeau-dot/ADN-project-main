<?php

namespace App\Services\User;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repository\User\SpamRepository;
use App\Entity\User\Spam;
use App\Entity\User\User;

/**
 * Handling spams
 */
class SpamHandler
{
    protected $em;
    protected $requestStack;
    protected $spamRepository;

    protected $spamCaptureActive;
    protected $spamFilterActive;

    public function __construct(RequestStack $requestStack, EntityManagerInterface $em, SpamRepository $spamRepository, ParameterBagInterface $params)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->spamRepository = $spamRepository;
    }

    /**
     * Generic block function
     */
    public function block(Request $request, User $user)
    {
        if ($this->spamCaptureActive) {
            $exists = $this->spamRepository->findBy(['email' => $user->getEmail()]);

            if (!$exists) {
                $spam = new Spam();
                $spam->setIp($request->getClientIp());
                $spam->setEmail($user->getEmail());
                $spam->setBlocked(true);

                $this->em->persist($spam);
                $this->em->flush();
            }

            // Temporary...
            die;
            throw new NotFoundHttpException('404 Not Found');
        }
    }

    public function isBlocked(): bool
    {
        if ($this->spamFilterActive) {
            $blocked = $this->spamRepository->findBy(['ip' => $this->requestStack->getCurrentRequest()->getClientIp()]);

            if ($blocked) {
                // Temporary...
                throw new NotFoundHttpException('404 Not Found');
                //die;
            }
        }

        return false;
    }
}
