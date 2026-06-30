<?php

namespace App\Controller;

use App\Entity\Mail\Contact;
use App\Repository\Mail\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormBuilderInterface;

class ContactController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ContactRepository $contactRepository;

    public function __construct(EntityManagerInterface $entityManager, ContactRepository $contactRepository)
    {
        $this->entityManager = $entityManager;
        $this->contactRepository = $contactRepository;
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $contact = new Contact();
        
        $form = $this->createFormBuilder($contact, [
                'csrf_protection' => true,
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom *',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Votre prénom'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Assert\Length(['min' => 2, 'max' => 50]),
                    new Assert\Regex([
                        'pattern' => '/^[\p{L}\p{M}\'\-\s]+$/u',
                        'message' => 'Le prénom contient des caractères non autorisés'
                    ]),
                ]
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom *',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Votre nom'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire']),
                    new Assert\Length(['min' => 2, 'max' => 50]),
                    new Assert\Regex([
                        'pattern' => '/^[\p{L}\p{M}\'\-\s]+$/u',
                        'message' => 'Le nom contient des caractères non autorisés'
                    ]),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'votre.email@exemple.com'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'email est obligatoire']),
                    new Assert\Email(['message' => 'Veuillez saisir un email valide']),
                    new Assert\Regex([
                        'pattern' => '/(\r|\n)/',
                        'match' => false,
                        'message' => 'Format d’email invalide'
                    ]),
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '+33 1 23 45 67 89'
                ],
                'constraints' => [
                    new Assert\Length(['max' => 25]),
                    new Assert\Regex([
                        'pattern' => '/^[0-9\+\-\s\(\)]{7,25}$/',
                        'message' => 'Veuillez saisir un numéro de téléphone valide'
                    ]),
                ]
            ])
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet de votre demande *',
                // Afficher les libellés lisibles et stocker la valeur numérique
                'choices' => array_flip(Contact::SUBJECT_LIST),
                'placeholder' => 'Sélectionnez un sujet',
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un sujet'])
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre message *',
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 6,
                    'placeholder' => 'Décrivez votre demande en détail...'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le message est obligatoire']),
                    new Assert\Length(['min' => 10, 'max' => 2000]),
                    new Assert\Regex([
                        'pattern' => '/[<>]/',
                        'match' => false,
                        'message' => 'Les balises HTML ne sont pas autorisées'
                    ]),
                ]
            ])
            // Honeypot anti-spam (invisible)
            ->add('website', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer ma demande',
                'attr' => [
                    'class' => 'btn-submit'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Honeypot: si rempli, ignorer (réponse neutre)
            if (trim((string) $form->get('website')->getData()) !== '') {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['success' => true, 'message' => 'OK']);
                }
                $this->addFlash('success', 'Message reçu.');
                return $this->redirectToRoute('app_contact', [], Response::HTTP_SEE_OTHER);
            }

            // Anti-flood simple: 10s mini entre 2 envois
            $session = $request->getSession();
            $lastTs = (int) ($session->get('contact_last_submit_ts') ?? 0);
            if (time() - $lastTs < 10) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['success' => true, 'message' => 'OK']);
                }
                $this->addFlash('success', 'Message reçu.');
                return $this->redirectToRoute('app_contact', [], Response::HTTP_SEE_OTHER);
            }
            $session->set('contact_last_submit_ts', time());

            // Sanitize serveur
            $contact->setFirstname($this->sanitizeName($contact->getFirstname()));
            $contact->setLastname($this->sanitizeName($contact->getLastname()));
            if ($contact->getPhone() !== null) {
                $contact->setPhone(trim((string) $contact->getPhone()));
            }
            $contact->setEmail(trim((string) $contact->getEmail()));
            if ($contact->getMessage() !== null) {
                $contact->setMessage($this->sanitizeMessage((string) $contact->getMessage()));
            }

            $this->entityManager->persist($contact);
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'message' => 'Votre message a été envoyé avec succès ! Nous vous recontacterons dans les plus brefs délais.'
                ]);
            }

            $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous recontacterons dans les plus brefs délais.');
            return $this->redirectToRoute('app_contact', ['sent' => 1], Response::HTTP_SEE_OTHER);
        }

        return $this->render('front/contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function sanitizeName(?string $value): ?string
    {
        if ($value === null) { return null; }
        $value = trim($value);
        return (string) preg_replace("/[^\\p{L}\\p{M}\\s'\\-]/u", '', $value);
    }

    private function sanitizeMessage(string $value): string
    {
        $clean = strip_tags($value);
        if (mb_strlen($clean) > 5000) {
            $clean = mb_substr($clean, 0, 5000);
        }
        return trim($clean);
    }
}
