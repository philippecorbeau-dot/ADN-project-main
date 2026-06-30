<?php

namespace App\Tests\Functional;

use App\Entity\User\User;
use App\Entity\User\Knowledge\InvestorKnowledge;
use App\Entity\User\Knowledge\MarketAbuse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class KnowledgeControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testMarketAbuseStep(): void
    {
        // Créer un utilisateur de test
        $user = $this->createTestUser();
        
        // Se connecter
        $this->client->loginUser($user);
        
        // Accéder à la page d'abus de marché
        $this->client->request('GET', '/knowledge/market-abuse');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1:contains("Connaissance des marchés financiers")');
        $this->assertSelectorExists('h2:contains("Abus de marché")');
        
        // Soumettre le formulaire
        $this->client->submitForm('Suivant', [
            'knowledge_market_abuse[hasOtherSecuritiesAccounts]' => '0',
            'knowledge_market_abuse[hasFinancialProfession]' => '0',
            'knowledge_market_abuse[isListedCompanyDirector]' => '0',
        ]);
        
        // Vérifier la redirection vers l'étape suivante
        $this->assertResponseRedirects('/knowledge/education-level');
        
        // Vérifier que les données sont sauvegardées
        $investorKnowledge = $this->entityManager->getRepository(InvestorKnowledge::class)
            ->findOneBy(['user' => $user]);
        
        $this->assertNotNull($investorKnowledge);
        $this->assertNotNull($investorKnowledge->getMarketAbuse());
        $this->assertFalse($investorKnowledge->getMarketAbuse()->getHasOtherSecuritiesAccounts());
        $this->assertFalse($investorKnowledge->getMarketAbuse()->getHasFinancialProfession());
        $this->assertFalse($investorKnowledge->getMarketAbuse()->getIsListedCompanyDirector());
    }

    public function testEducationLevelStep(): void
    {
        // Créer un utilisateur de test avec MarketAbuse déjà rempli
        $user = $this->createTestUser();
        $investorKnowledge = $this->createInvestorKnowledge($user);
        $marketAbuse = new MarketAbuse();
        $marketAbuse->setHasOtherSecuritiesAccounts(false);
        $marketAbuse->setHasFinancialProfession(false);
        $marketAbuse->setIsListedCompanyDirector(false);
        $investorKnowledge->setMarketAbuse($marketAbuse);
        $this->entityManager->persist($investorKnowledge);
        $this->entityManager->flush();
        
        // Se connecter
        $this->client->loginUser($user);
        
        // Accéder à la page du niveau d'éducation
        $this->client->request('GET', '/knowledge/education-level');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h2:contains("Niveau d\'éducation")');
        
        // Soumettre le formulaire
        $this->client->submitForm('Suivant', [
            'knowledge_education_level[level]' => 'Bac +5 (Master, Ingénieur)',
        ]);
        
        // Vérifier la redirection vers l'étape suivante
        $this->assertResponseRedirects('/knowledge/investment-experience');
        
        // Vérifier que les données sont sauvegardées
        $investorKnowledge = $this->entityManager->getRepository(InvestorKnowledge::class)
            ->findOneBy(['user' => $user]);
        
        $this->assertNotNull($investorKnowledge->getEducationLevel());
        $this->assertEquals('Bac +5 (Master, Ingénieur)', $investorKnowledge->getEducationLevel()->getLevel());
    }

    public function testCompleteQuestionnaire(): void
    {
        // Créer un utilisateur de test avec toutes les étapes précédentes
        $user = $this->createTestUser();
        $investorKnowledge = $this->createInvestorKnowledge($user);
        
        // Remplir toutes les étapes précédentes
        $this->fillPreviousSteps($investorKnowledge);
        $this->entityManager->flush();
        
        // Se connecter
        $this->client->loginUser($user);
        
        // Accéder à la dernière étape (expérience des marchés)
        $this->client->request('GET', '/knowledge/market-experience');
        
        $this->assertResponseIsSuccessful();
        
        // Soumettre le formulaire final
        $this->client->submitForm('Suivant', [
            'knowledge_market_experience[hasStocksExperience]' => '1',
            'knowledge_market_experience[stocksOperationsCount]' => '2-10 fois',
            'knowledge_market_experience[stocksVolume]' => 'de 50 K€ à 150 K€',
            'knowledge_market_experience[hasBondsExperience]' => '0',
            'knowledge_market_experience[hasUcitsExperience]' => '0',
            'knowledge_market_experience[hasRealEstateExperience]' => '0',
            'knowledge_market_experience[hasComplexInstrumentsExperience]' => '0',
        ]);
        
        // Vérifier la redirection vers l'étape 5 du KYC
        $this->assertResponseRedirects('/register/kyc/step/5');
        
        // Vérifier que le questionnaire est marqué comme terminé
        $investorKnowledge = $this->entityManager->getRepository(InvestorKnowledge::class)
            ->findOneBy(['user' => $user]);
        
        $this->assertTrue($investorKnowledge->isCompleted());
        $this->assertNotNull($investorKnowledge->getScore());
        $this->assertNotNull($investorKnowledge->getProfileType());
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }

    private function createInvestorKnowledge(User $user): InvestorKnowledge
    {
        $investorKnowledge = new InvestorKnowledge();
        $investorKnowledge->setUser($user);
        
        $this->entityManager->persist($investorKnowledge);
        
        return $investorKnowledge;
    }

    private function fillPreviousSteps(InvestorKnowledge $investorKnowledge): void
    {
        // MarketAbuse
        $marketAbuse = new MarketAbuse();
        $marketAbuse->setHasOtherSecuritiesAccounts(false);
        $marketAbuse->setHasFinancialProfession(false);
        $marketAbuse->setIsListedCompanyDirector(false);
        $investorKnowledge->setMarketAbuse($marketAbuse);
        
        // EducationLevel
        $educationLevel = new \App\Entity\User\Knowledge\EducationLevel();
        $educationLevel->setLevel('Bac +5 (Master, Ingénieur)');
        $investorKnowledge->setEducationLevel($educationLevel);
        
        // InvestmentExperience
        $investmentExperience = new \App\Entity\User\Knowledge\InvestmentExperience();
        $investmentExperience->setHasLostSignificantAmounts(false);
        $investmentExperience->setManagesOwnPortfolio(true);
        $investmentExperience->setConcentratesOnSingleSecurity(false);
        $investmentExperience->setAppropriatenessTestPerformed(true);
        $investmentExperience->setOrdersThroughCif(false);
        $investorKnowledge->setInvestmentExperience($investmentExperience);
        
        // FinancialProductsKnowledge
        $financialProducts = new \App\Entity\User\Knowledge\FinancialProductsKnowledge();
        $financialProducts->setQuestion1('false');
        $financialProducts->setQuestion2('true');
        $financialProducts->setQuestion3('false');
        $financialProducts->setQuestion4('false');
        $financialProducts->setQuestion5('true');
        $financialProducts->setQuestion6('true');
        $financialProducts->setQuestion7('true');
        $financialProducts->setQuestion8('false');
        $investorKnowledge->setFinancialProductsKnowledge($financialProducts);
        
        // ComplexProductsKnowledge
        $complexProducts = new \App\Entity\User\Knowledge\ComplexProductsKnowledge();
        $complexProducts->setQuestion1('true');
        $complexProducts->setQuestion2('true');
        $complexProducts->setQuestion3('true');
        $complexProducts->setQuestion4('true');
        $complexProducts->setQuestion5('false');
        $complexProducts->setQuestion6('true');
        $complexProducts->setQuestion7('true');
        $complexProducts->setQuestion8('true');
        $complexProducts->setQuestion9('true');
        $complexProducts->setQuestion10('true');
        $investorKnowledge->setComplexProductsKnowledge($complexProducts);
    }
} 