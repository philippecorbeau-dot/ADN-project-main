<?php

namespace App\Form\Front\User\Create;

use App\Entity\User\Pro;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThan;

class Step5ProType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        $builder
            ->add('identityProof', FileType::class, [
                'label' => "Pièce d'identité (recto-verso, PDF/PNG/JPG, max 10 Mo)",
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => ['application/pdf','application/x-pdf','image/png','image/jpeg','image/jpg'],
                        'mimeTypesMessage' => 'Formats autorisés: PDF, PNG, JPG, JPEG',
                    ])
                ],
            ])
            ->add('identityExpirationDate', DateType::class, [
                'label' => "Date d'expiration de la pièce d'identité",
                'mapped' => false,
                'required' => true,
                'widget' => 'single_text',
                'html5' => false,
                'format' => 'dd/MM/yyyy',
                'constraints' => [
                    new NotBlank(['message' => "La date d'expiration est requise"]),
                    new GreaterThan(['value' => 'today', 'message' => "La date d'expiration doit être dans le futur"]),
                ],
                'attr' => [
                    'placeholder' => 'JJ/MM/AAAA',
                    'pattern' => '\\d{2}/\\d{2}/\\d{4}',
                    'class' => 'date-mask',
                ],
            ])
            ->add('registrationProof', FileType::class, [
                'label' => "Extrait KBIS (PDF/PNG/JPG, max 10 Mo)",
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => ['application/pdf','image/png','image/jpeg','image/jpg'],
                        'mimeTypesMessage' => 'Formats autorisés: PDF, PNG, JPG, JPEG',
                    ])
                ],
            ])
            ->add('articlesOfAssociation', FileType::class, [
                'label' => "Statuts de la société (PDF/PNG/JPG, max 10 Mo)",
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => ['application/pdf','image/png','image/jpeg','image/jpg'],
                        'mimeTypesMessage' => 'Formats autorisés: PDF, PNG, JPG, JPEG',
                    ])
                ],
            ])
            ->add('shareholderDeclaration', FileType::class, [
                'label' => "Déclaration des bénéficiaires effectifs (PDF/PNG/JPG, max 10 Mo)",
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => ['application/pdf','image/png','image/jpeg','image/jpg'],
                        'mimeTypesMessage' => 'Formats autorisés: PDF, PNG, JPG, JPEG',
                    ])
                ],
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
