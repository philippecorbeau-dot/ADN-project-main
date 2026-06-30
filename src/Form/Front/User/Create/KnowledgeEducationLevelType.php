<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Knowledge\EducationLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KnowledgeEducationLevelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('level', ChoiceType::class, [
                'label' => 'Quel est votre niveau d\'études ? (Art. 55 du Règlement Délégué MIF 2 2017/565)',
                'required' => true,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'Baccalauréat' => 'Baccalauréat',
                    'Bac +2 (DUT, BTS)' => 'Bac +2 (DUT, BTS)',
                    'Bac +3 (Licence)' => 'Bac +3 (Licence)',
                    'Bac +4 (Maîtrise)' => 'Bac +4 (Maîtrise)',
                    'Bac +5 (Master, Ingénieur)' => 'Bac +5 (Master, Ingénieur)',
                    'Bac +8 (Doctorat)' => 'Bac +8 (Doctorat)',
                    'Autre' => 'Autre',
                ],
                'attr' => [
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EducationLevel::class
        ]);
    }
} 