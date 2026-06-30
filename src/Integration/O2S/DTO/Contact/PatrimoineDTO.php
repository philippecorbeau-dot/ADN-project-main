<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Contact;

/**
 * Data Transfer Object for patrimoine (wealth) data from O2S Contact API.
 * 
 * Parses the `stocks` array from GET /contacts/{contactId} response.
 * Each stock has a family code that classifies it:
 *   - ACTIF_ResidencePrincipale / ACTIF_ResidenceSecondaire → Immobilier
 *   - ACTIF_AutresBiensUsage / ACTIF_ImmobilierLocatif     → Immobilier
 *   - ASSURANCE_ContratsStandard_UC / O2S_ContratsStandard  → Actifs financiers
 *   - ACTIF_ComptesCourants / ACTIF_Livrets / ACTIF_PEA    → Actifs financiers
 *   - ACTIF_ComptesATerme                                   → Actifs financiers
 *   - PASSIF_* / O2S_Emprunts*                              → Passifs
 */
final class PatrimoineDTO
{
    // ─── Family code classification ───
    private const IMMOBILIER_FAMILIES = [
        'ACTIF_ResidencePrincipale', 'O2S_ResidencePrincipale',
        'ACTIF_ResidenceSecondaire', 'O2S_ResidenceSecondaire',
        'ACTIF_AutresBiensUsage', 'O2S_AutresBiensUsage',
        'ACTIF_ImmobilierLocatif', 'O2S_ImmobilierLocatif',
        'ACTIF_ImmeubleLocatif', 'O2S_ImmeubleLocatif', 'O2S_ImmeubleLocatif_ORDINAIRE',
        'ACTIF_ImmeubleLocatif_PINEL', 'O2S_ImmeubleLocatif_PINEL',
        'ACTIF_BiensMobiliers', 'O2S_BiensMobiliers',
        'ACTIF_SCPI', 'O2S_SCPI',
        'ACTIF_PartsDeSCPI', 'O2S_PartsDeSCPI', 'O2S_PartsDeSCPI_ORDINAIRE', 'O2S_PartsDeSCPI_SCELLIER_BBC',
        'ACTIF_SCI', 'O2S_SCI',
        'ACTIF_PartsDeSCI', 'O2S_PartsDeSCI',  // Parts de SCI = immobilier
        'ACTIF_BiensProfessionnels', 'O2S_BiensProfessionnels',
    ];

    private const FINANCIER_FAMILIES = [
        'ASSURANCE_ContratsStandard_UC', 'O2S_ContratsStandard',
        'ACTIF_ComptesCourants', 'O2S_ComptesCourants',
        'ACTIF_CompteCourantAssocie', 'O2S_CompteCourantAssocie',  // Comptes courants d'associés (SCI, etc.)
        'ACTIF_Livrets', 'O2S_Livrets',
        'ACTIF_LivretA', 'O2S_LivretA',
        'ACTIF_LivretDeveloppementDurable', 'O2S_LivretDeveloppementDurable',
        'ACTIF_LivretJeune', 'O2S_LivretJeune',
        'ACTIF_LEP', 'O2S_LEP',
        'ACTIF_CEL', 'O2S_CEL',
        'ACTIF_PEL', 'O2S_PEL',
        'ACTIF_PEA', 'O2S_PEA',
        'ACTIF_ComptesATerme', 'O2S_ComptesATerme',
        'ACTIF_PortefeuillesTitres', 'O2S_PortefeuillesTitres',
        'ACTIF_ComptesTitres', 'O2S_ComptesTitres',
        'ACTIF_CompteTitre', 'O2S_CompteTitre',
        'ASSURANCE_PER', 'O2S_PER',
        'ACTIF_PERIndividuel', 'O2S_PERIndividuel',
        'ASSURANCE_Retraite', 'O2S_Retraite',
        'ASSURANCE_Prevoyance', 'O2S_Prevoyance',
        'ASSURANCE_CARAC', 'O2S_AutreEpargneSalariale',
        'ASSURANCE_Capitalisation', 'O2S_Capitalisation',
        'ACTIF_BonDeCapitalisation_UC', 'ACTIF_BonDeCapitalisation_EURO', 'O2S_BonDeCapitalisation',  // Bons de capitalisation
        'ACTIF_EpargneSalariale', 'O2S_EpargneSalariale',
        'ASSURANCE_LoiMadelin', 'O2S_LoiMadelin',  // Contrats Madelin (retraite TNS)
    ];

