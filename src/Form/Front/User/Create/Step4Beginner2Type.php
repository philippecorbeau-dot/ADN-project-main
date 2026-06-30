<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;

class Step4Beginner2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $info = new Info();
        $builder
            ->add('adequacy1', ChoiceType::class, [
                'label' => "Une action ou une part sociale correspond à...",
                'choices' => $info->getAdequacyChoices(),
                'expanded' => true
            ])

            ->add('adequacy2', ChoiceType::class, [
                'label' => "Une obligation correspond à...",
                'choices' => $info->getAdequacyChoices(),
                'expanded' => true
            ])

            ->add('adequacy3', ChoiceType::class, [
                'label' => "Un investissement dans un projet de financement participatif entraine-t-il systématiquement une plus-value ?",
                'choices' => [
                    'Oui' => true,
                    'Non' => false
                ],
                'expanded' => true
            ])

            ->add('adequacy4', ChoiceType::class, [
                'label' => "Une obligation présentant un taux de 10 % est généralement plus risquée qu'une obligation portant sur un taux de 7 % ?",
                'choices' => [
                    'Oui' => true,
                    'Non' => false
                ],
                'expanded' => true
            ])
            ->add('adequacy5', ChoiceType::class, [
                'label' => "Quel niveau de risque êtes-vous prêt à supporter ?",
                'choices' => $info->getAdequacy5Choices(),
                'expanded' => true,
            ])
            ->add('accompaniment', CheckboxType::class, [
                'label' => "Je consens à transmettre ce résultat à Homunity afin d’être mieux accompagné",
            ])
        ;
        
        $builder
            ->add('attestAware', CheckboxType::class, [
            'label' => "Je suis conscient que l'investissement projeté peut représenter un risque de perte partielle voire totale du capital",
            'required' => true,
        ])
        ->add('attestTruth', CheckboxType::class, [
            'label' => "Je suis conscient que l'investissement projeté peut présenter un risque d'illiquidité qui peut vous empêcher de revendre vos titres au moment souhaité",
            'required' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Info::class
        ]);
    }
}
