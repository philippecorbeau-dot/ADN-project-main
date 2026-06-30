<?php

declare(strict_types=1);

/**
 * Données O2S Web extraites de captures écran utilisateur (vraies données prod ADN).
 * Chaque entrée correspond à un compte client réel observé dans O2S Web.
 *
 * Format :
 *   - id            : identifiant compte O2S
 *   - libelle       : libellé du compte
 *   - situationDate : date de la "Situation de compte au ..."
 *   - fondsEuros    : ligne fonds euros (pas d'ISIN public)
 *   - lignes[]      : actifs avec ISIN
 *   - totalCompte   : total affiché par O2S Web
 */

return [
    'COC000056' => [
        'id' => 'COC000056',
        'libelle' => 'CNP Alysés Lux Capi (Contrat capitalisation)',
        'client' => 'Philippe Corbeau',
        'situationDate' => '27/05/2026',
        'fondsEuros' => ['libelle' => 'CNP Alysés Euro', 'valeur' => 67787.80],
        'lignes' => [
            ['isin' => 'FR0010622514', 'libelle' => 'Ostrum SRI Cash',     'qty' => 186.57, 'navO2S' => 330.284,  'navDateO2S' => '26/05/2026', 'valO2S' => 61621.48],
            ['isin' => 'FR0010149302', 'libelle' => 'Carmignac Emergents', 'qty' => 6.32,   'navO2S' => 1865.460, 'navDateO2S' => '22/05/2026', 'valO2S' => 11792.88],
            ['isin' => 'LU2309388624', 'libelle' => 'Auris Gravity US',    'qty' => 60.68,  'navO2S' => 180.000,  'navDateO2S' => '21/05/2026', 'valO2S' => 10922.62],
            ['isin' => 'FR0000008963', 'libelle' => 'LBPAM ISR Actions',   'qty' => 30.11,  'navO2S' => 343.670,  'navDateO2S' => '22/05/2026', 'valO2S' => 10346.91],
            ['isin' => 'FR001400H5L2', 'libelle' => 'Solstice Selection',  'qty' => 87.76,  'navO2S' => 117.400,  'navDateO2S' => '20/05/2026', 'valO2S' => 10302.75],
        ],
        'totalCompte' => 172774.44,
    ],

    'OC830110468' => [
        'id' => 'OC830110468',
        'libelle' => 'HIMALIA (Generali Patrimoine, Assurance Vie)',
        'client' => "Emmanuel D'Estanque",
        'situationDate' => '27/05/2026',
        'fondsEuros' => ['libelle' => 'Actif Général Generali Vie', 'valeur' => 43249.71],
        'lignes' => [
            ['isin' => 'FR001400H5L2', 'libelle' => 'Solstice Selection',                  'qty' => 124.48, 'navO2S' => 117.7500,  'navDateO2S' => '21/05/2026', 'valO2S' => 14657.44],
            ['isin' => 'FR0013345709', 'libelle' => 'LBPAM ISR Actions Euro L',            'qty' => 68.49,  'navO2S' => 207.3400,  'navDateO2S' => '26/05/2026', 'valO2S' => 14201.19],
            ['isin' => 'LU0336083497', 'libelle' => 'Carmignac Portfolio Global Bond A',   'qty' => 5.61,   'navO2S' => 1570.7600, 'navDateO2S' => '26/05/2026', 'valO2S' => 8817.30],
            ['isin' => 'FR0010149302', 'libelle' => 'Carmignac Emergents',                 'qty' => 2.76,   'navO2S' => 1891.2600, 'navDateO2S' => '26/05/2026', 'valO2S' => 5218.18],
            ['isin' => 'LU1876459303', 'libelle' => 'Axiom European Banks',                'qty' => 0.63,   'navO2S' => 4169.5300, 'navDateO2S' => '26/05/2026', 'valO2S' => 2610.54],
            ['isin' => 'LU1989766289', 'libelle' => 'CPR Invest Global Gold Mines (USD)', 'qty' => 13.40,  'navO2S' => 186.3332,  'navDateO2S' => '22/05/2026', 'valO2S' => 2496.55],
        ],
        'totalCompte' => 91250.91,
    ],
];