    // ─── Financial sub-category classification (like Harvest breakdown) ───
    private const FIN_COMPTES_COURANTS = [
        'ACTIF_ComptesCourants', 'O2S_ComptesCourants',
        'ACTIF_CompteCourantAssocie', 'O2S_CompteCourantAssocie',  // CCA (SCI, etc.)
    ];

    private const FIN_EPARGNE_BANCAIRE = [
        'ACTIF_Livrets', 'O2S_Livrets',
        'ACTIF_LivretA', 'O2S_LivretA',
        'ACTIF_LivretDeveloppementDurable', 'O2S_LivretDeveloppementDurable',
        'ACTIF_LivretJeune', 'O2S_LivretJeune',
        'ACTIF_LEP', 'O2S_LEP',
        'ACTIF_CEL', 'O2S_CEL',
        'ACTIF_PEL', 'O2S_PEL',
        'ACTIF_ComptesATerme', 'O2S_ComptesATerme',
    ];

    private const FIN_COMPTES_TITRES = [
        'ACTIF_PortefeuillesTitres', 'O2S_PortefeuillesTitres',
        'ACTIF_ComptesTitres', 'O2S_ComptesTitres',
        'ACTIF_CompteTitre', 'O2S_CompteTitre',
        'ACTIF_PEA', 'O2S_PEA',
    ];

    private const FIN_ASSURANCE_VIE = [
        'ASSURANCE_ContratsStandard_UC', 'O2S_ContratsStandard',
        'ASSURANCE_Capitalisation', 'O2S_Capitalisation',
        'ACTIF_BonDeCapitalisation_UC', 'ACTIF_BonDeCapitalisation_EURO', 'O2S_BonDeCapitalisation',  // Bons de capitalisation
    ];

    private const FIN_EPARGNE_RETRAITE = [
        'ASSURANCE_PER', 'O2S_PER',
        'ACTIF_PERIndividuel', 'O2S_PERIndividuel',
        'ASSURANCE_Retraite', 'O2S_Retraite',
        'ASSURANCE_CARAC', 'O2S_AutreEpargneSalariale',
        'ACTIF_EpargneSalariale', 'O2S_EpargneSalariale',
        'ASSURANCE_Prevoyance', 'O2S_Prevoyance',
        'ASSURANCE_LoiMadelin', 'O2S_LoiMadelin',  // Contrats Madelin (retraite TNS)
    ];

    private const PASSIF_FAMILIES = [
        'PASSIF_Emprunts', 'O2S_Emprunts',
        'PASSIF_EmpruntImmobilier', 'O2S_EmpruntImmobilier',
        'PASSIF_CreditConsommation', 'O2S_CreditConsommation',
        'PASSIF_AutresEmprunts', 'O2S_AutresEmprunts',
        'PASSIF_AutresDettesDiverses', 'O2S_PASSIF_AutresDettesDiverses',
        'PASSIF_DettesFiscales', 'O2S_PASSIF_DettesFiscales',
        'PASSIF_DettesProvisions', 'O2S_PASSIF_DettesProvisions',
    ];

    /**
     * @param array<int, array{libelle: string, valeur: float, categorie: string, part: float}> $stocksDetail
     */
    public function __construct(
        private readonly float $actifsFinanciers = 0.0,
        private readonly float $immobilier = 0.0,
        private readonly float $immobilierResidencePrincipale = 0.0,
        private readonly float $immobilierResidenceSecondaire = 0.0,
        private readonly float $immobilierLocatif = 0.0,
        private readonly float $immobilierSCPI = 0.0,
        private readonly float $immobilierAutre = 0.0,
        private readonly float $finComptesCourants = 0.0,
        private readonly float $finEpargneBancaire = 0.0,
        private readonly float $finComptesTitres = 0.0,
        private readonly float $finAssuranceVie = 0.0,
        private readonly float $finEpargneRetraite = 0.0,
        private readonly float $finAutre = 0.0,
        private readonly float $passifs = 0.0,
        private readonly float $totalActif = 0.0,
        private readonly float $totalPassif = 0.0,
        private readonly float $patrimoineNet = 0.0,
        private readonly ?string $profession = null,
        private readonly ?string $situationFamiliale = null,
        private readonly int $stockCount = 0,
        private readonly array $stocksDetail = [],
        private readonly array $stocksByCategorie = [],
        private readonly array $stocksByFinCategorie = [],
        private readonly bool $hasData = false,
        private readonly array $rawData = [],
    ) {
    }

