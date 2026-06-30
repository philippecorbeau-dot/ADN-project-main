<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Step4Type extends AbstractType
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
            ])
        ;

        $builder
            ->add('attestMif', CheckboxType::class, [
            'label' => "Je suis un investisseur professionnel selon la réglementation MIF2",
            'required' => false,
            // Doit être persisté dans Info (colonne attest_mif)
            'mapped' => true,
            'data' => null
            ])
            ->add('attestAware', CheckboxType::class, [
                'label' => "Je déclare être conscient des conséquences de la perte de protection des investisseurs liée au statut d'investisseur non averti",
                'required' => false,
                'data' => null
            ])
            ->add('attestTruth', CheckboxType::class, [
                'label' => "Je déclare être responsable de la véracité des informations fournies dans la demande présente",
                'required' => false,
                'data' => null
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Info::class
        ]);
    }
}
