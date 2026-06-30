<?php

namespace App\Service\MarketData;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Client de données de référence léger.
 * 1) Map locale (quelques symboles CAC40) → ISIN + type (FIRD=equity/etf simplifié)
 * 2) Hook prêt pour OpenFIGI si on veut l’activer plus tard (sans l’appeler pour l’instant)
 */
class ReferenceDataClient
{
    /** @var array<string, array{isin?: string, type?: string, name?: string}> */
    private array $local = [
        // Euronext Paris
        'MC.PA' => ['isin' => 'FR0000121014', 'type' => 'EQUITY', 'name' => 'LVMH Moët Hennessy Louis Vuitton SE'],
        'OR.PA' => ['isin' => 'FR0000120321', 'type' => 'EQUITY', 'name' => 'L’Oréal SA'],
        'AIR.PA' => ['isin' => 'NL0000235190', 'type' => 'EQUITY'],
        'BNP.PA' => ['isin' => 'FR0000131104', 'type' => 'EQUITY'],
        'SAN.PA' => ['isin' => 'FR0000120578', 'type' => 'EQUITY'],
        'DG.PA' => ['isin' => 'FR0000125486', 'type' => 'EQUITY'],
        'KER.PA' => ['isin' => 'FR0000121485', 'type' => 'EQUITY'],
        'AI.PA' => ['isin' => 'FR0000120073', 'type' => 'EQUITY', 'name' => 'Air Liquide SA'],
        'GLE.PA' => ['isin' => 'FR0000130809', 'type' => 'EQUITY'],
        'SU.PA' => ['isin' => 'FR0000121972', 'type' => 'EQUITY'],
    ];

    public function __construct(private readonly KernelInterface $kernel)
    {
        // Charger map externe si disponible: config/refdata_euronext.json
        $path = $this->kernel->getProjectDir() . '/config/refdata_euronext.json';
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                // fusion sans écraser explicitement les clés locales si absentes dans le fichier
                $this->local = array_merge($this->local, $json);
            }
        }
    }

    public function resolve(string $symbol, ?string $exchange = null): array
    {
        $key = $symbol;
        $isIsin = (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($symbol));
        // Si c'est un ISIN, ne pas tenter d'ajouter de suffixe de place
        if (!$isIsin && $exchange === 'XPAR' && !str_contains($symbol, '.')) {
            $key .= '.PA';
        }
        return $this->local[$key] ?? [];
    }

    /**
     * Retourne la liste des symboles dont l'ISIN correspond.
     * @return string[]
     */
    public function findSymbolsByIsin(string $isin): array
    {
        $isin = strtoupper(trim($isin));
        $matches = [];
        foreach ($this->local as $symbol => $info) {
            if (isset($info['isin']) && strtoupper((string) $info['isin']) === $isin) {
                $matches[] = $symbol;
            }
        }
        return $matches;
    }
}


