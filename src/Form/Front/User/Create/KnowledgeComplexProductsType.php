<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Knowledge\ComplexProductsKnowledge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KnowledgeComplexProductsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('question1', ChoiceType::class, [
                'label' => 'Les fonds alternatifs et les fonds à formule sont considérés comme des OPC complexes ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question2', ChoiceType::class, [
                'label' => 'Un FCPI/FIP/FCPR est un fonds qui permet d\'investir dans des entreprises non cotées et de bénéficier d\'un abattement fiscal ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question3', ChoiceType::class, [
                'label' => 'Il est possible de sortir d\'un FCPI/FIP/FCPR à tout moment, même si cela fait perdre l\'éventuel avantage fiscal lié au produit ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question4', ChoiceType::class, [
                'label' => 'Un produit structuré est exposé à un risque de perte en capital, un risque de marché, un risque de liquidité et un risque de contrepartie ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question5', ChoiceType::class, [
                'label' => 'Les produits structurés garantissent toujours au moins 90% du capital à échéance ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question6', ChoiceType::class, [
                'label' => 'En général, la durée de vie d\'un produit structuré est fixée au départ et c\'est à son échéance, qui peut être variable, que l\'investisseur récupère son capital majoré ou minoré en fonction de la performance du sous-jacent ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question7', ChoiceType::class, [
                'label' => 'Une SCPI est un produit financier investi sur des biens immobiliers qui offre un revenu régulier du fait de la perception des loyers de ces biens ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question8', ChoiceType::class, [
                'label' => 'Il est possible de perdre de l\'argent avec une SCPI ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question9', ChoiceType::class, [
                'label' => 'La valeur d\'un OPCI (Organisme de Placement Collectif Immobilier) reste en partie liée à l\'évolution des marchés financiers, contrairement à celle d\'une SCPI (Société Civile de Placement Immobilier) ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('question10', ChoiceType::class, [
                'label' => 'Les produits à effet de levier sont beaucoup plus risqués que les produits conventionnels et amplifient les hausses comme les baisses ?',
                'required' => true,
                'choices' => [
                    'Vrai' => 'true',
                    'Faux' => 'false',
                    'Je ne sais pas' => 'unknown',
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
            'data_class' => ComplexProductsKnowledge::class
        ]);
    }
} 