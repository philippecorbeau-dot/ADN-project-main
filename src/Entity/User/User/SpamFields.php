<?php

namespace App\Entity\User\User;

trait SpamFields
{
    public function getExportTitle(): array
    {
        return [
            'Id',
            'Crée le',
            'Email',
            'Bloqué',
        ];
    }

    public function getExportFields(): array
    {
        return [
            $this->getId(),
            $this->getCreatedAt()->format('d/m/Y'),
            $this->getEmail(),
            $this->getBlocked() ? 'Oui' : 'Non',
        ];
    }
}
