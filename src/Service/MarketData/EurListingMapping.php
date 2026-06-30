<?php

declare(strict_types=1);

namespace App\Service\MarketData;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Mapping ISIN → place de cotation préférée pour les ETF/Fonds multi-cotés.
 *
 * Cas typique : `IE00B5BMR087` (iShares Core S&P 500) est coté à Londres en USD
 * (412 USD) et à Frankfurt/Milan en EUR (~615 EUR). Sans cet override, Boursorama
 * renvoie la place USD par défaut, ce qui produit un écart de devise vs O2S.
 *
 * Source de mapping : `config/market_data/eur_listings.php` (PHP plutôt que YAML
 * pour rester ultra-léger et 0 dépendance bundle).
 *
 * Format du fichier :
 *   return [
 *       'IE00B5BMR087' => ['boursoramaSymbol' => '1rPCSPX', 'preferredCurrency' => 'EUR', 'note' => 'CSPX Frankfurt EUR'],
 *       ...
 *   ];
 *
 * Si l'override existe :
 *  - on appelle Boursorama avec l'URL `/cours/{boursoramaSymbol}/` (qui pointe la cotation EUR)
 *  - sinon on utilise l'URL standard `/cours/{ISIN}/`
 */
final class EurListingMapping
{
    /** @var array<string, array{boursoramaSymbol?: string, preferredCurrency?: string, note?: string}> */
    private array $map = [];

    public function __construct(KernelInterface $kernel)
    {
        $path = $kernel->getProjectDir() . '/config/market_data/eur_listings.php';
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                // Normalise les clés en upper-case
                foreach ($loaded as $isin => $row) {
                    $this->map[strtoupper((string) $isin)] = $row;
                }
            }
        }
    }

    /**
     * Retourne le symbole Boursorama à interroger (ex: 1rPCSPX), ou null si pas d'override.
     */
    public function getBoursoramaSymbol(string $isin): ?string
    {
        $row = $this->map[strtoupper($isin)] ?? null;
        return $row['boursoramaSymbol'] ?? null;
    }

    /**
     * Retourne la devise attendue (EUR par défaut) pour cet ISIN.
     */
    public function getPreferredCurrency(string $isin): string
    {
        $row = $this->map[strtoupper($isin)] ?? null;
        return $row['preferredCurrency'] ?? 'EUR';
    }

    public function has(string $isin): bool
    {
        return isset($this->map[strtoupper($isin)]);
    }

    /**
     * @return array<string, array{boursoramaSymbol?: string, preferredCurrency?: string, note?: string}>
     */
    public function all(): array
    {
        return $this->map;
    }
}
