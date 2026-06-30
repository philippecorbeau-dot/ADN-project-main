<?php

namespace App\Form\Front\User\Pro;

use App\Entity\User\Pro\ShareholdersInformation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShareholderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Alexandre',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Martin',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('addressLine1', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'placeholder' => '10 Avenue de la République',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('addressLine2', TextType::class, [
                'label' => "Complément d'adresse (facultatif)",
                'required' => false,
                'attr' => [
                    'placeholder' => 'Bâtiment A, 2e étage…',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Lyon',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('region', TextType::class, [
                'label' => 'Région',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Auvergne-Rhône-Alpes',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'placeholder' => '69001',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('country', CountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR'],
                'required' => false,
            ])
            ->add('nationality', CountryType::class, [
                'label' => 'Nationalité',
                'preferred_choices' => ['FR'],
                'required' => false,
            ])
            ->add('birthday', BirthdayType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'years' => range(1900, (int) date('Y') - 11),
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'required' => false,
                'attr' => [
                    'max' => date('Y-m-d', strtotime('-11 years')),
                    'placeholder' => 'jj/mm/aaaa',
                    'pattern' => '[0-9]{2}/[0-9]{2}/[0-9]{4}',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out date-input-french'
                ],
            ])
            ->add('birthplace', TextType::class, [
                'label' => 'Ville de naissance',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Lyon',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
            ->add('birthDepartment', TextType::class, [
                'label' => 'Département de naissance',
                'required' => false,
                'attr' => [
                    'placeholder' => '69',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10 transition-all ease-in-out'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ShareholdersInformation::class,
        ]);
    }
}


