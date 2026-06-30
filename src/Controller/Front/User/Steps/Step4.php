<?php

namespace App\Controller\Front\User\Steps;

use App\Entity\User\Info;
use App\Entity\User\User;
use App\Entity\User\Knowledge\InvestorKnowledge;
use App\Entity\User\Knowledge\MarketAbuse;
use App\Entity\User\Knowledge\EducationLevel;
use App\Entity\User\Knowledge\InvestmentExperience;
use App\Entity\User\Knowledge\FinancialProductsKnowledge;
use App\Entity\User\Knowledge\ComplexProductsKnowledge;
use App\Entity\User\Knowledge\MarketExperience;
use App\Form\Front\User\Create\Step4Beginner2Type;
use App\Form\Front\User\Create\Step4BeginnerType;
use App\Form\Front\User\Create\Step4ProType;
use App\Form\Front\User\Create\Step4Type;
use App\Form\Front\User\Create\KnowledgeMarketAbuseType;
use App\Form\Front\User\Create\KnowledgeEducationLevelType;
use App\Form\Front\User\Create\KnowledgeInvestmentExperienceType;
use App\Form\Front\User\Create\KnowledgeFinancialProductsType;
use App\Form\Front\User\Create\KnowledgeComplexProductsType;
use App\Form\Front\User\Create\KnowledgeMarketExperienceType;
use App\Services\User\InvestorProfileScorer;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

trait Step4
{
    protected function step4(Request $request): array|RedirectResponse
    {
        
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isPro()) {
            // Unification: utiliser le même formulaire que le parcours naturel
            if (empty($user->getInfo())) {
                $user->setInfo(new Info());
            }
            $form = $this->createForm(Step4Type::class, $user->getInfo());
        } else {
            $form = $this->createForm(Step4Type::class, $user->getInfo());
        }
        
        $em = $this->entityManager;
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
                // Persister Info/Pro
                if ($user->isPro()) {
                    $em->persist($user->getPro());
                } else {
                    $em->persist($user->getInfo());
                }
                $em->persist($user);
                $em->flush();

