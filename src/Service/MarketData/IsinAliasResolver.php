<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Résout un code d'asset interne (code O2S/Harvest, code Quantalys, etc.)
 * vers l'ISIN officiel à utiliser pour interroger les sources de marché
 * (Boursorama, FE, etc.).
 *
 * Le mapping est défini dans `config/market_data/isin_aliases.php` et chargé
 * une seule fois au démarrage. Les codes non aliassés sont retournés tels
 * quels (= comportement actuel).
 *
 * Cette indirection est cruciale pour les comptes-titres / PEA / PER où O2S
 * fournit parfois un code propriétaire à la place de l'ISIN ISO standard.
 */
final class IsinAliasResolver
{
    /** @var array<string,array{isin:string,note?:string,confidence?:string}> */
    private array $aliases;

    public function __construct(KernelInterface $kernel)
    {
        $configPath = $kernel->getProjectDir() . '/config/market_data/isin_aliases.php';
        if (file_exists($configPath)) {
            $loaded = require $configPath;
            $this->aliases = is_array($loaded) ? $loaded : [];
        } else {
            $this->aliases = [];
        }
    }

    /**
     * Retourne l'ISIN officiel équivalent à `$code`, ou `null` si pas d'alias.
     */
    public function resolveIsin(string $code): ?string
    {
        return $this->aliases[$code]['isin'] ?? null;
    }

    /**
     * Retourne l'alias complet (isin + metadata) ou null.
     *
     * @return array{isin:string,note?:string,confidence?:string}|null
     */
    public function resolve(string $code): ?array
    {
        return $this->aliases[$code] ?? null;
    }

    /**
     * Retourne le mapping complet (pour debug / commande de listing).
     *
     * @return array<string,array{isin:string,note?:string,confidence?:string}>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function hasAlias(string $code): bool
    {
        return isset($this->aliases[$code]);
    }
}
