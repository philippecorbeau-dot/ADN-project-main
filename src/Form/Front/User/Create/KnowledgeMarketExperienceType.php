<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Knowledge\MarketExperience;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KnowledgeMarketExperienceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('hasStocksExperience', ChoiceType::class, [
                'label' => 'Avez-vous déjà réalisé un investissement sur Actions / OPC actions ?',
                'required' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('stocksOperationsCount', ChoiceType::class, [
                'label' => 'Combien d\'opérations (achats, ventes, arbitrages) réalisez-vous en moyenne par an ?',
                'required' => false,
                'choices' => [
                    '1 fois' => '1 fois',
                    '2-10 fois' => '2-10 fois',
                    '> 10 fois' => '> 10 fois',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('stocksVolume', ChoiceType::class, [
                'label' => 'Volume de transactions (moy. Annuelle des 24 derniers mois) sur Actions / OPC actions',
                'required' => false,
                'choices' => [
                    '< 50 K€' => '< 50 K€',
                    'de 50 K€ à 150 K€' => 'de 50 K€ à 150 K€',
                    '> 150 K€' => '> 150 K€',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('hasBondsExperience', ChoiceType::class, [
                'label' => 'Avez-vous déjà réalisé un investissement sur Obligations / OPC obligations ?',
                'required' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('bondsOperationsCount', ChoiceType::class, [
                'label' => 'Combien d\'opérations (achats, ventes, arbitrages) réalisez-vous en moyenne par an ?',
                'required' => false,
                'choices' => [
                    '1 fois' => '1 fois',
                    '2-10 fois' => '2-10 fois',
                    '> 10 fois' => '> 10 fois',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('bondsVolume', ChoiceType::class, [
                'label' => 'Volume de transactions (moy. Annuelle des 24 derniers mois) sur Obligations / OPC obligations',
                'required' => false,
                'choices' => [
                    '< 50 K€' => '< 50 K€',
                    'de 50 K€ à 150 K€' => 'de 50 K€ à 150 K€',
                    '> 150 K€' => '> 150 K€',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('hasUcitsExperience', ChoiceType::class, [
                'label' => 'Avez-vous déjà réalisé un investissement sur OPCVM (FCP, SICAV) ?',
                'required' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('ucitsOperationsCount', ChoiceType::class, [
                'label' => 'Combien d\'opérations (achats, ventes, arbitrages) réalisez-vous en moyenne par an ?',
                'required' => false,
                'choices' => [
                    '1 fois' => '1 fois',
                    '2-10 fois' => '2-10 fois',
                    '> 10 fois' => '> 10 fois',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('ucitsVolume', ChoiceType::class, [
                'label' => 'Volume de transactions (moy. Annuelle des 24 derniers mois) sur OPCVM (FCP, SICAV)',
                'required' => false,
                'choices' => [
                    '< 50 K€' => '< 50 K€',
                    'de 50 K€ à 150 K€' => 'de 50 K€ à 150 K€',
                    '> 150 K€' => '> 150 K€',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('hasRealEstateExperience', ChoiceType::class, [
                'label' => 'Avez-vous déjà réalisé un investissement sur Immobilier (ex: SCPI, OPCI) ?',
                'required' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('realEstateOperationsCount', ChoiceType::class, [
                'label' => 'Combien d\'opérations (achats, ventes, arbitrages) réalisez-vous en moyenne par an ?',
                'required' => false,
                'choices' => [
                    '1 fois' => '1 fois',
                    '2-10 fois' => '2-10 fois',
                    '> 10 fois' => '> 10 fois',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('realEstateVolume', ChoiceType::class, [
                'label' => 'Volume de transactions (moy. Annuelle des 24 derniers mois) sur Immobilier (ex: SCPI, OPCI)',
                'required' => false,
                'choices' => [
                    '< 50 K€' => '< 50 K€',
                    'de 50 K€ à 150 K€' => 'de 50 K€ à 150 K€',
                    '> 150 K€' => '> 150 K€',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('hasComplexInstrumentsExperience', ChoiceType::class, [
                'label' => 'Avez-vous déjà réalisé un investissement sur Instruments complexes (ex: produits structurés, FIA, certificats) ?',
                'required' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('complexInstrumentsOperationsCount', ChoiceType::class, [
                'label' => 'Combien d\'opérations (achats, ventes, arbitrages) réalisez-vous en moyenne par an ?',
                'required' => false,
                'choices' => [
                    '1 fois' => '1 fois',
                    '2-10 fois' => '2-10 fois',
                    '> 10 fois' => '> 10 fois',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('complexInstrumentsVolume', ChoiceType::class, [
                'label' => 'Volume de transactions (moy. Annuelle des 24 derniers mois) sur Instruments complexes (ex: produits structurés, FIA, certificats)',
                'required' => false,
                'choices' => [
                    '< 50 K€' => '< 50 K€',
                    'de 50 K€ à 150 K€' => 'de 50 K€ à 150 K€',
                    '> 150 K€' => '> 150 K€',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MarketExperience::class
        ]);
    }
} 