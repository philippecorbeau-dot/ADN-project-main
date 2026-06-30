<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Info;
use App\Services\User\Info as InfoService;
use App\Services\User\Info as UserInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Step3Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Info $userInfo */
        $userInfo = $builder->getData();
        // Forcer l'initialisation des proxys Doctrine et LazyGhost avant tout accès
        try {
            if ($userInfo instanceof \ProxyManager\Proxy\LazyLoadingInterface) {
                $userInfo->initializeProxy();
            } elseif ($userInfo instanceof \Doctrine\Persistence\Proxy) {
                $userInfo->__load();
            } elseif ($userInfo instanceof \Symfony\Component\VarExporter\LazyObjectInterface) {
                $userInfo->initializeLazyObject();
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Champs directement mappés à l'entité Info, en valeurs numériques simples
        $builder
            ->add('salary', TextType::class, [
                'attr' => ['placeholder' => '0']
            ])
            ->add('accountSecurities', TextType::class, [
                'attr' => ['placeholder' => '0']
            ])
            // Décomposition du compte dépôt et épargne (affichée sous le champ patrimoine financier)
            ->add('depositSavingsChecking', TextType::class, [
                'attr' => ['placeholder' => 'Compte courant']
            ])
            ->add('depositSavingsLivretA', TextType::class, [
                'attr' => ['placeholder' => 'Livret A']
            ])
            ->add('depositSavingsLdd', TextType::class, [
                'attr' => ['placeholder' => 'LDD']
            ])
            ->add('depositSavingsCsl', TextType::class, [
                'attr' => ['placeholder' => 'CSL']
            ])
            ->add('depositSavingsOther', TextType::class, [
                'attr' => ['placeholder' => 'Autre livret']
            ])
            ->add('capitalisation', TextType::class, [
                'attr' => ['placeholder' => '0']
            ])
            // SCPI sera rendu sous la section Immobilier (déplacé dans le template)
            ->add('scpi', TextType::class, [
                'attr' => ['placeholder' => '0']
            ])
            ->add('realestateIncome', TextType::class, [
                'attr' => ['placeholder' => '0']
            ])
            ->add('realestate', TextType::class, [
                'attr' => ['placeholder' => '0']
            ])
            // Décomposition immobilière
            ->add('realestatePrimaryResidence', TextType::class, [
                'attr' => ['placeholder' => 'Résidence principale']
            ])
            ->add('realestateInvestment', TextType::class, [
                'attr' => ['placeholder' => "Immobilier d'investissement"]
            ])
            ->add('rent', TextType::class, [
                'attr' => ['placeholder' => '0']
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