    /**
     * Creates a PatrimoineDTO from O2S Contact API raw response.
     *
     * @param array<string, mixed> $contactData Full contact API response
     */
    public static function fromContactResponse(array $contactData): self
    {
        $stocks = $contactData['stocks'] ?? [];
        if (empty($stocks)) {
            return new self(rawData: $contactData);
        }

        $actifsFinanciers = 0.0;
        $immobilier = 0.0;
        $immobilierRP = 0.0;
        $immobilierRS = 0.0;
        $immobilierLocatif = 0.0;
        $immobilierSCPI = 0.0;
        $immobilierAutre = 0.0;
        $finComptesCourants = 0.0;
        $finEpargneBancaire = 0.0;
        $finComptesTitres = 0.0;
        $finAssuranceVie = 0.0;
        $finEpargneRetraite = 0.0;
        $finAutre = 0.0;
        $passifs = 0.0;

        $stocksDetail = [];
        $stocksByCategorie = [
            'immobilier' => [],
            'financier' => [],
            'passif' => [],
            'autre' => [],
        ];
        // Sub-categories for financial stocks (like Harvest)
        $stocksByFinCategorie = [
            'comptes_courants' => [],
            'epargne_bancaire' => [],
            'comptes_titres' => [],
            'assurance_vie' => [],
            'epargne_retraite' => [],
            'autre_financier' => [],
        ];

        foreach ($stocks as $stock) {
            $libelle = $stock['libelle'] ?? 'N/A';
            $montant = (float) ($stock['valeur']['montant'] ?? 0);

            // Extraire les codes famille (nécessaire avant le fallback prêt)
            $familles = [];
            foreach ($stock['modeles']['familles'] ?? [] as $f) {
                $familles[] = $f['code'] ?? '';
            }

            // Classifier le stock
            $categorie = self::classifyStock($familles);

            // ─── Fallback pour les passifs (prêts) ───
            // L'API O2S ne met pas de valeur.montant pour les prêts.
            // Les données sont dans stock.pret.capitalEmprunte + durée/taux.
            // On calcule le capital restant dû (CRD) si possible.
            if ($montant <= 0 && $categorie === 'passif' && isset($stock['pret'])) {
                $montant = self::computeRemainingCapital($stock['pret']);
            }

            // Déterminer le pourcentage de détention
            $part = 100.0;
            $detenteurs = $stock['detenteurs']['detenteurs'] ?? [];
            if (!empty($detenteurs)) {
                $pp = $detenteurs[0]['pleinePropriete']['part'] ?? null;
                if ($pp !== null && is_numeric($pp)) {
                    $part = (float) $pp;
                }
            }

            // Valeur pondérée par la part de détention
            $valeurDetenue = ($montant * $part) / 100.0;

            $stockEntry = [
                'libelle' => $libelle,
                'valeur' => $montant,
                'valeurDetenue' => round($valeurDetenue, 2),
                'part' => $part,
                'categorie' => $categorie,
                'familles' => $familles,
                'id' => $stock['id'] ?? null,
            ];
            $stocksDetail[] = $stockEntry;
            $stocksByCategorie[$categorie][] = $stockEntry;

            // On utilise la valeur brute (montant) pour les totaux patrimoniaux,
            // comme le fait Harvest (patrimoine du foyer, pas seulement la part détenue)
            switch ($categorie) {
                case 'immobilier':
                    $immobilier += $montant;
                    // Sous-catégoriser l'immobilier
                    if (self::matchesAny($familles, ['ACTIF_ResidencePrincipale', 'O2S_ResidencePrincipale'])) {
                        $immobilierRP += $montant;
                    } elseif (self::matchesAny($familles, ['ACTIF_ResidenceSecondaire', 'O2S_ResidenceSecondaire'])) {
                        $immobilierRS += $montant;
                    } elseif (self::matchesAny($familles, [
                        'ACTIF_ImmobilierLocatif', 'O2S_ImmobilierLocatif',
                        'ACTIF_ImmeubleLocatif', 'O2S_ImmeubleLocatif', 'O2S_ImmeubleLocatif_ORDINAIRE',
                        'O2S_ImmeubleLocatif_PINEL', 'ACTIF_ImmeubleLocatif_PINEL',
                    ])) {
                        $immobilierLocatif += $montant;
                    } elseif (self::matchesAny($familles, [
                        'ACTIF_SCPI', 'O2S_SCPI',
                        'ACTIF_PartsDeSCPI', 'O2S_PartsDeSCPI', 'O2S_PartsDeSCPI_ORDINAIRE', 'O2S_PartsDeSCPI_SCELLIER_BBC',
                    ])) {
                        $immobilierSCPI += $montant;
                    } else {
                        $immobilierAutre += $montant;
                    }
                    break;
                case 'financier':
                    $actifsFinanciers += $montant;
                    // Sous-catégoriser les actifs financiers (comme Harvest)
                    if (self::matchesAny($familles, self::FIN_COMPTES_COURANTS)) {
                        $finComptesCourants += $montant;
                        $stocksByFinCategorie['comptes_courants'][] = $stockEntry;
                    } elseif (self::matchesAny($familles, self::FIN_EPARGNE_BANCAIRE)) {
                        $finEpargneBancaire += $montant;
                        $stocksByFinCategorie['epargne_bancaire'][] = $stockEntry;
                    } elseif (self::matchesAny($familles, self::FIN_COMPTES_TITRES)) {
                        $finComptesTitres += $montant;
                        $stocksByFinCategorie['comptes_titres'][] = $stockEntry;
                    } elseif (self::matchesAny($familles, self::FIN_ASSURANCE_VIE)) {
                        $finAssuranceVie += $montant;
                        $stocksByFinCategorie['assurance_vie'][] = $stockEntry;
                    } elseif (self::matchesAny($familles, self::FIN_EPARGNE_RETRAITE)) {
                        $finEpargneRetraite += $montant;
                        $stocksByFinCategorie['epargne_retraite'][] = $stockEntry;
                    } else {
                        $finAutre += $montant;
                        $stocksByFinCategorie['autre_financier'][] = $stockEntry;
                    }
                    break;
                case 'passif':
                    $passifs += $montant;
                    break;
                default:
                    // Classer en financier par défaut si montant > 0
                    if ($montant > 0) {
                        $actifsFinanciers += $montant;
                    }
                    break;
            }
        }

        $totalActif = $actifsFinanciers + $immobilier;
        $totalPassif = $passifs;
        $patrimoineNet = $totalActif - $totalPassif;

        // Extraire profession et situation familiale
        $personne = $contactData['personne'] ?? [];
        $profession = null;
        $professions = $personne['profession']['professions'] ?? [];
        if (!empty($professions)) {
            $profession = $professions[0]['profession']['libelleProfession']
                ?? $professions[0]['profession']['applCsp']
                ?? null;
        }
        $situationFamiliale = $personne['situationFamiliale']['situationMaritale'] ?? null;

        return new self(
            actifsFinanciers: round($actifsFinanciers, 2),
            immobilier: round($immobilier, 2),
            immobilierResidencePrincipale: round($immobilierRP, 2),
            immobilierResidenceSecondaire: round($immobilierRS, 2),
            immobilierLocatif: round($immobilierLocatif, 2),
            immobilierSCPI: round($immobilierSCPI, 2),
            immobilierAutre: round($immobilierAutre, 2),
            finComptesCourants: round($finComptesCourants, 2),
            finEpargneBancaire: round($finEpargneBancaire, 2),
            finComptesTitres: round($finComptesTitres, 2),
            finAssuranceVie: round($finAssuranceVie, 2),
            finEpargneRetraite: round($finEpargneRetraite, 2),
            finAutre: round($finAutre, 2),
            passifs: round($passifs, 2),
            totalActif: round($totalActif, 2),
            totalPassif: round($totalPassif, 2),
            patrimoineNet: round($patrimoineNet, 2),
            profession: $profession,
            situationFamiliale: $situationFamiliale,
            stockCount: count($stocks),
            stocksDetail: $stocksDetail,
            stocksByCategorie: $stocksByCategorie,
            stocksByFinCategorie: $stocksByFinCategorie,
            hasData: true,
            rawData: $contactData,
        );
    }

