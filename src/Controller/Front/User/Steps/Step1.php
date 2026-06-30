<?php

namespace App\Controller\Front\User\Steps;

use App\Entity\User\Pro;
use App\Entity\User\Pro\UboDeclaration;
use App\Entity\User\User;
use App\Form\Front\User\Create\Step1Type;
use App\Form\Front\User\Create\Step1ProType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

trait Step1
{
    protected function step1(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $isIdentified = $user->hasRole('ROLE_USER_IDENTIFIED') || $user->hasRole('ROLE_SUPER_ADMIN');
        $em = $this->entityManager;
        $uboService = $this->uboService;

        if ($user->isPro()) {

            if (!$pro = $user->getPro()) {
                $pro = new Pro();
            }

            $formOptions = [
                'is_identified' => $isIdentified,
                'existLegalRepresentativeFirstname' => !empty($pro->getLegalRepresentativeFirstname()),
                'existLegalRepresentativeLastname' => !empty($pro->getLegalRepresentativeLastname()),
            ];

            $form = $this->createForm(Step1ProType::class, $pro, $formOptions);
            $user->setPro($pro);

            if (!empty($user->getNationality()) and !$form->isSubmitted()) {
                $form->get('legalRepresentativeNationality')->setData($user->getNationality());
            }

            if (!empty($user->getCountry()) and !$form->isSubmitted()) {
                $form->get('legalRepresentativeCountry')->setData($user->getCountry());
            }

            if (!empty($user->getBirthday()) and !$form->isSubmitted()) {
                $form->get('legalRepresentativeBirthday')->setData($user->getBirthday());
            }

            // Obtenir les données des actionnaires avant de traiter le formulaire
            $uboDeclaration = $pro->getUboDeclaration() ?? new UboDeclaration();
            $existingShareholders = $uboService->getShareholdersDataArray($uboDeclaration->getUbos());

        } else {
            $form = $this->createForm(Step1Type::class, $user);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Si c'est juste une sauvegarde temporaire
            if ($request->request->get('_save_only')) {
                // Sauvegarder les données sans valider
                $em->persist($user);
                $em->flush();
                return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true]);
            }
            
            if ($form->isValid()) {
                // Traitement pour les utilisateurs professionnels
                if ($user->isPro()) {
                    // Récupérer toutes les données soumises via InputBag::all pour éviter l'erreur "non-scalar value"
                    $formPayload = $request->request->all('step1_pro') ?: [];

                    if (!empty($formPayload['legalRepresentativeNationality'])) {
                        $user->setNationality($formPayload['legalRepresentativeNationality']);
                    }

                    if (!empty($formPayload['legalRepresentativeCountry'])) {
                        $user->setCountry($formPayload['legalRepresentativeCountry']);
                    }

                    if (!empty($formPayload['legalRepresentativeBirthday'])) {
                        $birthdayRaw = (string) $formPayload['legalRepresentativeBirthday'];
                        $birthday = \DateTimeImmutable::createFromFormat('d/m/Y', $birthdayRaw) ?: \DateTimeImmutable::createFromFormat('Y-m-d', $birthdayRaw);
                        if ($birthday === false) {
                            // Fallback generique
                            $birthday = new \DateTimeImmutable($birthdayRaw);
                        }
                        $user->setBirthday($birthday);
                    }

                    // Récupérer les nouvelles données des actionnaires soumises
                    $submittedShareholders = $form->get('shareholdersInformations')->getData();
                    $submittedShareholdersData = $uboService->getShareholdersDataArray($submittedShareholders);
                    // Comparer les anciennes et les nouvelles données des actionnaires
                    if ($uboDeclaration->statusIsValidated() && $existingShareholders !== $submittedShareholdersData) {
                        $uboDeclaration = $uboService->createNewUboDeclaration($user, $submittedShareholders);
                    } else {
                        foreach ($submittedShareholders as $shareholder) {
                            $uboDeclaration->addUbo($shareholder);
                        }
                        $uboDeclaration->setPro($user->getPro());
                    }
                    // Gérer toute la logique des UBO
                    $rawUbos = $formPayload['shareholdersInformations'] ?? [];
                    $uboDeclaration = $uboService->handleAllUboLogic($user, $uboDeclaration, $submittedShareholders, $rawUbos);
                    // S'assurer que les dates obligatoires sont renseignées (sans appeler le getter typé qui peut renvoyer null)
                    if (method_exists($uboDeclaration, 'setCreatedAt')) {
                        try {
                            $uboDeclaration->setCreatedAt(new \DateTime());
                        } catch (\Throwable $e) {}
                    }
                    if (method_exists($uboDeclaration, 'setUpdatedAt')) {
                        try {
                            $uboDeclaration->setUpdatedAt(new \DateTime());
                        } catch (\Throwable $e) {}
                    }
                    foreach ($submittedShareholders as $shareholder) {
                        if (method_exists($shareholder, 'setCreatedAt')) {
                            try { $shareholder->setCreatedAt(new \DateTime()); } catch (\Throwable $e) {}
                        }
                        if (method_exists($shareholder, 'setUpdatedAt')) {
                            try { $shareholder->setUpdatedAt(new \DateTime()); } catch (\Throwable $e) {}
                        }
                    }

                    // Persister les entités
                    $em->persist($user->getPro());
                    $em->persist($uboDeclaration);
                    $em->flush();
                }
                
                // Permettre la progression vers l'étape 2 pour TOUS les utilisateurs
                // Même les utilisateurs identifiés peuvent mettre à jour leur KYC
                $currentStep = $user->getStepKyc() ?? 0;
                if ($currentStep < User::STEP_KYC_OBJECTIVES) {
                    $user->setStepKyc(User::STEP_KYC_OBJECTIVES);
                }
                
                // Enregistrer les informations (persister explicitement l'entité User)
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_OBJECTIVES]);
            }
        }

        return [
            'form' => $form->createView()
        ];
    }
}