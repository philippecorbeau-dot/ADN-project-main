<?php

namespace App\Repository;

use App\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSetting>
 */
class SiteSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    /**
     * Récupère toutes les settings sous forme de tableau associatif [key => value]
     */
    public function getAllSettings(): array
    {
        $settings = $this->findAll();
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getValue();
        }
        return $result;
    }

    /**
     * Récupère une setting par sa clé
     */
    public function getByKey(string $key): ?SiteSetting
    {
        return $this->findOneBy(['settingKey' => $key]);
    }

    /**
     * Récupère la valeur d'une setting par sa clé
     */
    public function getValue(string $key, string $default = ''): string
    {
        $setting = $this->getByKey($key);
        return $setting ? $setting->getValue() : $default;
    }

    /**
     * Met à jour ou crée une setting
     */
    public function setValue(string $key, string $value, string $label = '', ?string $suffix = null): SiteSetting
    {
        $setting = $this->getByKey($key);
        
        if (!$setting) {
            $setting = new SiteSetting();
            $setting->setSettingKey($key);
        }
        
        $setting->setValue($value);
        
        if ($label) {
            $setting->setLabel($label);
        }
        
        if ($suffix !== null) {
            $setting->setSuffix($suffix);
        }
        
        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();
        
        return $setting;
    }
}

