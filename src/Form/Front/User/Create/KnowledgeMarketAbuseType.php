<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Knowledge\MarketAbuse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KnowledgeMarketAbuseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('hasOtherSecuritiesAccounts', ChoiceType::class, [
                'label' => 'Avez-vous d\'autres comptes-titres ?',
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
            ->add('hasFinancialProfession', ChoiceType::class, [
                'label' => 'Exercez-vous ou avez-vous exercé une profession dans le domaine financier ou boursier ?',
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
            ->add('professionDetails', TextareaType::class, [
                'label' => 'Précision de la profession',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Précisez votre profession dans le domaine financier...',
                    'rows' => 3,
                    'class' => 'mt-2'
                ]
            ])
            ->add('isListedCompanyDirector', ChoiceType::class, [
                'label' => 'Êtes-vous dirigeant d\'une société cotée ou de la maison mère d\'une société cotée ?',
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
            ->add('listedCompanyDetails', TextareaType::class, [
                'label' => 'Précision de la société cotée',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Précisez la société cotée...',
                    'rows' => 3,
                    'class' => 'mt-2'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MarketAbuse::class
        ]);
    }
} 