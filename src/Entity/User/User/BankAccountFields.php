<?php

namespace App\Entity\User\User;

trait BankAccountFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Propriétaire',
            'Iban',
            'Bic',
            'Statut',
        ];
    }


    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getUser() ? $this->getUser()->getLastname() : '',
            $this->getUser() ? $this->getUser()->getFirstname() : '',
            $this->getUser() ? $this->getUser()->getEmail() : '',
            $this->getOwner(),
            $this->getIban(),
            $this->getBic(),
            $this->getRibStatus(),
        ];
    }
}
