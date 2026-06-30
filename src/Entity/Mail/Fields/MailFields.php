<?php

namespace App\Entity\Mail\Fields;

trait MailFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Sujet',
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getUser()->getLastname(),
            $this->getUser()->getFirstname(),
            $this->getUser()->getEmail(),
            $this->getSubject(),
        ];
    }
}
