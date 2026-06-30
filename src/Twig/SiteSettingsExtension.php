<?php

namespace App\Twig;

use App\Repository\SiteSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SiteSettingsExtension extends AbstractExtension
{
    private array $cache = [];
    private bool $loaded = false;

    public function __construct(
        private SiteSettingRepository $repository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_setting', [$this, 'getSetting']),
            new TwigFunction('clients_accompagnes', [$this, 'getClientsAccompagnes']),
            new TwigFunction('actifs_geres', [$this, 'getActifsGeres']),
            new TwigFunction('annees_expertise', [$this, 'getAnneesExpertise']),
            new TwigFunction('montant_accessible', [$this, 'getMontantAccessible']),
        ];
    }

    private function loadSettings(): void
    {
        if (!$this->loaded) {
            $this->cache = $this->repository->getAllSettings();
            $this->loaded = true;
        }
    }

    public function getSetting(string $key, string $default = ''): string
    {
        $this->loadSettings();
        return $this->cache[$key] ?? $default;
    }

    public function getClientsAccompagnes(): string
    {
        return $this->getSetting('clients_accompagnes', '170');
    }

    public function getActifsGeres(): string
    {
        return $this->getSetting('actifs_geres', '70');
    }

    public function getAnneesExpertise(): string
    {
        return $this->getSetting('annees_expertise', '15');
    }

    public function getMontantAccessible(): string
    {
        return $this->getSetting('montant_accessible', '10 000');
    }
}

