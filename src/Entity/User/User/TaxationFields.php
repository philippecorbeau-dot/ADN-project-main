<?php

namespace App\Entity\User\User;

trait TaxationFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Année',
            'Type',
            'Statut'
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getUser()->getLastname(),
            $this->getUser()->getFirstname(),
            $this->getYear(),
            $this->getUser()->getEmail(),
            $this->getTypeName(),
            $this->getStatusName(),
        ];
    }
}
