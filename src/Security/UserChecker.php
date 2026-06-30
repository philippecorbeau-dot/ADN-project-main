<?php

namespace App\Security;

use App\Entity\User\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifie l'état du compte utilisateur lors de la connexion
 * Bloque les utilisateurs suspendus avec un message approprié
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Vérifications avant l'authentification (vérification du mot de passe)
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Vérifier si l'utilisateur est suspendu
        if ($user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Veuillez contacter l\'administrateur du site pour plus d\'informations.'
            );
        }

        // Vérifier si le compte est soft-deleted
        if ($user->getDeletedAt() !== null) {
            throw new CustomUserMessageAccountStatusException(
                'Ce compte n\'existe plus.'
            );
        }
    }

    /**
     * Vérifications après l'authentification réussie
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Double vérification après authentification
        if ($user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Veuillez contacter l\'administrateur du site pour plus d\'informations.'
            );
        }
    }
}

