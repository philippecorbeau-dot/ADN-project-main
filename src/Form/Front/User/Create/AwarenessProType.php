<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Entity\User\Pro;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AwarenessProType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        $builder
            ->add('awarenessBalanceSheet', ChoiceType::class, [
                'label' => "Avez-vous un total du bilan d’au moins 1 million d’euros ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
            ])
            ->add('awarenessTurnover', ChoiceType::class, [
                'label' => "Avez-vous un chiffre d’affaires net d’au moins 2 millions d’euros ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
            ])
            ->add('awarenessEquity', ChoiceType::class, [
                'label' => "Avez-vous des capitaux propres d’au moins 100.000 € ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ], 
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Pro::class
        ]);
    }
}
