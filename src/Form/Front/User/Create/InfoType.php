<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('political', ChoiceType::class, [
                'label' => 'Êtes-vous, ou avez-vous été, (ou une personne de votre entourage) une personne politiquement exposée ?',
                'required' => true,
                
                'choices' => UserInfo::getBooleanChoices(),
                'expanded' => true,
            ])

            ->add('usPerson', ChoiceType::class, [
                'label' => 'Êtes-vous une US person ?',
                'required' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Info::class,
            'validation_groups' => ['investment'],
        ]);
    }
}
