<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Entity\User\Pro;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttestProType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
       
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Pro::class
        ]);
    }
}
