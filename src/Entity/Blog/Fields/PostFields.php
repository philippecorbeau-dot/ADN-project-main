<?php

namespace App\Entity\Blog\Fields;

trait PostFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Titre',
            'Posté le',
            'Auteur'
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getTitle(),
            $this->getPublicationDateStart()->format('Y-m-d'),
            $this->getUser()->getEmail(),
        ];
    }
}
