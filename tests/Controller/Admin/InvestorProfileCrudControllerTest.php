<?php

namespace App\Tests\Controller\Admin;

use App\Entity\User\User;
use App\Services\User\InvestorProfileScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvestorProfileCrudControllerTest extends WebTestCase
{
    public function testInvestorProfileControllerExists(): void
    {
        $client = static::createClient();
        
        // Vérifier que le service existe
        $container = static::getContainer();
        $controller = $container->get('App\Controller\Admin\InvestorProfileCrudController');
        
        $this->assertNotNull($controller);
        $this->assertInstanceOf('App\Controller\Admin\InvestorProfileCrudController', $controller);
    }

    public function testInvestorProfileScorerServiceExists(): void
    {
        $client = static::createClient();
        
        // Vérifier que le service existe
        $container = static::getContainer();
        $scorer = $container->get('App\Services\User\InvestorProfileScorer');
        
        $this->assertNotNull($scorer);
        $this->assertInstanceOf('App\Services\User\InvestorProfileScorer', $scorer);
    }

    public function testCalculateProfileType(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $scorer = $container->get('App\Services\User\InvestorProfileScorer');
        
        // Test des catégories de profil
        $this->assertEquals('PRUDENT', $scorer->calculateProfileType(0));
        $this->assertEquals('PRUDENT', $scorer->calculateProfileType(29));
        $this->assertEquals('EQUILIBRE', $scorer->calculateProfileType(30));
        $this->assertEquals('EQUILIBRE', $scorer->calculateProfileType(59));
        $this->assertEquals('DYNAMIQUE', $scorer->calculateProfileType(60));
        $this->assertEquals('DYNAMIQUE', $scorer->calculateProfileType(79));
        $this->assertEquals('SPE', $scorer->calculateProfileType(80));
        $this->assertEquals('SPE', $scorer->calculateProfileType(100));
    }
} 