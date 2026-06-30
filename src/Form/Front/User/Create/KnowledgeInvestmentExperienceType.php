<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Knowledge\InvestmentExperience;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KnowledgeInvestmentExperienceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('hasLostSignificantAmounts', ChoiceType::class, [
                'label' => 'Avez-vous déjà perdu des sommes significatives en bourse ?',
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
            ->add('portfolioLossPercentage', IntegerType::class, [
                'label' => 'Quel est le pourcentage de votre portefeuille que vous avez perdu ?',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 25',
                    'min' => 0,
                    'max' => 100,
                    'class' => 'mt-2 block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('managesOwnPortfolio', ChoiceType::class, [
                'label' => 'Gérez-vous vous-même votre portefeuille ?',
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
            ->add('portfolioSecuritiesLines', IntegerType::class, [
                'label' => 'Sur combien de lignes de titres est réparti votre portefeuille ?',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 10',
                    'min' => 1,
                    'max' => 1000,
                    'class' => 'mt-2 block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('concentratesOnSingleSecurity', ChoiceType::class, [
                'label' => 'Vous arrive-t-il de concentrer tout le portefeuille sur un seul titre ?',
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
            ->add('appropriatenessTestPerformed', ChoiceType::class, [
                'label' => 'Le test de caractère approprié est réalisé à chaque fois que je passe un ordre d\'achat sur produit complexe ?',
                'required' => true,
                'choices' => [
                    'Vrai' => true,
                    'Faux' => false,
                    'Je ne sais pas' => null,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'space-y-2'
                ]
            ])
            ->add('ordersThroughCif', ChoiceType::class, [
                'label' => 'Les ordres sur titre vif et/ou produit structuré ne doivent pas transiter par mon CIF ?',
                'required' => true,
                'choices' => [
                    'Vrai' => true,
                    'Faux' => false,
                    'Je ne sais pas' => null,
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
            'data_class' => InvestmentExperience::class
        ]);
    }
} 