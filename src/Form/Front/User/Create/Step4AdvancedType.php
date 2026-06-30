<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Step4AdvancedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // Questions de stratégie d'investissement
            ->add('investmentStrategy', ChoiceType::class, [
                'label' => 'Quelle est votre stratégie d\'investissement préférée ?',
                'required' => true,
                'choices' => [
                    'Investissement passif (ETF, fonds indiciels)' => 'passive',
                    'Investissement actif (sélection de titres)' => 'active',
                    'Investissement alternatif (immobilier, private equity)' => 'alternative',
                    'Investissement défensif (obligations, liquidités)' => 'defensive',
                    'Je ne sais pas encore' => 'unknown',
                ],
                'expanded' => true,
            ])
            
            ->add('investmentHorizon', ChoiceType::class, [
                'label' => 'Quel est votre horizon d\'investissement moyen ?',
                'required' => true,
                'choices' => [
                    'Court terme (1-3 ans)' => 'short',
                    'Moyen terme (3-7 ans)' => 'medium',
                    'Long terme (7-15 ans)' => 'long',
                    'Très long terme (15+ ans)' => 'very_long',
                ],
                'expanded' => true,
            ])
            
            ->add('derivativesExperience', ChoiceType::class, [
                'label' => 'Avez-vous déjà utilisé des produits dérivés ?',
                'required' => true,
                'choices' => [
                    'Oui, options et futures' => 'advanced',
                    'Oui, warrants et turbos' => 'intermediate',
                    'Non, jamais' => 'none',
                    'Je ne sais pas ce que c\'est' => 'unknown',
                ],
                'expanded' => true,
            ])
            
            ->add('internationalExposure', ChoiceType::class, [
                'label' => 'Quel pourcentage de votre portefeuille en actifs internationaux ?',
                'required' => true,
                'choices' => [
                    '0-20%' => 'low',
                    '20-50%' => 'medium',
                    '50-80%' => 'high',
                    '80-100%' => 'very_high',
                ],
                'expanded' => true,
            ])
            
            ->add('marketReaction', ChoiceType::class, [
                'label' => 'Comment réagissez-vous aux baisses de marché ?',
                'required' => true,
                'choices' => [
                    'Je vends pour limiter les pertes' => 'sell',
                    'J\'attends que ça remonte' => 'hold',
                    'J\'achète plus (moyenne à la baisse)' => 'buy',
                    'Je rééquilibre mon portefeuille' => 'rebalance',
                ],
                'expanded' => true,
            ])
            
            // Questions de tolérance au risque avancée
            ->add('temporalRiskTolerance', ChoiceType::class, [
                'label' => 'Quelle est votre tolérance au risque temporel ?',
                'required' => true,
                'choices' => [
                    'Je préfère la stabilité à court terme' => 'conservative',
                    'Je peux accepter des fluctuations temporaires' => 'moderate',
                    'Je recherche la performance même avec des baisses' => 'aggressive',
                ],
                'expanded' => true,
            ])
            
            ->add('volatilityPreference', ChoiceType::class, [
                'label' => 'Préférez-vous la stabilité ou le rendement ?',
                'required' => true,
                'choices' => [
                    'Stabilité avant tout (2-4% par an)' => 'stability',
                    'Équilibré (4-7% par an)' => 'balanced',
                    'Performance (7-10% par an)' => 'performance',
                    'Performance élevée (10%+ par an)' => 'high_performance',
                ],
                'expanded' => true,
            ])
            
            ->add('rebalancingFrequency', ChoiceType::class, [
                'label' => 'À quelle fréquence souhaitez-vous rééquilibrer votre portefeuille ?',
                'required' => true,
                'choices' => [
                    'Automatiquement (gestion déléguée)' => 'automatic',
                    'Trimestriellement' => 'quarterly',
                    'Semestriellement' => 'semiannual',
                    'Annuellement' => 'annual',
                    'Jamais' => 'never',
                ],
                'expanded' => true,
            ])
            
            ->add('performanceTarget', ChoiceType::class, [
                'label' => 'Quel est votre objectif de performance annuel ?',
                'required' => true,
                'choices' => [
                    'Battre l\'inflation (2-3%)' => 'inflation',
                    'Performance modérée (4-6%)' => 'moderate',
                    'Performance élevée (7-10%)' => 'high',
                    'Performance très élevée (10%+)' => 'very_high',
                ],
                'expanded' => true,
            ])
            
            ->add('liquidityNeeds', ChoiceType::class, [
                'label' => 'Quels sont vos besoins de liquidité à court terme ?',
                'required' => true,
                'choices' => [
                    'Élevés (besoin de cash rapidement)' => 'high',
                    'Modérés (quelques mois de préavis)' => 'moderate',
                    'Faibles (investissement long terme)' => 'low',
                    'Aucun (capital bloqué accepté)' => 'none',
                ],
                'expanded' => true,
            ])
            
            // Questions d'objectifs financiers
            ->add('mainFinancialGoal', ChoiceType::class, [
                'label' => 'Quel est votre objectif financier principal ?',
                'required' => true,
                'choices' => [
                    'Préparer ma retraite' => 'retirement',
                    'Acheter un bien immobilier' => 'real_estate',
                    'Transmettre à mes enfants' => 'inheritance',
                    'Générer des revenus complémentaires' => 'income',
                    'Préserver mon capital' => 'preservation',
                    'Autre' => 'other',
                ],
                'expanded' => true,
            ])
            
            ->add('targetAmount', IntegerType::class, [
                'label' => 'Montant cible à atteindre (en euros)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 100000',
                    'min' => 0,
                ],
            ])
            
            ->add('goalDeadline', ChoiceType::class, [
                'label' => 'Dans combien d\'années souhaitez-vous atteindre cet objectif ?',
                'required' => true,
                'choices' => [
                    '1-3 ans' => 'short',
                    '3-7 ans' => 'medium',
                    '7-15 ans' => 'long',
                    '15+ ans' => 'very_long',
                ],
                'expanded' => true,
            ])
            
            ->add('regularIncomeNeed', ChoiceType::class, [
                'label' => 'Avez-vous besoin de revenus réguliers de vos investissements ?',
                'required' => true,
                'choices' => [
                    'Oui, c\'est essentiel' => 'essential',
                    'Oui, c\'est un plus' => 'preferred',
                    'Non, je réinvestis tout' => 'none',
                ],
                'expanded' => true,
            ])
            
            ->add('taxOptimization', ChoiceType::class, [
                'label' => 'L\'optimisation fiscale est-elle importante pour vous ?',
                'required' => true,
                'choices' => [
                    'Très importante' => 'very_important',
                    'Importante' => 'important',
                    'Peu importante' => 'not_important',
                    'Pas importante' => 'not_at_all',
                ],
                'expanded' => true,
            ])
            
            // Questions de comportement d'investisseur
            ->add('portfolioCheckFrequency', ChoiceType::class, [
                'label' => 'À quelle fréquence consultez-vous vos investissements ?',
                'required' => true,
                'choices' => [
                    'Plusieurs fois par jour' => 'multiple_daily',
                    'Une fois par jour' => 'daily',
                    'Une fois par semaine' => 'weekly',
                    'Une fois par mois' => 'monthly',
                    'Une fois par trimestre' => 'quarterly',
                    'Une fois par an' => 'yearly',
                ],
                'expanded' => true,
            ])
            
            ->add('informationSources', ChoiceType::class, [
                'label' => 'Quelles sont vos sources d\'information privilégiées ?',
                'required' => true,
                'choices' => [
                    'Conseillers financiers' => 'advisors',
                    'Médias financiers (Bloomberg, Reuters)' => 'financial_media',
                    'Réseaux sociaux et forums' => 'social_media',
                    'Rapports d\'analystes' => 'analyst_reports',
                    'Formation personnelle' => 'self_education',
                    'Autre' => 'other',
                ],
                'expanded' => true,
            ])
            
            ->add('managementPreference', ChoiceType::class, [
                'label' => 'Préférez-vous la gestion déléguée ou autonome ?',
                'required' => true,
                'choices' => [
                    'Gestion entièrement déléguée' => 'fully_delegated',
                    'Gestion partiellement déléguée' => 'partially_delegated',
                    'Gestion autonome avec conseils' => 'autonomous_with_advice',
                    'Gestion entièrement autonome' => 'fully_autonomous',
                ],
                'expanded' => true,
            ])
            
            ->add('personalizedSupport', ChoiceType::class, [
                'label' => 'Souhaitez-vous un accompagnement personnalisé ?',
                'required' => true,
                'choices' => [
                    'Oui, très important' => 'very_important',
                    'Oui, c\'est un plus' => 'preferred',
                    'Non, je préfère être autonome' => 'not_needed',
                ],
                'expanded' => true,
            ])
            
            ->add('educationNeeds', ChoiceType::class, [
                'label' => 'Souhaitez-vous une formation sur certains produits ?',
                'required' => true,
                'choices' => [
                    'Oui, sur tous les produits' => 'all_products',
                    'Oui, sur les produits complexes' => 'complex_products',
                    'Oui, sur les produits alternatifs' => 'alternative_products',
                    'Non, je me forme seul' => 'self_education',
                ],
                'expanded' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Info::class
        ]);
    }
} 