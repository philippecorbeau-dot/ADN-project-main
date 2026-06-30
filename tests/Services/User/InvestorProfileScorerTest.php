<?php

namespace App\Tests\Services\User;

use App\Entity\User\Info;
use App\Entity\User\User;
use App\Entity\User\Knowledge\InvestorKnowledge;
use App\Entity\User\Knowledge\FinancialProductsKnowledge;
use App\Entity\User\Knowledge\ComplexProductsKnowledge;
use App\Entity\User\Knowledge\MarketExperience;
use App\Entity\User\Knowledge\EducationLevel;
use App\Services\User\InvestorProfileScorer;
use PHPUnit\Framework\TestCase;

class InvestorProfileScorerTest extends TestCase
{
    private InvestorProfileScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new InvestorProfileScorer();
    }

    public function testCalculateProfileType(): void
    {
        $this->assertEquals(InvestorProfileScorer::PROFILE_PRUDENT, $this->scorer->calculateProfileType(0));
        $this->assertEquals(InvestorProfileScorer::PROFILE_PRUDENT, $this->scorer->calculateProfileType(29));
        $this->assertEquals(InvestorProfileScorer::PROFILE_EQUILIBRE, $this->scorer->calculateProfileType(30));
        $this->assertEquals(InvestorProfileScorer::PROFILE_EQUILIBRE, $this->scorer->calculateProfileType(59));
        $this->assertEquals(InvestorProfileScorer::PROFILE_DYNAMIQUE, $this->scorer->calculateProfileType(60));
        $this->assertEquals(InvestorProfileScorer::PROFILE_DYNAMIQUE, $this->scorer->calculateProfileType(79));
        $this->assertEquals(InvestorProfileScorer::PROFILE_SPE, $this->scorer->calculateProfileType(80));
        $this->assertEquals(InvestorProfileScorer::PROFILE_SPE, $this->scorer->calculateProfileType(100));
    }

    public function testGetProductRecommendations(): void
    {
        $recommendations = $this->scorer->getProductRecommendations(InvestorProfileScorer::PROFILE_PRUDENT);
        $this->assertContains('ETF indiciels', $recommendations);
        $this->assertContains('Assurance-vie', $recommendations);

        $recommendations = $this->scorer->getProductRecommendations(InvestorProfileScorer::PROFILE_SPE);
        $this->assertContains('Produits dérivés complexes', $recommendations);
        $this->assertContains('Private Equity', $recommendations);
    }

    public function testGetProfileDescription(): void
    {
        $description = $this->scorer->getProfileDescription(InvestorProfileScorer::PROFILE_PRUDENT);
        $this->assertStringContainsString('Prudent', $description);

        $description = $this->scorer->getProfileDescription(InvestorProfileScorer::PROFILE_SPE);
        $this->assertStringContainsString('Sophistiqué', $description);
    }

    public function testGetProfileColor(): void
    {
        $this->assertEquals('green', $this->scorer->getProfileColor(InvestorProfileScorer::PROFILE_PRUDENT));
        $this->assertEquals('blue', $this->scorer->getProfileColor(InvestorProfileScorer::PROFILE_EQUILIBRE));
        $this->assertEquals('orange', $this->scorer->getProfileColor(InvestorProfileScorer::PROFILE_DYNAMIQUE));
        $this->assertEquals('purple', $this->scorer->getProfileColor(InvestorProfileScorer::PROFILE_SPE));
    }

    public function testCalculateTotalScoreWithCompleteData(): void
    {
        $user = $this->createUserWithCompleteData();
        $score = $this->scorer->calculateTotalScore($user);
        
        // Le score devrait être > 0 avec des données complètes
        $this->assertGreaterThan(0, $score);
    }

    public function testCalculateTotalScoreWithMinimalData(): void
    {
        $user = $this->createUserWithMinimalData();
        $score = $this->scorer->calculateTotalScore($user);
        
        // Le score devrait être 0 avec des données minimales
        $this->assertEquals(0, $score);
    }

    public function testCalculateAndUpdateProfile(): void
    {
        $user = $this->createUserWithCompleteData();
        
        $this->scorer->calculateAndUpdateProfile($user);
        
        $this->assertNotNull($user->getInvestorScore());
        $this->assertNotNull($user->getInvestorProfile());
        $this->assertNotNull($user->getInvestorProfileCalculatedAt());
        $this->assertGreaterThan(0, $user->getInvestorScore());
    }

    public function testGetQuestionnaireStatus(): void
    {
        $user = $this->createUserWithCompleteData();
        
        // Test avec questionnaire complet
        $this->assertEquals('Complété', $user->getQuestionnaireStatus());
        
        // Test avec questionnaire partiel
        $userPartial = $this->createUserWithMinimalData();
        $this->assertEquals('Non commencé', $userPartial->getQuestionnaireStatus());
    }

    public function testProfilePrudentWithLowKnowledge(): void
    {
        $user = $this->createUserWithLowInfo();

        $score = $this->scorer->calculateTotalScore($user);
        $profile = $this->scorer->calculateProfileType($score);

        $this->assertLessThan(30, $score);
        $this->assertSame(InvestorProfileScorer::PROFILE_PRUDENT, $profile);
    }

    public function testProfileEquilibreWithModerateKnowledge(): void
    {
        $user = $this->createUserWithBaselineInfo();
        $user->getInfo()->setAwarenessMinimumAmount(true);
        $user->setIsAwareProfile(true);

        $knowledge = new InvestorKnowledge();
        $user->setInvestorKnowledge($knowledge);

        $financialProducts = new FinancialProductsKnowledge();
        $financialProducts->setQuestion1('false');
        $financialProducts->setQuestion2('true');
        $financialProducts->setQuestion3('false');
        $financialProducts->setQuestion4('false');
        $financialProducts->setQuestion5('true');
        $financialProducts->setQuestion6('true');
        $financialProducts->setQuestion7('true');
        $financialProducts->setQuestion8('false');
        $knowledge->setFinancialProductsKnowledge($financialProducts);

        $education = new EducationLevel();
        $education->setLevel('bachelor');
        $knowledge->setEducationLevel($education);

        $score = $this->scorer->calculateTotalScore($user);
        $profile = $this->scorer->calculateProfileType($score);

        $this->assertGreaterThanOrEqual(30, $score);
        $this->assertLessThan(60, $score);
        $this->assertSame(InvestorProfileScorer::PROFILE_EQUILIBRE, $profile);
    }

    public function testProfileDynamiqueWithHighKnowledge(): void
    {
        $user = $this->createUserWithBaselineInfo();
        $user->getInfo()->setAwarenessMinimumAmount(true);
        $user->getInfo()->setAwarenessMinimumTime(true);
        $user->getInfo()->setAwarenessTransactions(true);
        $user->setIsAwareProfile(true);

        $knowledge = new InvestorKnowledge();
        $user->setInvestorKnowledge($knowledge);

        $financialProducts = new FinancialProductsKnowledge();
        $financialProducts->setQuestion1('false');
        $financialProducts->setQuestion2('true');
        $financialProducts->setQuestion3('false');
        $financialProducts->setQuestion4('false');
        $financialProducts->setQuestion5('true');
        $financialProducts->setQuestion6('true');
        $financialProducts->setQuestion7('true');
        $financialProducts->setQuestion8('false');
        $knowledge->setFinancialProductsKnowledge($financialProducts);

        $complexProducts = new ComplexProductsKnowledge();
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
        $knowledge->setComplexProductsKnowledge($complexProducts);

        $marketExperience = new MarketExperience();
        $marketExperience->setHasStocksExperience(true);
        $marketExperience->setStocksOperationsCount('> 10 fois');
        $marketExperience->setStocksVolume('> 150 K€');
        $marketExperience->setHasBondsExperience(true);
        $marketExperience->setBondsOperationsCount('> 10 fois');
        $marketExperience->setBondsVolume('> 150 K€');
        $marketExperience->setHasUcitsExperience(true);
        $marketExperience->setUcitsOperationsCount('2-10 fois');
        $marketExperience->setUcitsVolume('de 50 K€ à 150 K€');
        $marketExperience->setHasRealEstateExperience(true);
        $marketExperience->setRealEstateOperationsCount('2-10 fois');
        $marketExperience->setRealEstateVolume('de 50 K€ à 150 K€');
        $marketExperience->setHasComplexInstrumentsExperience(true);
        $marketExperience->setComplexInstrumentsOperationsCount('2-10 fois');
        $marketExperience->setComplexInstrumentsVolume('de 50 K€ à 150 K€');
        $knowledge->setMarketExperience($marketExperience);

        $education = new EducationLevel();
        $education->setLevel('master');
        $knowledge->setEducationLevel($education);

        $score = $this->scorer->calculateTotalScore($user);
        $profile = $this->scorer->calculateProfileType($score);

        $this->assertGreaterThanOrEqual(60, $score);
        $this->assertLessThan(80, $score);
        $this->assertSame(InvestorProfileScorer::PROFILE_DYNAMIQUE, $profile);
    }

    public function testProfileSpeWithMaximumKnowledgeAndMif(): void
    {
        $user = $this->createUserWithCompleteData();

        $score = $this->scorer->calculateTotalScore($user);
        $profile = $this->scorer->calculateProfileType($score);

        $this->assertGreaterThanOrEqual(80, $score);
        $this->assertSame(InvestorProfileScorer::PROFILE_SPE, $profile);
    }

    private function createUserWithCompleteData(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setInterestedBy(['Crowdfunding immobilier', 'SCPI']);

        $info = new Info();
        $info->setRealestate(300000);
        $info->setAccountSecurities(200000);
        $info->setCapitalisation(150000);
        $info->setScpi(50000);
        $info->setIncome(80000);
        $info->setAwarenessMinimumAmount(true);
        $info->setAwarenessMinimumTime(true);
        $info->setAwarenessTransactions(true);
        $info->setMif(true);
        $info->setObjective([
            Info::OBJECTIVE_DIVERSIFY,
            Info::OBJECTIVE_REALESTATE,
            Info::OBJECTIVE_TAXATION,
        ]);
        $info->setInvestmentTerm([0, 1, 2]);
        $user->setInfo($info);
        $user->setIsAwareProfile(true);

        $investorKnowledge = new InvestorKnowledge();
        $investorKnowledge->setUser($user);

        $financialProducts = new FinancialProductsKnowledge();
        $financialProducts->setQuestion1('true');
        $financialProducts->setQuestion2('true');
        $financialProducts->setQuestion3('false');
        $financialProducts->setQuestion4('true');
        $financialProducts->setQuestion5('true');
        $financialProducts->setQuestion6('true');
        $financialProducts->setQuestion7('true');
        $financialProducts->setQuestion8('false');
        $investorKnowledge->setFinancialProductsKnowledge($financialProducts);

        $complexProducts = new ComplexProductsKnowledge();
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

        $marketExperience = new MarketExperience();
        $marketExperience->setHasStocksExperience(true);
        $marketExperience->setStocksOperationsCount('> 10 fois');
        $marketExperience->setStocksVolume('> 150 K€');
        $marketExperience->setHasBondsExperience(true);
        $marketExperience->setBondsOperationsCount('> 10 fois');
        $marketExperience->setBondsVolume('> 150 K€');
        $marketExperience->setHasUcitsExperience(true);
        $marketExperience->setUcitsOperationsCount('> 10 fois');
        $marketExperience->setUcitsVolume('> 150 K€');
        $marketExperience->setHasRealEstateExperience(true);
        $marketExperience->setRealEstateOperationsCount('2-10 fois');
        $marketExperience->setRealEstateVolume('> 150 K€');
        $marketExperience->setHasComplexInstrumentsExperience(true);
        $marketExperience->setComplexInstrumentsOperationsCount('> 10 fois');
        $marketExperience->setComplexInstrumentsVolume('> 150 K€');
        $investorKnowledge->setMarketExperience($marketExperience);

        $educationLevel = new EducationLevel();
        $educationLevel->setLevel('phd');
        $investorKnowledge->setEducationLevel($educationLevel);

        $user->setInvestorKnowledge($investorKnowledge);

        return $user;
    }

    private function createUserWithMinimalData(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        
        return $user;
    }

    private function createUserWithBaselineInfo(): User
    {
        $user = new User();
        $user->setInfo(new Info());
        $user->getInfo()->setRealestate(150000);
        $user->getInfo()->setAccountSecurities(50000);
        $user->getInfo()->setCapitalisation(50000);
        $user->getInfo()->setScpi(20000);
        $user->getInfo()->setIncome(60000);
        $user->getInfo()->setObjective([
            Info::OBJECTIVE_DIVERSIFY,
            Info::OBJECTIVE_REALESTATE,
        ]);
        $user->getInfo()->setInvestmentTerm([1, 2]);

        return $user;
    }

    private function createUserWithLowInfo(): User
    {
        $user = new User();
        $user->setInfo(new Info());
        $user->getInfo()->setObjective([Info::OBJECTIVE_SAVINGS]);
        $user->getInfo()->setInvestmentTerm([0]);
        $user->setInvestorKnowledge(null);

        return $user;
    }
} 