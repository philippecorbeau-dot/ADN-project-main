<?php

namespace App\Form\Front\User\Create;

use App\Form\Front\User\Create\InfoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use App\Form\Type\ModernCountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\User\User;
use Symfony\Component\Validator\Constraints as Assert;

class Step1Type extends AbstractType
{
    private $user;

    public function __construct()
    {
        $this->user = new User();
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $user = $options['data'];
        $birthday = $user->getBirthday();
        $isNewUser = is_null($user->getGender()) || is_null($birthday);
        $dataRegion = $user->getRegion();
        $dataPostalCode = $user->getPostalCode();
        $dataBirthLastName = $user->getBirthLastName();
        if ($isNewUser) {
            $region = (bool) $dataRegion;
            $postalCode = (bool) $dataPostalCode;
        } else {
            $region = is_null($dataRegion);
            $postalCode = is_null($dataPostalCode);
        }
        $todayMinus18Years = new \DateTime();
        $todayMinus18Years->sub(new \DateInterval('P18Y'));
        $identified = $user->getIdentified();
        $colorText = $identified ? ' text-gray-500' : '';
        $lockLastName = !empty($user->getLastName());
        $lockFirstName = !empty($user->getFirstName());

        $builder
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'required' => true,
                'choices' => User::getGenders(),
                'attr' => [
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out border-gray-300 identified-css' . $colorText
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'disabled' => $lockLastName,
                'attr' => [
                    'placeholder' => 'Saisir votre nom',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ],
                'help' => $lockLastName ? 'Le nom ne peut pas être modifié. Contactez le support en cas d\'erreur.' : null,
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'disabled' => $lockFirstName,
                'attr' => [
                    'placeholder' => 'Saisir votre prénnom',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ],
                'help' => $lockFirstName ? 'Le prénom ne peut pas être modifié. Contactez le support en cas d\'erreur.' : null,
            ])
            ->add('birthLastName', TextType::class, [
                'label' => 'Nom de naissance',
                'attr' => [
                    'placeholder' => 'Saisir votre nom de naissance',
                    'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                ],
            ])
            ->add('birthday', BirthdayType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'years' => range(1900, date('Y') - 18),
                'format' => 'dd/MM/yyyy',
                'html5' => false,
                'attr' => [
                    'max' => date('Y-m-d', strtotime('-18 years')),
                    'placeholder' => 'jj/mm/aaaa',
                    'pattern' => '[0-9]{2}/[0-9]{2}/[0-9]{4}',
                    'class' => 'date-input-french'
                ],
                'data' => $birthday > $todayMinus18Years ? null : $birthday,
            ])
            ->add('phone', TelType::class, [
                    'label' => 'Téléphone portable',
                    'constraints' => [
                        new Assert\Regex([
                            'pattern' => '/^\+\d{1,3}\d{4,14}(?:x.+)?$/',
                            'message' => "L'indicatif de votre numéro de téléphone n'est pas valide.",
                        ]),
                    ],
                    'attr' => [
                        'placeholder' => '06 XX XX XX XX',
                        'class' => 'block w-full rounded-lg border-homblue-normal/50 shadow-sm focus:border-homblue-normal focus:ring focus:ring-homblue-alternate focus:ring-opacity-10  transition-all ease-in-out identified-css'
                    ],
                ]
            )
            ->add('birthplace', TextType::class, [
                'label' => 'Ville de naissance',
                'attr' => [
                    'placeholder' => 'Saisir votre ville',
                    'id' => 'user_birthplace',
                ]
            ])
            ->add('nationality', ModernCountryType::class, [
                'label' => 'Pays de naissance',
                'preferred_choices' => ['FR'],
                'placeholder' => 'Sélectionner un pays'
            ])
            ->add('postalCodeBirthplace', TextType::class, [
                'label' => 'Code postal de naissance',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Saisir votre code postal',
                    'data-postalcodebirthplace' => '',
                ]
            ])
            ->add('inseeCodeBirthplace', HiddenType::class, [
                'label' => false,
            ])
            ->add('country', ModernCountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR'],
                'placeholder' => 'Sélectionner un pays'
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Saisir votre ville'
                ]
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Saisir votre code postal',
                ]
            ])
            // Champ région supprimé car non indispensable
            ->add('addressLine1', null, [
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Saisir votre adresse postale'
                ],
                'required' => true
            ])
            ->add('addressLine2', null, [
                'label' => "Complément d'adresse (facultatif)",
                'attr' => [
                    'placeholder' => 'Ex : Bâtiment A, 2e étage...',
                ],
            ])
            ->add('inseeCode', HiddenType::class, [
                'label' => false,
            ])
            ->add('info', InfoType::class, [
                'label' => false
            ])
            ->add('profession', ChoiceType::class, [
                'choices' => $this->user->getProfessions(),
                'label' => 'Situation professionnelle',
                'required' => false
            ])
            ->add('maritalStatus', ChoiceType::class, [
                'label' => 'Quelle est votre situation familiale ?',
                'choices' => $this->user->getMaritalStatuses(),
                /* 'expanded' => true */
            ])
            ->add('taxResidence', ModernCountryType::class, [
                'label' => 'Résidence fiscale',
                'preferred_choices' => ['FR'],
                'placeholder' => 'Sélectionner un pays'
            ])
        ;

        // Supprimer les validations restrictives pour permettre la mise à jour du KYC
        // Les utilisateurs identifiés peuvent maintenant modifier leurs informations
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class
        ]);
    }
}
