<?php

namespace App\Entity\User\User;

trait  WantFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Newsletter',
            'Infopack',
            'Doc blog',
            'Dernier infopack'
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getLastname(),
            $this->getFirstname(),
            $this->getEmail(),
            $this->getWantNl() ? 'Oui' : 'Non',
            $this->getWantInfopack() ? 'Oui' : 'Non',
            $this->getWantDocBlog() ? 'Oui' : 'Non',
            $this->getWantSyntheticFile(),
        ];
    }
}
