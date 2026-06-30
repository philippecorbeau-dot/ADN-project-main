<?php

namespace App\Form\Front\User\Create;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThan;

class KycStepDocumentsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $tomorrow = (new \DateTime('tomorrow'))->format('Y-m-d');
        $builder
            ->add('identityProof', FileType::class, [
                'label' => "Pièce d'identité (recto-verso, PDF/PNG/JPG, max 10 Mo)",
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/x-pdf',
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Formats acceptés : PDF, PNG, JPG, JPEG',
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
                    new GreaterThan([
                        'value' => 'today',
                        'message' => "La date d'expiration doit être dans le futur"
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'JJ/MM/AAAA',
                    'pattern' => '\\d{2}/\\d{2}/\\d{4}',
                    'class' => 'date-mask',
                ],
            ])
            ->add('addressProof', FileType::class, [
                'label' => 'Justificatif de domicile (moins de 3 mois, PDF/PNG/JPG, max 10 Mo)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/x-pdf',
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Formats acceptés : PDF, PNG, JPG, JPEG',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // 'data_class' => ... (à compléter si tu as une entité ou DTO)
        ]);
    }
} 