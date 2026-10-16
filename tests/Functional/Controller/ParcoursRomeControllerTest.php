<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Parcours;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ParcoursRomeControllerTest extends WebTestCase
{
    private function getOrCreateAdmin(): User
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-apikey']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-apikey');
            $admin->setEmail('admin@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Test');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    public function testRomeEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $parcours = $em->getRepository(Parcours::class)->findOneBy([]);

        if ($parcours === null) {
            $this->markTestSkipped('Aucun parcours disponible pour tester.');
        }

        $client->request('GET', sprintf('/parcours/v2/%d/rome/status', $parcours->getId()));
        $this->assertResponseRedirects('/connexion');
    }

    public function testRomeStatusAndSearchEndpoints(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateAdmin();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $parcours = $em->getRepository(Parcours::class)->findOneBy([]);

        if ($parcours === null) {
            $this->markTestSkipped('Aucun parcours disponible pour tester.');
        }

        $client->loginUser($admin);

        // 1. Status
        $client->request('GET', sprintf('/parcours/v2/%d/rome/status', $parcours->getId()));
        $this->assertResponseIsSuccessful();
        $statusData = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($statusData);
        $this->assertArrayHasKey('connected', $statusData);

        // 2. Search
        $client->request('GET', sprintf('/parcours/v2/%d/rome/search?q=informatique', $parcours->getId()));
        $this->assertResponseIsSuccessful();
        $searchData = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($searchData);
        $this->assertArrayHasKey('results', $searchData);

        // 3. Verify
        $client->request(
            'POST',
            sprintf('/parcours/v2/%d/rome/verify', $parcours->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['codes' => ['M1805', 'INVALIDE']])
        );
        $this->assertResponseIsSuccessful();
        $verifyData = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($verifyData);
        $this->assertArrayHasKey('codes', $verifyData);
        $this->assertCount(2, $verifyData['codes']);
        $this->assertSame('M1805', $verifyData['codes'][0]['code']);
        $this->assertTrue($verifyData['codes'][0]['valid']);
        $this->assertFalse($verifyData['codes'][1]['valid']);
    }
}
