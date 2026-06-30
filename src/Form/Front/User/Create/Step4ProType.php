<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Entity\User\Pro;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Step4ProType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        $builder
            ->add('mif', ChoiceType::class, [
                'label' => "Êtes-vous un investisseur professionnel selon la réglementation MIF2 ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'mapped' => false
            ])
        ;

        $builder
            ->add('awarenessBalanceSheet', ChoiceType::class, [
                'label' => "Avez-vous un total du bilan d’au moins 1 million d’euros ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'data' => false,
            ])
            ->add('awarenessTurnover', ChoiceType::class, [
                'label' => "Avez-vous un chiffre d’affaires net d’au moins 2 millions d’euros ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'data' => false,
            ])
            ->add('awarenessEquity', ChoiceType::class, [
                'label' => "Avez-vous des capitaux propres d’au moins 100.000 € ?",
                'required' => true,
                'expanded' => true,
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'data' => false,
            ])
        ;

        $builder
            ->add('attestMif', CheckboxType::class, [
                'label' => "Je suis un investisseur professionnel selon la réglementation MIF2",
                'required' => true,
                'mapped' => false,
                'data' => null
            ])
            ->add('attestBalanceSheet', CheckboxType::class, [
                'label' => "J’ai un total du bilan d’au moins 1 million d’euros",
                'required' => true,
                'data' => null
            ])
            ->add('attestTurnover', CheckboxType::class, [
                'label' => "J’ai un chiffre d’affaires net d’au moins 2 millions d’euros",
                'required' => true,
                'data' => null
            ])
            ->add('attestEquity', CheckboxType::class, [
                'label' => "J’ai des capitaux propres d’au moins 100 000 €",
                'required' => true,
                'data' => null
            ])
            ->add('attestAware', CheckboxType::class, [
                'label' => "Je déclare être conscient des conséquences de la perte de protection des investisseurs liée au statut d’investisseur non averti",
                'required' => true,
                'data' => null
            ])
            ->add('attestTruth', CheckboxType::class, [
                'label' => "Je déclare être responsable de la véracité des informations fournies dans la demande présente",
                'required' => true,
                'data' => null
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Pro::class,
        ]);
    }
}
