<?php

namespace App\Entity\Mail\Fields;

trait ContactFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Sujet',
            'Crée le',
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getLastname(),
            $this->getFirstname(),
            $this->getEmail(),
            $this->getSubjectName(),
            $this->getCreatedAt()->format('d/m/Y'),
        ];
    }
}
