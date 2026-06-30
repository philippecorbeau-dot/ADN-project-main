<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Pro;
use App\Entity\User\User;
use App\Form\Front\User\Pro\ShareholderType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class Step1ProType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isIdentified = $options['is_identified'];

        $builder
            ->add('companyName', TextType::class, [
                    'label' => 'Dénomination sociale',
                    'attr' => [
                        'readonly' => $isIdentified,
                        'placeholder' => 'SARL Dupont',
                        'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                    ]
                ]
            )
            ->add('siren', TextType::class, [
                'label' => 'N° de SIREN',
                'attr' => [
                    'readonly' => $isIdentified,
                    'pattern' => '[0-9]{3}[ \.\-]?[0-9]{3}[ \.\-]?[0-9]{3}',
                    'placeholder' => '444 555 666',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ]
            ])
            ->add('phone', TelType::class, [
                    'label' => 'Téléphone portable',
                    'constraints' => [
                        new Assert\Regex([
                            'pattern' => '/^\+\d{1,3}\d{4,14}(?:x.+)?$/',
                        ]),
                    ],
                    'attr' => [
                        'readonly' => $isIdentified,
                        'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                    ],
                ]
            )
            ->add('country', CountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR']
            ])
            ->add('addressLine1', TextType::class, [
                'label' => 'Adresse',
                'required' => true,
                'attr' => [
                    'placeholder' => "12, rue Jacquemont"
                ],
            ])
            ->add('addressLine2', TextType::class, [
                'label' => "Complément d'adresse",
                'required' => false
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => true,
                'attr' => [
                    'placeholder' => '75017'
                ]
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Paris'
                ]
            ])
            // Champ région supprimé car non indispensable
            ->add('socialObject', TextareaType::class, ['label' => 'Objet social synthétisé'])
            ->add('legalRepresentativeFirstname', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
                'attr' => [
                    'readonly' => ($isIdentified && $options['existLegalRepresentativeFirstname']) ? $isIdentified : false,
                    'placeholder' => 'Paul',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ]
            ])
            ->add('legalRepresentativeLastname', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'readonly' => ($isIdentified && $options['existLegalRepresentativeLastname']) ? $isIdentified : false,
                    'placeholder' => 'Dupont',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ]
            ])
            ->add('legalRepresentativeNationality', CountryType::class, [
                'label' => 'Pays de naissance',
                'preferred_choices' => ['FR'],
                'attr' => [
                    'placeholder' => 'Paris',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ],
                'mapped' => false
            ])
            ->add('legalRepresentativeCountry', CountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR'],
                'attr' => [
                    'placeholder' => 'Paris',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ],
                'mapped' => false
            ])
            ->add('legalRepresentativeBirthday', BirthdayType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'years' => range(1900, date('Y') -11),
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'attr' => [
                    'readonly' => $isIdentified,
                    'max' =>  date('Y-m-d', strtotime('-11 years')),
                    'placeholder' => 'jj/mm/aaaa',
                    'pattern' => '[0-9]{2}/[0-9]{2}/[0-9]{4}',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css date-input-french'
                ],
                'mapped' => false
            ])
            ->add('shareholdersInformations', CollectionType::class, [
                'label' => false,
                'entry_type' => ShareholderType::class,
                'by_reference' => false,
                'prototype' => true,
                'allow_add' => true,
                'allow_delete' => true
            ])
        ;

        if ($isIdentified) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($builder) {
                /** @var Pro $userEntity */
                $userEntity = $event->getData();
                $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($userEntity) {
                    $userForm = $event->getData();
                    if ($userEntity->getCompanyName() !== $userForm['companyName']) {
                        $event->getForm()->addError(new FormError('Impossible de changer la Dénomination sociale'));
                    }
                    if ($userEntity->getSiren() !== $userForm['siren']) {
                        $event->getForm()->addError(new FormError('Impossible de changer le Siren'));
                    }
                    if ($userEntity->getPhone() !== $userForm['phone']) {
                        $event->getForm()->addError(new FormError('Impossible de changer le téléphone portable'));
                    }
                    if (
                        !empty($userEntity->getLegalRepresentativeFirstname())
                        && $userEntity->getLegalRepresentativeFirstname() !== $userForm['legalRepresentativeFirstname']
                    ) {
                        $event->getForm()->addError(new FormError('Impossible de changer le prénom du representant légal'));
                    }
                    if (
                        !empty($userEntity->getLegalRepresentativeLastname())
                        && $userEntity->getLegalRepresentativeLastname() !== $userForm['legalRepresentativeLastname']
                    ) {
                        $event->getForm()->addError(new FormError('Impossible de changer le nom du representant légal'));
                    }
                });
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Pro::class,
            'is_identified' => false,
            'existLegalRepresentativeFirstname' => false,
            'existLegalRepresentativeLastname' => false,
        ]);
    }
}