                // Si MIF2 = Oui → attester puis aller documents; sinon → parcours débutant
                $isMif = $user->getInfo() ? $user->getInfo()->isMif() : false;
                if ($isMif === true) {
                    // Attestations requises: si cochées, passer à l'étape 5 (documents)
                    $info = $user->getInfo();
                    if ($info && $info->getAttestMif() && $info->isAttestAware() && $info->isAttestTruth()) {
                        // Booster automatiquement les connaissances à 100% pour les investisseurs professionnels (MIF2)
                        $this->ensureMaxKnowledgeForProfessional($user);
                        if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                            $user->setStepKyc(User::STEP_KYC_DOCUMENTS);
                        }
                        $em->persist($user);
                        $em->flush();
                        return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_DOCUMENTS]);
                    }
                    // Sinon rester sur Step 4 pour compléter les attestations
                    return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE]);
                }

                // Non MIF2: activer parcours débutant (beginner → beginner2 → questionnaires)
                $user->setIsAwareProfile(false);
                if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                    $user->setStepKyc(User::STEP_KYC_EXPERIENCE);
                }
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'beginner']);
            }
        }

        return [
            'form' => $form->createView()
        ];

    }

    protected function step4Beginner(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();

        $form = $this->createForm(Step4BeginnerType::class, $user->getInfo());
        $em = $this->entityManager;
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
                if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                    $user->setStepKyc(User::STEP_KYC_EXPERIENCE);
                }
                // Recalcul progressif du profil investisseur (normalisé, robuste)
                $this->profileScorer->calculateAndUpdateProfile($user);
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'beginner2']);
            }
        }

        return [
            'form' => $form->createView()
        ];

    }

    protected function step4Beginner2(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();

        $form = $this->createForm(Step4Beginner2Type::class, $user->getInfo());
        $em = $this->entityManager;
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
                if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                    $user->setStepKyc(User::STEP_KYC_EXPERIENCE);
                }
                // Recalcul progressif
                $this->profileScorer->calculateAndUpdateProfile($user);
                $em->persist($user);
                $em->flush();

                // Rediriger vers la première sous-étape du questionnaire de connaissance
                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'market-abuse']);
            }
        }

        return [
            'form' => $form->createView(),
            'userInfo' => $user->getInfo(),
        ];

    }

    protected function step4MarketAbuse(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $marketAbuse = $investorKnowledge->getMarketAbuse() ?? new MarketAbuse();
        $form = $this->createForm(KnowledgeMarketAbuseType::class, $marketAbuse);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setMarketAbuse($marketAbuse);
            // Recalcul progressif (ne change pas le score Knowledge, mais tient à jour le global)
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'education-level']);
        }
        
        return [
            'form' => $form->createView(),
            'step' => 1,
            'totalSteps' => 6,
        ];
    }

    protected function step4EducationLevel(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $educationLevel = $investorKnowledge->getEducationLevel() ?? new EducationLevel();
        $form = $this->createForm(KnowledgeEducationLevelType::class, $educationLevel);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setEducationLevel($educationLevel);
            // Recalcul progressif (inclut le bonus éducation si vous l'activez côté service)
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'investment-experience']);
        }
        
        return [
            'form' => $form->createView(),
            'step' => 2,
            'totalSteps' => 6,
        ];
    }

    protected function step4InvestmentExperience(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $investmentExperience = $investorKnowledge->getInvestmentExperience() ?? new InvestmentExperience();
        $form = $this->createForm(KnowledgeInvestmentExperienceType::class, $investmentExperience);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setInvestmentExperience($investmentExperience);
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'financial-products']);
        }
        
        return [
            'form' => $form->createView(),
            'step' => 3,
            'totalSteps' => 6,
        ];
    }

    protected function step4FinancialProducts(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $financialProducts = $investorKnowledge->getFinancialProductsKnowledge() ?? new FinancialProductsKnowledge();
        $form = $this->createForm(KnowledgeFinancialProductsType::class, $financialProducts);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setFinancialProductsKnowledge($financialProducts);
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'complex-products']);
        }
        
        return [
            'form' => $form->createView(),
            'step' => 4,
            'totalSteps' => 6,
        ];
    }

    protected function step4ComplexProducts(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $complexProducts = $investorKnowledge->getComplexProductsKnowledge() ?? new ComplexProductsKnowledge();
        $form = $this->createForm(KnowledgeComplexProductsType::class, $complexProducts);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setComplexProductsKnowledge($complexProducts);
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE, 'substep' => 'market-experience']);
        }
        
        return [
            'form' => $form->createView(),
            'step' => 5,
            'totalSteps' => 6,
        ];
    }

    protected function step4MarketExperience(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $marketExperience = $investorKnowledge->getMarketExperience() ?? new MarketExperience();
        $form = $this->createForm(KnowledgeMarketExperienceType::class, $marketExperience);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setMarketExperience($marketExperience);
            $investorKnowledge->setIsCompleted(true);
            
            // Calculer le score Knowledge local et profil Knowledge
            $score = $this->calculateTotalScore($investorKnowledge);
            $profileType = $this->profileScorer->calculateProfileType($score);
            
            $investorKnowledge->setScore($score);
            $investorKnowledge->setProfileType($profileType);
            
            // Mettre à jour le step KYC de l'utilisateur
            if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                $user->setStepKyc(User::STEP_KYC_DOCUMENTS);
            }
            
            // Mettre à jour le profil/score global utilisateur
            $this->profileScorer->calculateAndUpdateProfile($user);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Questionnaire de connaissance des marchés financiers terminé avec succès !');
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_DOCUMENTS]);
        }
        
        return [
            'form' => $form->createView(),
            'step' => 6,
            'totalSteps' => 6,
        ];
    }

    protected function step4Aware(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();

        $form = $this->createForm(Step4Type::class, $user->getInfo());
        $em = $this->entityManager;
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
                if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                    $user->setStepKyc(User::STEP_KYC_DOCUMENTS);
                }
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_DOCUMENTS]);
            }
        }

        return [
            'form' => $form->createView()
        ];

    }

    private function getOrCreateInvestorKnowledge(User $user): InvestorKnowledge
    {
        $investorKnowledge = $user->getInvestorKnowledge();
        
        if (!$investorKnowledge) {
            $investorKnowledge = new InvestorKnowledge();
            $investorKnowledge->setUser($user);
            $this->entityManager->persist($investorKnowledge);
        }
        
        return $investorKnowledge;
    }

    private function calculateTotalScore(InvestorKnowledge $investorKnowledge): int
    {
        $score = 0;
        
        // Score pour les produits financiers (8 questions, max 24 points)
        if ($financialProducts = $investorKnowledge->getFinancialProductsKnowledge()) {
            $score += $financialProducts->getScore();
        }
        
        // Score pour les produits complexes (10 questions, max 30 points)
        if ($complexProducts = $investorKnowledge->getComplexProductsKnowledge()) {
            $score += $complexProducts->getScore();
        }
        
        // Score pour l'expérience des marchés (max 20 points)
        if ($marketExperience = $investorKnowledge->getMarketExperience()) {
            $score += $marketExperience->getTotalExperienceScore();
        }
        
        // Bonus pour l'éducation (max 10 points)
        if ($educationLevel = $investorKnowledge->getEducationLevel()) {
            $level = $educationLevel->getLevel();
            if ($level === 'master' || $level === 'phd') {
                $score += 10;
            } elseif ($level === 'bachelor') {
                $score += 5;
            }
        }
        
        return $score;
    }

    /**
     * Renseigne toutes les sous-étapes "Connaissances des marchés" au maximum
     * lorsqu'un utilisateur se déclare investisseur professionnel (MIF2).
     */
    private function ensureMaxKnowledgeForProfessional(User $user): void
    {
        $em = $this->entityManager;
        $knowledge = $user->getInvestorKnowledge();
        if (!$knowledge) {
            $knowledge = new InvestorKnowledge();
            $knowledge->setUser($user);
            $em->persist($knowledge);
        }

        // MarketAbuse (placeholder)
        if (!$knowledge->getMarketAbuse()) {
            $knowledge->setMarketAbuse(new MarketAbuse());
        }

        // Education level au maximum
        $edu = $knowledge->getEducationLevel() ?? new EducationLevel();
        $edu->setLevel('phd');
        $knowledge->setEducationLevel($edu);

        // InvestmentExperience (créer si manquant)
        if (!$knowledge->getInvestmentExperience()) {
            $knowledge->setInvestmentExperience(new InvestmentExperience());
        }

        // Financial products knowledge (8 questions) -> maximiser
        $fp = $knowledge->getFinancialProductsKnowledge() ?? new FinancialProductsKnowledge();
        $fp->setQuestion1('true');
        $fp->setQuestion2('true');
        $fp->setQuestion3('true');
        $fp->setQuestion4('true');
        $fp->setQuestion5('true');
        $fp->setQuestion6('true');
        $fp->setQuestion7('true');
        $fp->setQuestion8('true');
        $knowledge->setFinancialProductsKnowledge($fp);

        // Complex products knowledge (10 questions) -> maximiser
        $cp = $knowledge->getComplexProductsKnowledge() ?? new ComplexProductsKnowledge();
        $cp->setQuestion1('true');
        $cp->setQuestion2('true');
        $cp->setQuestion3('true');
        $cp->setQuestion4('true');
        $cp->setQuestion5('true');
        $cp->setQuestion6('true');
        $cp->setQuestion7('true');
        $cp->setQuestion8('true');
        $cp->setQuestion9('true');
        $cp->setQuestion10('true');
        $knowledge->setComplexProductsKnowledge($cp);

        // Market experience -> maximiser
        $me = $knowledge->getMarketExperience() ?? new MarketExperience();
        $me->setHasStocksExperience(true);
        $me->setStocksOperationsCount('> 10 fois');
        $me->setStocksVolume('> 150 K€');
        $me->setHasBondsExperience(true);
        $me->setBondsOperationsCount('> 10 fois');
        $me->setBondsVolume('> 150 K€');
        $me->setHasUcitsExperience(true);
        $me->setUcitsOperationsCount('> 10 fois');
        $me->setUcitsVolume('> 150 K€');
        $me->setHasRealEstateExperience(true);
        $me->setRealEstateOperationsCount('> 10 fois');
        $me->setRealEstateVolume('> 150 K€');
        $me->setHasComplexInstrumentsExperience(true);
        $me->setComplexInstrumentsOperationsCount('> 10 fois');
        $me->setComplexInstrumentsVolume('> 150 K€');
        $knowledge->setMarketExperience($me);

        $knowledge->setIsCompleted(true);
        // Recalcul du score global
        if (property_exists($this, 'profileScorer') && $this->profileScorer instanceof InvestorProfileScorer) {
            $this->profileScorer->calculateAndUpdateProfile($user);
        }
        $em->persist($knowledge);
        $em->persist($user);
        $em->flush();
    }
}
