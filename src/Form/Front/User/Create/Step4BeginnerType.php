<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class Step4BeginnerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('alreadyInvest', ChoiceType::class, [
                'label' => "Avez-vous déjà investi dans l'immobilier ?",
                'required' => true,
                'choices' => UserInfo::getBooleanChoices(),
                'expanded' => true,
            ])
            ->add('investType', ChoiceType::class, [
                'label' => "Si oui lesquels ?",
                'required' => false,
                'choices' => UserInfo::getInvestTypeChoices(),
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('securities', ChoiceType::class, [
                'label' => 'Avez-vous déjà réalisé des opérations sur des instruments financiers ?',
                'required' => true,
                'choices' => UserInfo::getBooleanChoices(),
                'expanded' => true,
            ])
            ->add('securitiesOptions', ChoiceType::class, [
                'label' => 'Si oui lesquels ?',
                'required' => false,
                'choices' => InfoService::getSecuritiesTypesChoices(),
                'expanded' => true,
                'multiple' => true
            ])
            ->add('securitiesOptionsCount', ChoiceType::class, [
                'label' => "Si oui, combien d'opérations ?",
                'required' => false,
                'choices' => InfoService::getSecuritiesTypesCountChoices(),
                'expanded' => true,
            ])
        ;

        // Validation conditionnelle: rendre certains champs obligatoires uniquement
        // si la réponse parent est "Oui".
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData() ?? [];
            $form = $event->getForm();

            $securities = isset($data['securities']) ? (bool)$data['securities'] : null;
            if ($securities === true) {
                // Recréer les champs dépendants en required=true
                if ($form->has('securitiesOptions')) {
                    $config = $form->get('securitiesOptions')->getConfig();
                    $form->add('securitiesOptions', ChoiceType::class, array_replace($config->getOptions(), [
                        'required' => true,
                    ]));
                }
                if ($form->has('securitiesOptionsCount')) {
                    $config = $form->get('securitiesOptionsCount')->getConfig();
                    $form->add('securitiesOptionsCount', ChoiceType::class, array_replace($config->getOptions(), [
                        'required' => true,
                    ]));
                }
            }

            $alreadyInvest = isset($data['alreadyInvest']) ? (bool)$data['alreadyInvest'] : null;
            if ($alreadyInvest === true) {
                if ($form->has('investType')) {
                    $config = $form->get('investType')->getConfig();
                    $form->add('investType', ChoiceType::class, array_replace($config->getOptions(), [
                        'required' => true,
                    ]));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Info::class
        ]);
    }
}
