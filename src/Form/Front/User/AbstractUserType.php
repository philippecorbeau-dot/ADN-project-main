<?php

namespace App\Form\Front\User;

use App\Entity\User\User;
use App\Services\Localization\Department;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractUserType extends AbstractType
{
    private $user;

    public function __construct()
    {
        $this->user = new User();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gender', ChoiceType::class, [
                'label' => 'Genre',
                'required' => true,
                'choices' => User::getGenders()
            ])
            ->add('gender', TextType::class, ['label' => 'Nom'])
            ->add('firstName', TextType::class, ['label' => 'Prénom'])
            ->add('birthday', BirthdayType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'years' => range(1900, date('Y') -11)
            ])
            /*            ->add('phone', TelType::class, ['label' => 'Téléphone portable',
                            'attr' => ['type' => 'tel']
                        ])*/
            ->add('birthplace', TextType::class, [
                'label' => 'Ville de naissance',
                'required' => true,
                'attr' => ['placeholder' => 'Paris']
            ])
            ->add('postalCodeBirthplace', HiddenType::class, [
                'label' => false,
            ])
            ->add('birthDepartment', ChoiceType::class, [
                'label' => 'Département de naissance',
                'choices' => Department::getFrenchDepartments()
            ])
            ->add('birthCountry', CountryType::class, [
                'label' => 'Pays de naissance',
                'preferred_choices' => ['FR']
            ])
            ->add('country', CountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR']
            ])
            ->add('addressLine1', null, [
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => "131, boulevard Brune"
                ],
                'required' => true
            ])
            ->add('addressLine2', null, [
                'label' => "Complément d'adresse",
            ])
            ->add('city', null, [
                'label' => 'Ville',
                'required' => true
            ])
            ->add('region', null, [
                'label' => 'Région',
            ])
            ->add('postalCode', null, [
                'label' => 'Code postal',
                'required' => true
            ])
            ->add('info', ChoiceType::class, [
                'label' => 'Êtes-vous, ou avez-vous été, (ou une personne de votre entourage) une personne politiquement exposée ?',
                'required' => true,
                'choices' => UserInfo::getBooleanChoices(),
                'expanded' => true,
            ])
            ->add(
                'maritalStatus', ChoiceType::class, [
                'label' => 'Quelle est votre situation maritale ?',
                'choices' => $this->user->getMaritalStatuses(),
                /* 'expanded' => true */
            ])
            ->setAction($options['url'])
            ->setMethod('GET')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['url' => null]);
    }
}