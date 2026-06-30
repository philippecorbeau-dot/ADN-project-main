<?php

namespace App\Form\Front\User;

use App\Entity\User\User;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractUserType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // On part d'un formulaire minimaliste pour l'inscription
        $builder
            ->add('email', EmailType::class, [
                'attr' => [
                    'placeholder' => 'pierre@adnfamilyoffice.fr',
                    'autocomplete' => 'username',
                ],
                'constraints' => [
                    new NotBlank([]),
                    new Email(message: 'cette adresse e-mail n\'est pas valide')
                ],
                'label' => 'Adresse email',
                'required' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label'       => 'Mot de passe',
                'mapped'      => false,
                'required'    => true,
                'attr'        => [
                    'placeholder'  => 'Mot de passe',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new NotBlank(message: 'Merci de saisir un mot de passe.'),
                    new Length(min: 12, minMessage: 'Au moins {{ limit }} caractères.'),
                ],
            ])

            ->add('landing', HiddenType::class, [
                'label' => 'Landing',
                'required' => true,
                'attr' => ['value' => null],
                'mapped' => false
            ])
            ->add('newsletter', CheckboxType::class, [
                'label' => "J'accepte de recevoir les opportunités d'investissement d'ADN Family Office",
                'required' => false,
                'mapped' => false,
                'data' => true
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Vous êtes un(e) :',
                'choices' => [
                    'Particulier' => User::USER_TYPE_PRIVATE,
                    'Personne morale' => User::USER_TYPE_PRO
                ],
                'expanded' => true,
                'label_attr' => ['class' => 'radio-inline']
            ])
            ->add('lastName', TextType::class, [
                'attr' => [
                    'placeholder' => 'Nom',
                ],
                'label' => 'Nom'])
            ->add('firstName', TextType::class, [
                'attr' => [
                    'placeholder' => 'Prénom',
                ],
                'label' => 'Prénom'])
        ;

        // Honeypot to avoid spam
        $builder->add('useremail', TextType::class, [
            'label' => "Nom d'utilisateur",
            'required' => false,
            'mapped' => false
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'email' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            // a unique key to help generate the secret token
            'csrf_token_id'   => 'registration',
        ]);
    }
}
