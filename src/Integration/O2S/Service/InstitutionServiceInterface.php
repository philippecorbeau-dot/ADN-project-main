<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\DTO\Institution\InstitutionDTO;

interface InstitutionServiceInterface
{
    public function getInstitution(string $institutionId): InstitutionDTO;

    /**
     * @return InstitutionDTO[]
     */
    public function getAllInstitutions(): array;

    /**
     * @return array<string, string> Map of institutionId => label
     */
    public function getInstitutionsMap(): array;
}

