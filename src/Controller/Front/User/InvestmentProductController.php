<?php

namespace App\Controller\Front\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\InvestmentComparisonRepository;

#[Route("/user/produits", name: "user_investment_")]
#[IsGranted("ROLE_USER")]
class InvestmentProductController extends AbstractController
{
    #[Route("/scpi", name: "scpi", methods: ["GET"])]
    public function scpi(): Response
    {
        $productData = [
            'name' => 'SCPI',
            'fullName' => 'Société Civile de Placement Immobilier',
            'description' => 'Investissement dans l\'immobilier commercial et résidentiel',
            'icon' => 'building',
            'color' => 'green',
            'yield' => '6-8%',
            'riskLevel' => 'Modéré',
            'minInvestment' => '1 000 €',
            'advantages' => [
                'Revenus réguliers',
                'Diversification immobilière',
                'Gestion déléguée',
                'Liquidité partielle',
                'Réduction d\'impôt possible'
            ],
            'risks' => [
                'Risque de marché immobilier',
                'Liquidité limitée',
                'Frais de gestion',
                'Risque de défaut locataire'
            ],
            'characteristics' => [
                'Type' => 'Investissement immobilier indirect',
                'Durée' => 'Long terme (8-15 ans)',
                'Fiscalité' => 'Revenus fonciers',
                'Liquidité' => 'Limitée',
                'Capital minimum' => 'À partir de 1 000 €'
            ],
            'howItWorks' => [
                'Vous investissez dans des parts de SCPI',
                'La société collecte les fonds de tous les investisseurs',
                'Elle achète et gère un patrimoine immobilier diversifié',
                'Les loyers perçus sont redistribués aux porteurs de parts',
                'Vous percevez des revenus réguliers trimestriels'
            ]
        ];

        return $this->render('front/user/investment/product.html.twig', [
            'product' => $productData,
            'user' => $this->getUser(),
        ]);
    }

    #[Route("/pea-pme", name: "pea_pme", methods: ["GET"])]
    public function peaPme(): Response
    {
        $productData = [
            'name' => 'PEA',
            'fullName' => 'Plan d\'Épargne en Actions PME',
            'description' => 'Investissement dans les petites et moyennes entreprises',
            'icon' => 'chart-line',
            'color' => 'blue',
            'yield' => 'Variable',
            'riskLevel' => 'Élevé',
            'minInvestment' => '100 €',
            'advantages' => [
                'Exonération d\'impôt après 5 ans',
                'Soutien aux PME françaises',
                'Potentiel de plus-value important',
                'Plafond de versement : 225 000 €',
                'Transmission facilitée'
            ],
            'risks' => [
                'Risque de perte en capital',
                'Volatilité des marchés',
                'Risque de liquidité',
                'Concentration sectorielle'
            ],
            'characteristics' => [
                'Type' => 'Enveloppe fiscale d\'investissement',
                'Durée' => 'Minimum 5 ans pour optimisation fiscale',
                'Fiscalité' => 'Exonération après 5 ans',
                'Liquidité' => 'Possible mais sortie du plan',
                'Plafond' => '225 000 € de versements'
            ],
            'howItWorks' => [
                'Ouverture d\'un PEA chez ADN Family Office',
                'Versements libres dans la limite du plafond',
                'Investissement dans des actions de PME éligibles',
                'Gestion des arbitrages au sein du plan',
                'Sortie après 5 ans avec exonération fiscale'
            ]
        ];

        return $this->render('front/user/investment/product.html.twig', [
            'product' => $productData,
            'user' => $this->getUser(),
        ]);
    }

