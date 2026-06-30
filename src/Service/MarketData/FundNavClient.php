<?php

namespace App\Service\MarketData;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Client simple pour retourner la VL/date des fonds (par ISIN) depuis un fichier local.
 * Extensible ultérieurement pour brancher une API (Quantalys/Morningstar) avec cache.
 */
class FundNavClient
{
    /** @var array<string, array{nav?: float, navDate?: string}> */
    private array $byIsin = [];

    public function __construct(private readonly KernelInterface $kernel)
    {
        $path = $this->kernel->getProjectDir() . '/config/funds_nav.json';
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                $this->byIsin = $json;
            }
        }
    }

    /**
     * @return array{nav: float|null, navDate: string|null}
     */
    public function getByIsin(string $isin): array
    {
        $key = strtoupper(trim($isin));
        $row = $this->byIsin[$key] ?? [];
        return [
            'nav' => isset($row['nav']) ? (float) $row['nav'] : null,
            'navDate' => $row['navDate'] ?? null,
        ];
    }
}


