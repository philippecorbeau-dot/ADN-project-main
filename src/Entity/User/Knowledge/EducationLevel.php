<?php

namespace App\Entity\User\Knowledge;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_knowledge_education_level')]
class EducationLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'level', type: 'string', length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'Baccalauréat',
        'Bac +2 (DUT, BTS)',
        'Bac +3 (Licence)',
        'Bac +4 (Maîtrise)',
        'Bac +5 (Master, Ingénieur)',
        'Bac +8 (Doctorat)',
        'Autre'
    ])]
    private ?string $level = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->level !== null;
    }
} 