    #[Route("/assurance-vie", name: "assurance_vie", methods: ["GET"])]
    public function assuranceVie(): Response
    {
        $productData = [
            'name' => 'Assurance-vie',
            'fullName' => 'Contrat d\'Assurance-vie',
            'description' => 'Solution d\'épargne et de transmission patrimoniale',
            'icon' => 'shield-check',
            'color' => 'purple',
            'yield' => '2-6%',
            'riskLevel' => 'Faible à Modéré',
            'minInvestment' => '500 €',
            'advantages' => [
                'Fiscalité attractive après 8 ans',
                'Transmission optimisée',
                'Diversification d\'actifs',
                'Liquidité totale',
                'Abattement sur les plus-values'
            ],
            'risks' => [
                'Risque de marché sur UC',
                'Frais de gestion',
                'Inflation sur fonds euros',
                'Risque de contrepartie'
            ],
            'characteristics' => [
                'Type' => 'Contrat d\'épargne et d\'assurance',
                'Durée' => 'Libre (optimum 8 ans)',
                'Fiscalité' => 'Abattement annuel après 8 ans',
                'Liquidité' => 'Totale (rachats possibles)',
                'Capital minimum' => 'À partir de 500 €'
            ],
            'howItWorks' => [
                'Souscription d\'un contrat d\'assurance-vie',
                'Versements libres ou programmés',
                'Choix entre fonds euros (sécurisés) et UC (dynamiques)',
                'Gestion libre ou déléguée disponible',
                'Transmission facilitée aux bénéficiaires désignés'
            ]
        ];

        return $this->render('front/user/investment/product.html.twig', [
            'product' => $productData,
            'user' => $this->getUser(),
        ]);
    }

    #[Route("/per", name: "per", methods: ["GET"])]
    public function per(): Response
    {
        $productData = [
            'name' => 'PER',
            'fullName' => 'Plan d\'Épargne Retraite',
            'description' => 'Préparation de votre retraite avec avantages fiscaux',
            'icon' => 'calendar-days',
            'color' => 'orange',
            'yield' => 'Variable',
            'riskLevel' => 'Faible à Élevé',
            'minInvestment' => '300 €',
            'advantages' => [
                'Déduction fiscale des versements',
                'Épargne retraite constituée',
                'Sortie en rente ou capital',
                'Transmission possible',
                'Gestion pilotée disponible'
            ],
            'risks' => [
                'Risque de marché',
                'Blocage jusqu\'à la retraite',
                'Risque d\'inflation',
                'Frais de gestion'
            ],
            'characteristics' => [
                'Type' => 'Plan d\'épargne retraite',
                'Durée' => 'Jusqu\'à la retraite',
                'Fiscalité' => 'Déduction à l\'entrée, imposition à la sortie',
                'Liquidité' => 'Limitée (cas exceptionnels)',
                'Plafond' => '10% des revenus professionnels'
            ],
            'howItWorks' => [
                'Ouverture d\'un PER individuel',
                'Versements déductibles de vos revenus',
                'Constitution progressive de votre épargne retraite',
                'Choix d\'investissement selon votre profil',
                'Sortie à la retraite en rente ou capital (partiellement)'
            ]
        ];

        return $this->render('front/user/investment/product.html.twig', [
            'product' => $productData,
            'user' => $this->getUser(),
        ]);
    }

    #[Route("/comparaison", name: "comparison", methods: ["GET"])]
    public function comparison(InvestmentComparisonRepository $repo): Response
    {
        // Récupération de la matrice depuis le back-office (BDD)
        // Format: [criterion => [productKey => value]]
        $matrix = $repo->getMatrix();

        // Clés produits normalisées côté back-office
        $productKeys = ['SCPI', 'PEA_PME', 'ASSURANCE_VIE', 'PER'];

        // Mapping des libellés front + couleurs
        $displayName = [
            'SCPI' => 'SCPI',
            'PEA_PME' => 'PEA',               // renommage front
            'ASSURANCE_VIE' => 'Assurance-vie',
            'PER' => 'PER',
        ];
        $colors = [
            'SCPI' => 'green',
            'PEA_PME' => 'blue',
            'ASSURANCE_VIE' => 'purple',
            'PER' => 'orange',
        ];

        // Construire la structure attendue par le template à partir de la matrice
        $products = [];
        foreach ($productKeys as $key) {
            $products[] = [
                'name' => $displayName[$key] ?? $key,
                'yield' => $matrix['yield'][$key] ?? '—',
                'risk' => $matrix['risk'][$key] ?? '—',
                'liquidity' => $matrix['liquidity'][$key] ?? '—',
                'taxation' => $matrix['taxation'][$key] ?? '—',
                'minInvestment' => $matrix['min_investment'][$key] ?? '—',
                'color' => $colors[$key] ?? 'gray',
            ];
        }

        return $this->render('front/user/investment/comparison.html.twig', [
            'products' => $products,
            'user' => $this->getUser(),
        ]);
    }
}
