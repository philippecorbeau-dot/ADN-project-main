<?php

namespace App\Controller\Front\User;

use App\Entity\User\Knowledge\InvestorKnowledge;
use App\Entity\User\Knowledge\MarketAbuse;
use App\Entity\User\Knowledge\EducationLevel;
use App\Entity\User\Knowledge\InvestmentExperience;
use App\Entity\User\Knowledge\FinancialProductsKnowledge;
use App\Entity\User\Knowledge\ComplexProductsKnowledge;
use App\Entity\User\Knowledge\MarketExperience;
use App\Form\Front\User\Create\KnowledgeMarketAbuseType;
use App\Form\Front\User\Create\KnowledgeEducationLevelType;
use App\Form\Front\User\Create\KnowledgeInvestmentExperienceType;
use App\Form\Front\User\Create\KnowledgeFinancialProductsType;
use App\Form\Front\User\Create\KnowledgeComplexProductsType;
use App\Form\Front\User\Create\KnowledgeMarketExperienceType;
use App\Services\User\InvestorProfileScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;

#[Route('/knowledge')]
#[IsGranted('ROLE_USER')]
class KnowledgeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvestorProfileScorer $profileScorer
    ) {}

    #[Route('/market-abuse', name: 'knowledge_market_abuse')]
    public function marketAbuse(Request $request): Response
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $marketAbuse = $investorKnowledge->getMarketAbuse() ?? new MarketAbuse();
        $form = $this->createForm(KnowledgeMarketAbuseType::class, $marketAbuse);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setMarketAbuse($marketAbuse);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('knowledge_education_level');
        }
        
        return $this->render('front/user/knowledge/market_abuse.html.twig', [
            'form' => $form->createView(),
            'step' => 1,
            'totalSteps' => 6,
        ]);
    }

    #[Route('/education-level', name: 'knowledge_education_level')]
    public function educationLevel(Request $request): Response
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $educationLevel = $investorKnowledge->getEducationLevel() ?? new EducationLevel();
        $form = $this->createForm(KnowledgeEducationLevelType::class, $educationLevel);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setEducationLevel($educationLevel);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('knowledge_investment_experience');
        }
        
        return $this->render('front/user/knowledge/education_level.html.twig', [
            'form' => $form->createView(),
            'step' => 2,
            'totalSteps' => 6,
        ]);
    }

    #[Route('/investment-experience', name: 'knowledge_investment_experience')]
    public function investmentExperience(Request $request): Response
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $investmentExperience = $investorKnowledge->getInvestmentExperience() ?? new InvestmentExperience();
        $form = $this->createForm(KnowledgeInvestmentExperienceType::class, $investmentExperience);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setInvestmentExperience($investmentExperience);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('knowledge_financial_products');
        }
        
        return $this->render('front/user/knowledge/investment_experience.html.twig', [
            'form' => $form->createView(),
            'step' => 3,
            'totalSteps' => 6,
        ]);
    }

    #[Route('/financial-products', name: 'knowledge_financial_products')]
    public function financialProducts(Request $request): Response
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $financialProducts = $investorKnowledge->getFinancialProductsKnowledge() ?? new FinancialProductsKnowledge();
        $form = $this->createForm(KnowledgeFinancialProductsType::class, $financialProducts);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setFinancialProductsKnowledge($financialProducts);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('knowledge_complex_products');
        }
        
        return $this->render('front/user/knowledge/financial_products.html.twig', [
            'form' => $form->createView(),
            'step' => 4,
            'totalSteps' => 6,
        ]);
    }

    #[Route('/complex-products', name: 'knowledge_complex_products')]
    public function complexProducts(Request $request): Response
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $complexProducts = $investorKnowledge->getComplexProductsKnowledge() ?? new ComplexProductsKnowledge();
        $form = $this->createForm(KnowledgeComplexProductsType::class, $complexProducts);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setComplexProductsKnowledge($complexProducts);
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('knowledge_market_experience');
        }
        
        return $this->render('front/user/knowledge/complex_products.html.twig', [
            'form' => $form->createView(),
            'step' => 5,
            'totalSteps' => 6,
        ]);
    }

    #[Route('/market-experience', name: 'knowledge_market_experience')]
    public function marketExperience(Request $request): Response
    {
        $user = $this->getUser();
        $investorKnowledge = $this->getOrCreateInvestorKnowledge($user);
        
        $marketExperience = $investorKnowledge->getMarketExperience() ?? new MarketExperience();
        $form = $this->createForm(KnowledgeMarketExperienceType::class, $marketExperience);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $investorKnowledge->setMarketExperience($marketExperience);
            $investorKnowledge->setIsCompleted(true);
            
            // Calculer le score et le profil
            $score = $this->calculateTotalScore($investorKnowledge);
            $profileType = $this->profileScorer->calculateProfileType($score);
            
            $investorKnowledge->setScore($score);
            $investorKnowledge->setProfileType($profileType);
            
            // Mettre à jour le step KYC de l'utilisateur
            if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                $user->setStepKyc(User::STEP_KYC_DOCUMENTS);
            }
            
            $this->entityManager->persist($investorKnowledge);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Questionnaire de connaissance des marchés financiers terminé avec succès !');
            
            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_DOCUMENTS]);
        }
        
        return $this->render('front/user/knowledge/market_experience.html.twig', [
            'form' => $form->createView(),
            'step' => 6,
            'totalSteps' => 6,
        ]);
    }

    private function getOrCreateInvestorKnowledge($user): InvestorKnowledge
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
        
        // Score des produits financiers (8 questions)
        if ($investorKnowledge->getFinancialProductsKnowledge()) {
            $score += $investorKnowledge->getFinancialProductsKnowledge()->getScore();
        }
        
        // Score des produits complexes (10 questions)
        if ($investorKnowledge->getComplexProductsKnowledge()) {
            $score += $investorKnowledge->getComplexProductsKnowledge()->getScore();
        }
        
        // Score de l'expérience des marchés
        if ($investorKnowledge->getMarketExperience()) {
            $score += $investorKnowledge->getMarketExperience()->getTotalExperienceScore();
        }
        
        return $score;
    }
} 