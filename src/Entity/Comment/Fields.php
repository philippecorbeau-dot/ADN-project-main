<?php

namespace App\Entity\Comment;

trait Fields
{
    public function getExportTitle(): array
    {
        return [
            'Id',
            'Email',
            'Message',
            'Modèle'
        ];
    }

    public function getExportFields(): array
    {
        return [
            $this->getId(),
            $this->getEmail(),
            $this->getContent(),
            $this->getModel(),
        ];
    }
}
