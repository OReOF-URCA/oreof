<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConseillerHomepageControllerTest extends WebTestCase
{
    private function getOrCreateAdmin(): User
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-conseiller']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-conseiller');
            $admin->setEmail('admin-conseiller@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Conseiller');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    public function testUnauthenticatedUserCannotAccessConseilLists(): void
    {
        $client = static::createClient();

        $client->request('GET', '/formation/liste-cfvu');
        $this->assertFalse($client->getResponse()->isSuccessful());

        $client->request('GET', '/formation/liste-conseil-composante');
        $this->assertFalse($client->getResponse()->isSuccessful());
    }

    public function testAdminAccessToCfvuAndConseilComposanteWizard(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateAdmin();
        $client->loginUser($admin);

        // Test homepage with step=cfvu
        $client->request('GET', '/?step=cfvu');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#tab-cfvu');

        // Test homepage with step=conseil_composante
        $client->request('GET', '/?step=conseil_composante');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#tab-conseil-composante');

        // Test wizard partials
        $client->request('GET', '/wizard?step=cfvu');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/wizard?step=conseil_composante');
        $this->assertResponseIsSuccessful();

        // Test AJAX lists
        $client->request('GET', '/formation/liste-cfvu');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/formation/liste-conseil-composante');
        $this->assertResponseIsSuccessful();
    }
}
