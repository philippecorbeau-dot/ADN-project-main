<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class KycWorkflowTest extends WebTestCase
{
    public function testRedirectIfNotLoggedIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register/kyc/step/1');

        $this->assertResponseRedirects('/login');
    }

    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/user/dashboard');

        $this->assertResponseRedirects('/login');
    }
}