    /**
     * Classify a stock based on its family codes.
     *
     * @param string[] $familles
     */
    private static function classifyStock(array $familles): string
    {
        if (self::matchesAny($familles, self::IMMOBILIER_FAMILIES)) {
            return 'immobilier';
        }
        if (self::matchesAny($familles, self::FINANCIER_FAMILIES)) {
            return 'financier';
        }
        if (self::matchesAny($familles, self::PASSIF_FAMILIES)) {
            return 'passif';
        }
        // Fallback générique : tout code contenant "PASSIF" est un passif
        // (couvre les futurs codes O2S non encore listés explicitement)
        foreach ($familles as $code) {
            if (stripos($code, 'PASSIF') !== false) {
                return 'passif';
            }
        }
        return 'autre';
    }

    /**
     * Check if any of the given codes matches any of the target codes.
     *
     * @param string[] $codes
     * @param string[] $targets
     */
    private static function matchesAny(array $codes, array $targets): bool
    {
        foreach ($codes as $code) {
            if (in_array($code, $targets, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compute the remaining capital (capital restant dû) for a loan from O2S pret data.
     *
     * Uses the amortization formula for constant-payment loans:
     *   CRD = capitalEmprunte × [(1+r)^N - (1+r)^n] / [(1+r)^N - 1]
     * where r = monthly rate, N = total months, n = elapsed months.
     *
     * Falls back to capitalEmprunte if calculation is not possible.
     *
     * @param array<string, mixed> $pret The pret sub-object from O2S API
     */
    private static function computeRemainingCapital(array $pret): float
    {
        $capitalEmprunte = (float) ($pret['capitalEmprunte'] ?? 0);
        if ($capitalEmprunte <= 0) {
            return 0.0;
        }

        $dateSouscription = $pret['dateSouscription'] ?? null;
        $duree = $pret['dureeAmortissement'] ?? null;
        $tauxData = $pret['taux'] ?? null;

        // If we have all info, compute CRD
        if ($dateSouscription && $duree && $tauxData) {
            try {
                $start = new \DateTimeImmutable($dateSouscription);
                $now = new \DateTimeImmutable();

                // Total duration in months
                $totalMonths = (int) ($duree['valeur'] ?? 0);
                $unite = $duree['unite'] ?? 'M';
                if ($unite === 'A') {
                    $totalMonths *= 12;
                }

                // Elapsed months since subscription
                $diff = $start->diff($now);
                $elapsedMonths = ($diff->y * 12) + $diff->m;

                // If loan is fully repaid
                if ($elapsedMonths >= $totalMonths) {
                    return 0.0;
                }

                // Annual rate as percentage → monthly rate as decimal
                $annualRate = (float) ($tauxData['valeur'] ?? 0);
                if ($annualRate > 0 && $totalMonths > 0) {
                    $monthlyRate = $annualRate / 100.0 / 12.0;
                    $factor = pow(1 + $monthlyRate, $totalMonths);
                    $factorN = pow(1 + $monthlyRate, $elapsedMonths);

                    // CRD = C × [(1+r)^N - (1+r)^n] / [(1+r)^N - 1]
                    if ($factor > 1) {
                        $crd = $capitalEmprunte * ($factor - $factorN) / ($factor - 1);
                        return round(max(0, $crd), 2);
                    }
                }

                // Zero-rate loan: linear amortization
                if ($totalMonths > 0) {
                    $remainingMonths = $totalMonths - $elapsedMonths;
                    return round($capitalEmprunte * ($remainingMonths / $totalMonths), 2);
                }
            } catch (\Throwable $e) {
                // Fall through to capitalEmprunte
            }
        }

        // Fallback: return the full borrowed amount
        return $capitalEmprunte;
    }

    // ─── Getters ───

    public function getActifsFinanciers(): float
    {
        return $this->actifsFinanciers;
    }

    public function getImmobilier(): float
    {
        return $this->immobilier;
    }

    public function getImmobilierResidencePrincipale(): float
    {
        return $this->immobilierResidencePrincipale;
    }

    public function getImmobilierResidenceSecondaire(): float
    {
        return $this->immobilierResidenceSecondaire;
    }

    public function getImmobilierLocatif(): float
    {
        return $this->immobilierLocatif;
    }

    public function getImmobilierSCPI(): float
    {
        return $this->immobilierSCPI;
    }

    public function getImmobilierAutre(): float
    {
        return $this->immobilierAutre;
    }

    public function getFinComptesCourants(): float
    {
        return $this->finComptesCourants;
    }

    public function getFinEpargneBancaire(): float
    {
        return $this->finEpargneBancaire;
    }

    public function getFinComptesTitres(): float
    {
        return $this->finComptesTitres;
    }

    public function getFinAssuranceVie(): float
    {
        return $this->finAssuranceVie;
    }

    public function getFinEpargneRetraite(): float
    {
        return $this->finEpargneRetraite;
    }

    public function getFinAutre(): float
    {
        return $this->finAutre;
    }

    /**
     * @return array<string, array<int, array{libelle: string, valeur: float, valeurDetenue: float, part: float, categorie: string}>>
     */
    public function getStocksByFinCategorie(): array
    {
        return $this->stocksByFinCategorie;
    }

    public function getPassifs(): float
    {
        return $this->passifs;
    }

    public function getTotalActif(): float
    {
        return $this->totalActif;
    }

    public function getTotalPassif(): float
    {
        return $this->totalPassif;
    }

    public function getPatrimoineNet(): float
    {
        return $this->patrimoineNet;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function getSituationFamiliale(): ?string
    {
        return $this->situationFamiliale;
    }

    public function getStockCount(): int
    {
        return $this->stockCount;
    }

    /**
     * @return array<int, array{libelle: string, valeur: float, valeurDetenue: float, part: float, categorie: string, familles: string[], id: ?string}>
     */
    public function getStocksDetail(): array
    {
        return $this->stocksDetail;
    }

    /**
     * @return array<string, array<int, array{libelle: string, valeur: float, valeurDetenue: float, part: float, categorie: string}>>
     */
    public function getStocksByCategorie(): array
    {
        return $this->stocksByCategorie;
    }

    public function hasData(): bool
    {
        return $this->hasData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Returns percentages for a donut chart: immobilier vs actifs financiers.
     *
     * @return array{immobilier_pct: int, financier_pct: int}
     */
    public function getDonutPercentages(): array
    {
        if ($this->totalActif <= 0) {
            return ['immobilier_pct' => 0, 'financier_pct' => 0];
        }

        $immoPct = (int) round(($this->immobilier / $this->totalActif) * 100);
        $finPct = (int) round(($this->actifsFinanciers / $this->totalActif) * 100);

        return [
            'immobilier_pct' => $immoPct,
            'financier_pct' => $finPct,
        ];
    }

    /**
     * Returns a structured array suitable for Twig template rendering.
     * Compatible with the existing patrimoineData format used in the dashboard.
     *
     * @return array<string, mixed>
     */
    public function toTemplateArray(): array
    {
        $pcts = $this->getDonutPercentages();

        return [
            'patrimoine' => $this->patrimoineNet,
            'actif' => $this->totalActif,
            'passif' => $this->totalPassif,
            'immobilier' => [
                'montant' => $this->immobilier,
                'pourcentage' => $pcts['immobilier_pct'],
                'residencePrincipale' => $this->immobilierResidencePrincipale,
                'residenceSecondaire' => $this->immobilierResidenceSecondaire,
                'locatif' => $this->immobilierLocatif,
                'scpi' => $this->immobilierSCPI,
                'autre' => $this->immobilierAutre,
            ],
            'actifs_financiers' => [
                'montant' => $this->actifsFinanciers,
                'pourcentage' => $pcts['financier_pct'],
                'comptes_courants' => $this->finComptesCourants,
                'epargne_bancaire' => $this->finEpargneBancaire,
                'comptes_titres' => $this->finComptesTitres,
                'assurance_vie' => $this->finAssuranceVie,
                'epargne_retraite' => $this->finEpargneRetraite,
                'autre' => $this->finAutre,
            ],
            'profession' => $this->profession,
            'situationFamiliale' => $this->situationFamiliale,
            'stockCount' => $this->stockCount,
            'stocksByCategorie' => $this->stocksByCategorie,
            'stocksByFinCategorie' => $this->stocksByFinCategorie,
            'hasData' => $this->hasData,
            'isDemoData' => false,
            'isO2S' => true,
        ];
    }
}
