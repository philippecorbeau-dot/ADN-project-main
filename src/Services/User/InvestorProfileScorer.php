<?php

namespace App\Services\User;

use App\Entity\User\User;
use App\Entity\User\Info;
use App\Entity\User\Knowledge\InvestorKnowledge;

class InvestorProfileScorer
{
    // Constantes pour les catégories de profil
    public const PROFILE_PRUDENT = 'PRUDENT';
    public const PROFILE_EQUILIBRE = 'EQUILIBRE';
    public const PROFILE_DYNAMIQUE = 'DYNAMIQUE';
    public const PROFILE_SPE = 'SPE';

    // Seuils normalisés (0-100). Rend configurable via constructeur si besoin.
    private array $scoreThresholds = [
        self::PROFILE_PRUDENT => 0,
        self::PROFILE_EQUILIBRE => 30,
        self::PROFILE_DYNAMIQUE => 60,
        self::PROFILE_SPE => 80,
    ];

    // Pondérations par rubrique (total 100 par défaut)
    // Renforcement du poids de la Step 3 pour mieux refléter la situation patrimoniale
    private array $weights = [
        'step2' => 20,        // Objectifs
        'step3' => 40,        // Patrimoine / revenus (renforcé)
        'step4' => 15,        // Expérience MIF2 / aware
        'knowledge' => 25,    // Questionnaire connaissances
    ];

    // Journal détaillé pour audit/BO
    private array $lastBreakdown = [];

    public function __construct(?array $weights = null, ?array $thresholds = null)
    {
        if (is_array($weights)) {
            $this->weights = $weights;
        }
        if (is_array($thresholds)) {
            $this->scoreThresholds = $thresholds;
        }
    }

    /**
     * Calcule le score total du profil investisseur
     */
    public function calculateTotalScore(User $user): int
    {
        $score = 0;

        $this->lastBreakdown = [];

        // Score du Step 2 (Objectifs d'investissement)
        $s2 = $this->calculateStep2Score($user);
        $this->lastBreakdown['step2_raw'] = $s2;

        // Score du Step 3 (Patrimoine/Situation financière)
        $s3 = $this->calculateStep3Score($user);
        $this->lastBreakdown['step3_raw'] = $s3;

        // Score du Step 4 (Expérience)
        $s4 = $this->calculateStep4Score($user);
        $this->lastBreakdown['step4_raw'] = $s4;

        // Score du questionnaire de connaissance des marchés financiers
        $sk = $this->calculateKnowledgeScore($user);
        $this->lastBreakdown['knowledge_raw'] = $sk;

        // Normalisation sur 100 via pondérations
        $normalized = $this->normalizeTo100($s2, $s3, $s4, $sk);
        $this->lastBreakdown['normalized_total'] = $normalized;

        // Garde‑fous: plafonds et planchers selon cohérence
        $normalized = $this->applySafetyGuards($normalized, $user, $sk);
        $this->lastBreakdown['safety_adjusted_total'] = $normalized;

        return $normalized;
    }

    /**
     * Calcule le score du Step 2 (Objectifs d'investissement)
     */
    private function calculateStep2Score(User $user): int
    {
        $score = 0;
        $info = $user->getInfo();

        if (!$info) {
            return $score;
        }

        $objectives = $info->getObjective() ?? [];
        if (is_array($objectives)) {
            foreach ($objectives as $objective) {
                switch ($objective) {
                    case Info::OBJECTIVE_DIVERSIFY:
                        $score += 10;
                        break;
                    case Info::OBJECTIVE_REALESTATE:
                        $score += 8;
                        break;
                    case Info::OBJECTIVE_SAVINGS:
                        $score += 5;
                        break;
                    case Info::OBJECTIVE_TAXATION:
                        $score += 6;
                        break;
                    case Info::OBJECTIVE_RETIREMENT:
                        $score += 7;
                        break;
                    case Info::OBJECTIVE_LEGACY:
                        $score += 4;
                        break;
                }
            }
        }

        // Horizon d'investissement (plus long → plus de score)
        $term = $info->getInvestmentTerm();
        if (is_array($term) && count($term) > 0) {
            $count = count(array_filter($term, static fn($value) => $value !== null && $value !== ''));
            if ($count >= 1) { $score += 3; }
            if ($count >= 2) { $score += 3; }
            if ($count >= 3) { $score += 4; }
        }

        return $score;
    }

