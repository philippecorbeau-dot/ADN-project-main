<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Institution;

/**
 * DTO for O2S Institution (financial establishment).
 */
final class InstitutionDTO
{
    public function __construct(
        public readonly string $institutionId,
        public readonly ?string $label,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            institutionId: $data['institutionId'] ?? '',
            label: $data['label'] ?? null,
        );
    }

    public function getInstitutionId(): string
    {
        return $this->institutionId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'institutionId' => $this->institutionId,
            'label' => $this->label,
        ];
    }
}

