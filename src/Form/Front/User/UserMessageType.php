<?php

namespace App\Form\Front\User;

use App\Entity\Mail\UserMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => UserMessage::getCategoryOptions(),
                'attr' => [
                    'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-adn-blue focus:ring-adn-blue sm:text-sm'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner une catégorie'])
                ]
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet',
                'attr' => [
                    'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-adn-blue focus:ring-adn-blue sm:text-sm',
                    'placeholder' => 'Décrivez brièvement votre demande...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez saisir un objet']),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'L\'objet doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'L\'objet ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-adn-blue focus:ring-adn-blue sm:text-sm',
                    'rows' => 6,
                    'placeholder' => 'Détaillez votre demande ou question...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez saisir votre message']),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 2000,
                        'minMessage' => 'Le message doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le message ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => UserMessage::getPriorityOptions(),
                'data' => 'normal',
                'attr' => [
                    'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-adn-blue focus:ring-adn-blue sm:text-sm'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer le message',
                'attr' => [
                    'class' => 'w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-adn-blue hover:bg-adn-blue-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-adn-blue transition-colors duration-200'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserMessage::class,
        ]);
    }
}
