<?php

declare(strict_types=1);

/**
 * Mapping code interne O2S/Harvest → ISIN officiel Euronext / ISO.
 *
 * O2S/Harvest attribue parfois un code "O2S2017xxxxx" (ou autre format
 * propriétaire) au lieu de l'ISIN officiel. Sans alias, Bourso ne peut pas
 * résoudre ces codes. Ce fichier permet de retrouver la VL fraîche en
 * indiquant l'ISIN équivalent à interroger.
 *
 * Règles :
 *  - Ne mapper QUE si l'instrument cible est strictement le même (même code
 *    ISIN officiel). Pour les FCPE Eres / OPCI / SCPI, la VL du wrapper est
 *    différente du fonds maître — ne pas mapper aveuglément.
 *  - Toujours fournir une `note` (nom lisible) pour la traçabilité.
 *  - Le format `confidence` peut être "high" (action cotée publiquement) ou
 *    "medium" (fonds OPCVM dont l'équivalence a été vérifiée).
 */
return [
    // ── Actions cotées : code O2S interne → vrai ISIN Euronext ──
    'O2S201734564' => ['isin' => 'FR0000131104', 'note' => 'BNP Paribas',           'confidence' => 'high'],
    'O2S201736873' => ['isin' => 'FR0000121667', 'note' => 'EssilorLuxottica',      'confidence' => 'high'],
    'O2S201736402' => ['isin' => 'FR0000120628', 'note' => 'AXA',                   'confidence' => 'high'],
    'O2S201737695' => ['isin' => 'FR0000130809', 'note' => 'Société Générale',      'confidence' => 'high'],
    'O2S201738652' => ['isin' => 'FR0000045072', 'note' => 'Crédit Agricole SA',    'confidence' => 'high'],
    'O2S201735505' => ['isin' => 'FR0000120503', 'note' => 'Bouygues',              'confidence' => 'high'],
    'O2S201740234' => ['isin' => 'FR0000124141', 'note' => 'Veolia Environnement',  'confidence' => 'high'],
    'O2S201734976' => ['isin' => 'FR0000120578', 'note' => 'Sanofi',                'confidence' => 'high'],

    // ── Fonds OPCVM avec code O2S interne (au lieu de l'ISIN officiel) ──
    // Confidence "medium" : à valider visuellement sur quelques comptes prod
    // (vérifier que la VL native O2S correspond bien à la VL Bourso de l'ISIN cible).
    'O2S201724504' => [
        'isin' => 'GB0030932676',
        'note' => 'M&G Global Themes Fund EUR A Acc (ex-Global Basics, renommé 17/11/2017)',
        'confidence' => 'medium',
    ],
    'O2S201743499' => [
        'isin' => 'LU2882334977',
        'note' => 'DNCA Invest SRI Euro Quality BD (sub-fund de DNCA Invest SICAV)',
        'confidence' => 'medium',
    ],
    'O2S201745028' => [
        'isin' => 'FR0010058529',
        'note' => 'Thematics Europe Selection R Capitalisation (Natixis IM / Mirova)',
        'confidence' => 'medium',
    ],

    // ── À compléter au fil de l'eau ──
    // Format : 'CODE_INTERNE' => ['isin' => 'XXXXXXXXXXXX', 'note' => '...', 'confidence' => 'high|medium'],
    //
    // ── Codes restant à investiguer ──
    // 'O2S201735520' => ['isin' => '???', 'note' => 'Comète (Generali Lux Vie)', ...]
    //   → sous-fonds Comète Lifinity à confirmer (Actions / Modéré / Equilibre ?)
];
