<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Pro;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Step3ProType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $resultDate = new \DateTime();
        $resultDate->setTimestamp(strtotime("-1 year"));

        $caDate = new \DateTime();

        $builder
            ->add('turnover', TextType::class, [
                'label' => sprintf("Quel est votre chiffre d'affaires de %s ?",  $resultDate->format('Y')),
                'required' => true,
                'attr' => [
                    'min' => '0'
                ]
            ])
            ->add('oldResult', TextType::class, [
                'label' => sprintf('Quel est votre résultat de %s ?', $resultDate->format('Y')),
                'required' => true,
                'attr' => [
                    'min' => '0'
                ]
            ])
            ->add('forecastTurnover', TextType::class, [
                'label' => sprintf('Quel est votre prévision de CA pour %s ?', $caDate->format('Y')),
                'required' => true,
                'attr' => [
                    'min' => '0'
                ]
            ])
            ->add('capital', TextType::class, [
                'label' => 'Quel est votre capital social ?',
                'required' => true,
                'attr' => [
                    'min' => '0'
                ]
            ])
            ->add('stocks', TextType::class, [
                'label' => 'Quels sont vos réserves ?',
                'required' => true,
                'attr' => [
                    'min' => '0'
                ]
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
