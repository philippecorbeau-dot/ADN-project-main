<?php

namespace App\Form\Admin;

use App\Entity\ProductContribution;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductContributionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('amount', MoneyType::class, [
                'currency' => 'EUR',
                'label' => 'Montant',
                'required' => true,
            ])
            ->add('contributionDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date',
                'required' => false,
                'attr' => [
                    'lang' => 'fr',
                ],
                'data' => new \DateTime('today'),
            ])
            ->add('note', TextType::class, [
                'label' => 'Note',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProductContribution::class,
        ]);
    }
}


