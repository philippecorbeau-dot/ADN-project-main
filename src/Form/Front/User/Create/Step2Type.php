<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Services\User\Info as InfoService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\User\User;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Step2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $info = new Info();
        $objectiveList = $options['data']->getObjectiveList();
        $valuesObjective = [];
        $objectives = $options['data']->getObjective();
        if ($objectives !== null) {
            foreach ($objectives as $objective) {
                if (array_key_exists($objective, $objectiveList)) {
                    $valuesObjective[] = $objective;
                } else {
                    $valuesObjective[] = Info::LIFEINSURANCE_OBJECTIVE_CONVERT_VALUES_TO_HOMUNITY_VALUES[$objective];
                }
            }
        }
        $sourceOfFundsList = InfoService::getSourceOfFundsChoices();
        $valuesSourceOfFunds = [];
        $sourceOfFunds = $options['data']->getSourceOfFunds();
        if ($sourceOfFunds !== null) {
            foreach ($sourceOfFunds as $sourceOfFund) {
                if (in_array($sourceOfFund, $sourceOfFundsList)) {
                    $valuesSourceOfFunds[] = $sourceOfFund;
                } else {
                    $valuesSourceOfFunds[] = InfoService::LIFEINSURANCE_SOURCE_OF_FUNDS_CONVERT_VALUES_TO_HOMUNITY_VALUES[$sourceOfFund];
                }
            }
        }

        $builder
            ->add('objective', ChoiceType::class, [
                'label' => "Pourquoi souhaitez-vous investir ?",
                'choices' => array_flip($info->getObjectiveList()),
                'expanded' => true,
                'multiple' => true,
                'validation_groups' => ['createprofile'],
                'data' => $valuesObjective,
            ])
            ->add('investmentTerm', ChoiceType::class, [
                'label' => "Sur combien de temps souhaitez-vous investir ?",
                'required' => true,
                'choices' => [
                    'Moins de 2 ans' => 0,
                    'Entre 2 et 6 ans' => 1,
                    'Plus de 6 ans' => 2,
                    'Je ne sais pas encore' => 3,
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('liquidity', MoneyType::class, [
                'label' => "Quelle est votre épargne disponible ?",
                'attr' => [
                    'placeholder' => 'ex : 10 000',
                ],
                'grouping' => true,
                'scale' => 0,
                'currency' => false,
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'La valeur doit être égale ou supérieure à 1€'
                    ])
                ],
            ])
            ->add('sourceOfFunds', ChoiceType::class, [
                'label' => "D'où provient le montant que vous souhaitez investir ?",
                'choices' => InfoService::getSourceOfFundsChoices(),
                'expanded' => true,
                'multiple' => true,
                'validation_groups' => ['createprofile'],
                'data' => $valuesSourceOfFunds,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Info::class,
            'validation_groups' => ['Default', 'createprofile']
        ]);
    }
}
