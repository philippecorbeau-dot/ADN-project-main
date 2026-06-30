<?php

namespace App\Entity\User\User;

trait KycDocumentFields
{
    public function getExportTitle()
    {
        return [
            '#',
            'Nom',
            'Prénom',
            'Email',
            'Id Mangopay',
            'Type de document',
            'Statut',
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getUser()->getLastname(),
            $this->getUser()->getFirstname(),
            $this->getUser()->getEmail(),
            null,
            $this->getTypeName(),
            $this->getStatusName(),
        ];
    }
}
