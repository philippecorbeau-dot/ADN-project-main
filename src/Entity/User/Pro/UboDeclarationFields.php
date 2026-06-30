<?php

namespace App\Entity\User\Pro;

trait UboDeclarationFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Statut',
            'Message de refus'
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getPro()->getUser()->getLastname(),
            $this->getPro()->getUser()->getFirstname(),
            $this->getPro()->getUser()->getEmail(),
            $this->getMessageLabel(),
            $this->getMessage()
        ];
    }
}
