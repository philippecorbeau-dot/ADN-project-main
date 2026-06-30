<?php

namespace App\Entity\Blog\Fields;

trait CategoryFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Description',
        ];
    }

    public function getExportFields()
    {
        return [
            $this->getId(),
            $this->getName(),
            $this->getDescription(),
        ];
    }
}
