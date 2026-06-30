<?php

namespace App\Entity\User\User;

trait ControlFields
{
    public function getExportTitle()
    {
        return [
            'Id',
            'Nom',
            'Prénom',
            'Email',
            'Origine',
            'Types',
        ];
    }

    public function getExportFields()
    {
        $types = '';

        foreach ($this->getType() as $key => $type) {
            if($key !== 0) {
                $types .= ' / ';
            }

            $types .= $type;
        }

        return [
            $this->getId(),
            $this->getLastname(),
            $this->getFirstname(),
            $this->getEmail(),
            $this->getOrigin(),
            $types,
        ];
    }
}
