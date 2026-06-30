<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Knowledge\FinancialProductsKnowledge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KnowledgeFinancialProductsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('question1', ChoiceType::class, [
                'label' => 'Quand je détiens une action d\'une société, je lui prête de l\'argent et suis rémunéré par les intérêts de ce prêt ?',
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
                'label' => 'Quand une action donne un dividende, le cours de cette action diminue de la valeur de ce dividende ?',
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
                'label' => 'Une obligation est toujours intégralement remboursée par son émetteur ?',
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
                'label' => 'A maturité égale, une obligation notée AAA offre normalement un meilleur rendement qu\'une obligation notée B ?',
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
                'label' => 'Les OPCVM investis en actions sont généralement plus risqués que les OPCVM investis en obligations ?',
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
                'label' => 'Un ETF est un produit financier dont la vocation est de reproduire le plus fidèlement possible le comportement d\'un indice ?',
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
                'label' => 'La volatilité d\'un fonds est un bon indicateur de son niveau de risque ?',
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
                'label' => 'Comme son nom l\'indique, un fonds à performance absolue ne peut jamais avoir une performance négative ?',
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
            'data_class' => FinancialProductsKnowledge::class
        ]);
    }
} 