    /**
     * Calcule le score du Step 3 (Patrimoine/Situation financière)
     */
    private function calculateStep3Score(User $user): int
    {
        $score = 0;
        $info = $user->getInfo();

        // Si parcours PRO et données Pro disponibles, utiliser des repères simples
        if ($user->isPro() && $user->getPro()) {
            $pro = $user->getPro();
            $capital = (int) ($pro->getCapital() ?? 0);
            $turnover = (int) ($pro->getTurnover() ?? 0);
            $stocks = (int) ($pro->getStocks() ?? 0);

            if ($capital > 1000000) { $score += 20; }
            elseif ($capital > 300000) { $score += 15; }
            elseif ($capital > 100000) { $score += 10; }
            elseif ($capital > 50000) { $score += 5; }

            if ($turnover > 5000000) { $score += 20; }
            elseif ($turnover > 1000000) { $score += 15; }
            elseif ($turnover > 300000) { $score += 10; }
            elseif ($turnover > 100000) { $score += 5; }

            if ($stocks > 1000000) { $score += 10; }
            elseif ($stocks > 300000) { $score += 7; }
            elseif ($stocks > 100000) { $score += 5; }

            return $score;
        }

        if (!$info) {
            return $score;
        }

        // Score basé sur le patrimoine immobilier
        $realestate = $info->getRealestate() ?? 0;
        if ($realestate > 500000) {
            $score += 20;
        } elseif ($realestate > 200000) {
            $score += 15;
        } elseif ($realestate > 100000) {
            $score += 10;
        } elseif ($realestate > 50000) {
            $score += 5;
        }

        // Score basé sur le patrimoine financier (AccountSecurities + Capitalisation + Scpi)
        $accountSecurities = $info->getAccountSecurities() ?? 0;
        $capitalisation = $info->getCapitalisation() ?? 0;
        $scpi = $info->getScpi() ?? 0;
        $financial = $accountSecurities + $capitalisation + $scpi;
        
        if ($financial > 1000000) {
            $score += 25;
        } elseif ($financial > 500000) {
            $score += 20;
        } elseif ($financial > 200000) {
            $score += 15;
        } elseif ($financial > 100000) {
            $score += 10;
        } elseif ($financial > 50000) {
            $score += 5;
        }

        // Score basé sur les revenus
        $income = $info->getIncome() ?? 0;
        if ($income > 200000) {
            $score += 15;
        } elseif ($income > 100000) {
            $score += 12;
        } elseif ($income > 60000) {
            $score += 8;
        } elseif ($income > 40000) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Calcule le score du Step 4 (Expérience)
     */
    private function calculateStep4Score(User $user): int
    {
        $score = 0;
        $info = $user->getInfo();

        if (!$info) {
            return $score;
        }

        // Score basé sur l'expérience d'investissement
        if ($info->isAwarenessMinimumAmount()) {
            $score += 10;
        }
        if ($info->isAwarenessMinimumTime()) {
            $score += 10;
        }
        if ($info->isAwarenessTransactions()) {
            $score += 10;
        }

        // Score basé sur le statut MIF2
        if ($info->isMif()) {
            $score += 25;
        }

        // Score basé sur le profil aware
        if ($user->getIsAwareProfile()) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Calcule le score du questionnaire de connaissance des marchés financiers
     */
    private function calculateKnowledgeScore(User $user): int
    {
        $score = 0;
        $investorKnowledge = $user->getInvestorKnowledge();

        if (!$investorKnowledge) {
            return $score;
        }

        // Score pour les produits financiers (8 questions, max 24 points)
        if ($financialProducts = $investorKnowledge->getFinancialProductsKnowledge()) {
            $score += $financialProducts->getScore();
        }

        // Score pour les produits complexes (10 questions, max 30 points)
        if ($complexProducts = $investorKnowledge->getComplexProductsKnowledge()) {
            $score += $complexProducts->getScore();
        }

        // Score pour l'expérience des marchés (max 20 points)
        if ($marketExperience = $investorKnowledge->getMarketExperience()) {
            $score += $marketExperience->getTotalExperienceScore();
        }

        // Bonus pour l'éducation (max 10 points)
        if ($educationLevel = $investorKnowledge->getEducationLevel()) {
            $level = $educationLevel->getLevel();
            if ($level === 'master' || $level === 'phd') {
                $score += 10;
            } elseif ($level === 'bachelor') {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * Détermine la catégorie de profil basée sur le score total
     */
    public function calculateProfileType(int $score): string
    {
        if ($score >= $this->scoreThresholds[self::PROFILE_SPE]) {
            return self::PROFILE_SPE;
        } elseif ($score >= $this->scoreThresholds[self::PROFILE_DYNAMIQUE]) {
            return self::PROFILE_DYNAMIQUE;
        } elseif ($score >= $this->scoreThresholds[self::PROFILE_EQUILIBRE]) {
            return self::PROFILE_EQUILIBRE;
        } else {
            return self::PROFILE_PRUDENT;
        }
    }

    /**
     * Calcule et met à jour le profil investisseur complet
     */
    public function calculateAndUpdateProfile(User $user): void
    {
        $score = $this->calculateTotalScore($user);
        $profile = $this->calculateProfileType($score);

        $user->setInvestorScore($score);
        $user->setInvestorProfile($profile);
        $user->setInvestorProfileCalculatedAt(new \DateTimeImmutable());
    }

    /**
     * Retourne la décomposition du dernier calcul (pour BO/debug)
     */
    public function getLastBreakdown(): array
    {
        return $this->lastBreakdown;
    }

    private function normalizeTo100(int $s2, int $s3, int $s4, int $sk): int
    {
        // Bornes max empiriques des sous‑scores actuels
        $max = [
            'step2' => 40,      // intérêts
            'step3' => 60,      // patrimoine/revenus
            'step4' => 60,      // MIF2/aware
            'knowledge' => 84,  // 24 + 30 + 20 + 10
        ];

        $parts = [
            'step2' => $s2,
            'step3' => $s3,
            'step4' => $s4,
            'knowledge' => $sk,
        ];

        $total = 0.0;
        foreach ($parts as $k => $raw) {
            $ratio = $max[$k] > 0 ? min(max($raw / $max[$k], 0), 1) : 0; // 0..1
            $weighted = $ratio * $this->weights[$k];
            $this->lastBreakdown[$k.'_ratio'] = $ratio;
            $this->lastBreakdown[$k.'_weighted'] = $weighted;
            $total += $weighted;
        }

        return (int) round($total);
    }

    private function applySafetyGuards(int $normalized, User $user, int $knowledgeScore): int
    {
        $info = $user->getInfo();
        if (!$info) {
            return $normalized;
        }

        // Si MIF2 vrai → plancher EQUILIBRE
        if ($info->isMif()) {
            $normalized = max($normalized, $this->scoreThresholds[self::PROFILE_EQUILIBRE]);
        }

        // Si connaissances très faibles (< 20% du max), limiter à ÉQUILIBRÉ (59 max)
        $knowledgeMax = 84;
        if ($knowledgeMax > 0 && ($knowledgeScore / $knowledgeMax) < 0.2) {
            $normalized = min($normalized, $this->scoreThresholds[self::PROFILE_DYNAMIQUE] - 1);
        }

        return $normalized;
    }

    /**
     * Retourne les recommandations de produits selon le profil
     */
    public function getProductRecommendations(string $profile): array
    {
        switch ($profile) {
            case self::PROFILE_SPE:
                return [
                    'Produits dérivés complexes',
                    'Stratégies d\'arbitrage',
                    'Private Equity',
                    'Hedge Funds',
                    'Produits structurés avancés'
                ];
            case self::PROFILE_DYNAMIQUE:
                return [
                    'Actions individuelles',
                    'Produits structurés',
                    'SCPI',
                    'Private Equity',
                    'ETF sectoriels'
                ];
            case self::PROFILE_EQUILIBRE:
                return [
                    'ETF diversifiés',
                    'SCPI',
                    'Obligations',
                    'Assurance-vie',
                    'Fonds de placement'
                ];
            case self::PROFILE_PRUDENT:
            default:
                return [
                    'ETF indiciels',
                    'Assurance-vie',
                    'Livret A',
                    'LDDS',
                    'Fonds euros'
                ];
        }
    }

    /**
     * Retourne la description du profil
     */
    public function getProfileDescription(string $profile): string
    {
        switch ($profile) {
            case self::PROFILE_SPE:
                return 'Investisseur Sophistiqué avec une expérience avancée des marchés financiers et une capacité à gérer des produits complexes.';
            case self::PROFILE_DYNAMIQUE:
                return 'Investisseur Dynamique avec une bonne connaissance des marchés et une appétence pour le risque modéré à élevé.';
            case self::PROFILE_EQUILIBRE:
                return 'Investisseur Équilibré avec une approche prudente mais diversifiée de l\'investissement.';
            case self::PROFILE_PRUDENT:
            default:
                return 'Investisseur Prudent préférant les placements sécurisés et la capitalisation.';
        }
    }

    /**
     * Retourne la couleur du badge pour le profil
     */
    public function getProfileColor(string $profile): string
    {
        switch ($profile) {
            case self::PROFILE_SPE:
                return 'purple';
            case self::PROFILE_DYNAMIQUE:
                return 'orange';
            case self::PROFILE_EQUILIBRE:
                return 'blue';
            case self::PROFILE_PRUDENT:
            default:
                return 'green';
        }
    }
} 