<?php

declare(strict_types=1);

/**
 * Mapping ISIN → cotation Boursorama préférée pour les ETF/Fonds multi-cotés.
 *
 * Pourquoi ?
 *   Certains ETF UCITS sont cotés sur plusieurs places (LSE USD, Xetra EUR, Borsa Italiana EUR).
 *   Boursorama renvoie par défaut la place LSE (USD) pour la plupart, ce qui crée un mismatch
 *   de devise avec les valorisations EUR de l'extranet assureur.
 *
 *   En fournissant ici le symbole Boursorama interne pointant la cotation EUR, on force le
 *   bon prix sans conversion FX (donc sans perte de précision).
 *
 * Comment trouver le symbole ?
 *   1. Aller sur https://www.boursorama.com/cours/{ISIN}/
 *   2. Sur la page, en haut de la fiche, lien "Autres places de cotation"
 *   3. Cliquer sur la ligne EUR (Xetra / Borsa Italiana / Euronext) → l'URL devient
 *      /cours/{symbole}/ — c'est ce symbole qu'on copie ici.
 *
 * Format :
 *   'ISIN' => [
 *       'boursoramaSymbol'   => 'symboleBoursorama',   // ex: '1rPCSPX'
 *       'preferredCurrency'  => 'EUR',
 *       'note'               => 'commentaire libre',
 *   ],
 *
 * Si aucune cotation EUR n'existe (fonds USD-only par exemple CPR Invest Global Gold Mines
 * LU1989766289), ne PAS ajouter d'entrée ici → le résolveur appliquera la conversion FX
 * automatique via les taux BCE.
 */

return [
    // À compléter au fil de l'eau lors de la détection de cas multi-cotés.
    // Exemple (à valider au cas par cas) :
    //
    // 'IE00B5BMR087' => [
    //     'boursoramaSymbol'  => '1rPCSPX',
    //     'preferredCurrency' => 'EUR',
    //     'note'              => 'iShares Core S&P 500 UCITS — Xetra EUR (au lieu de LSE USD)',
    // ],
];
