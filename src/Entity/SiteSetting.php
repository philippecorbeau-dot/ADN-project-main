<?php

namespace App\Entity;

use App\Repository\SiteSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteSettingRepository::class)]
#[ORM\Table(name: 'site_settings')]
class SiteSetting
{
    public const KEY_CLIENTS_ACCOMPAGNES = 'clients_accompagnes';
    public const KEY_ACTIFS_GERES = 'actifs_geres';
    public const KEY_ANNEES_EXPERTISE = 'annees_expertise';
    public const KEY_MONTANT_ACCESSIBLE = 'montant_accessible';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $settingKey;

    #[ORM\Column(type: 'string', length: 255)]
    private string $value;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $suffix = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): self
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function setSuffix(?string $suffix): self
    {
        $this->suffix = $suffix;
        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getFormattedValue(): string
    {
        $value = $this->value;
        if ($this->suffix) {
            $value .= $this->suffix;
        }
        return $value;
    }
}

