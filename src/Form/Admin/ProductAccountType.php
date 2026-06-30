<?php

namespace App\Form\Admin;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use App\Form\Admin\ProductContributionType;

class ProductAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '') . ' <' . $user->getEmail() . '>');
                },
                'placeholder' => 'Sélectionner un client',
                'required' => true,
            ])
            ->add('productType', ChoiceType::class, [
                'label' => 'Type de produit',
                'choices' => [
                    'Assurance-vie' => 'ASSURANCE_VIE',
                    'PER' => 'PER',
                    'PEA/PME' => 'PEA_PME',
                    'SCPI' => 'SCPI',
                    'Autre (manuel)…' => 'OTHER',
                ],
                'required' => true,
            ])
            ->add('productTypeOther', TextType::class, [
                'label' => 'Nouveau type (manuel)',
                'mapped' => false,
                'required' => false,
            ])
            ->add('distributor', ChoiceType::class, [
                'choices' => [
                    'Generali' => 'Generali',
                    'Spirica' => 'Spirica',
                    'Apicil' => 'Apicil',
                    'SwissLife' => 'SwissLife',
                    'Autre (manuel)…' => 'OTHER',
                ],
                'placeholder' => 'Choisir un distributeur',
                'required' => true,
            ])
            ->add('distributorOther', TextType::class, [
                'label' => 'Nouveau distributeur',
                'mapped' => false,
                'required' => false,
            ])
            // internalName supprimé: le type de produit devient l’identifiant logique côté admin
            ->add('displayAlias', TextType::class, [
                'required' => false,
                'label' => 'Alias affichage (optionnel)'
            ])
            ->add('euroFund', MoneyType::class, [
                'currency' => 'EUR',
                'required' => false,
                'label' => 'Fonds Euro (si Assurance-vie / PER)'
            ])
            ->add('fiscalDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Avantage fiscal daté'
            ])
            ->add('initialAmount', MoneyType::class, [
                'currency' => 'EUR',
                'required' => true,
                'label' => 'Montant initial'
            ])
            ->add('contributions', CollectionType::class, [
                'label' => 'Versements additionnels',
                'entry_type' => ProductContributionType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])
        ;

        // Normaliser les champs "Autre"
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            /** @var ProductAccount $data */
            $data = $event->getData();
            $form = $event->getForm();

            if ($data->getProductType() === 'OTHER') {
                $custom = trim((string) $form->get('productTypeOther')->getData());
                if ($custom !== '') {
                    $identifier = strtoupper(preg_replace('/[^A-Z0-9_]+/i', '_', $custom));
                    $identifier = substr($identifier, 0, 30);
                    $data->setProductType($identifier);
                    if (!$data->getDisplayAlias()) {
                        $data->setDisplayAlias($custom);
                    }
                }
            }

            if ($data->getDistributor() === 'OTHER') {
                $customD = trim((string) $form->get('distributorOther')->getData());
                if ($customD !== '') {
                    $data->setDistributor($customD);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductAccount::class,
        ]);
    }
}


