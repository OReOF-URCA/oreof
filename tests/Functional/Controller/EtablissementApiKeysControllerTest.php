<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Etablissement;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EtablissementApiKeysControllerTest extends WebTestCase
{
    private function getOrCreateEtablissement(): Etablissement
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $etablissement = $em->getRepository(Etablissement::class)->findOneBy([]);

        if ($etablissement === null) {
            $etablissement = new Etablissement();
            $etablissement->setLibelle('Université Test');
            $em->persist($etablissement);
            $em->flush();
        }

        return $etablissement;
    }

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

    public function testUnauthenticatedUserIsRedirected(): void
    {
        $client = static::createClient();
        $etablissement = $this->getOrCreateEtablissement();

        $client->request('GET', sprintf('/administration/etablissement/%d/api-keys', $etablissement->getId()));
        $this->assertResponseRedirects('/connexion');
    }

    public function testAdminCanViewAndSubmitApiKeys(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateAdmin();
        $etablissement = $this->getOrCreateEtablissement();

        $client->loginUser($admin);
        $crawler = $client->request('GET', sprintf('/administration/etablissement/%d/api-keys', $etablissement->getId()));

        // Soumission du formulaire avec ajout d'une configuration d'API tierce
        $tokenNode = $crawler->filter('input[name*="_token"]');
        $formData = [
            'apiKeys' => [
                [
                    'plateforme' => 'Apogée',
                    'url' => 'https://apogee.univ-reims.fr/api',
                    'login' => 'user_apogee',
                    'apiKey' => 'mon_token_secret',
                ],
            ],
        ];

        if ($tokenNode->count() > 0) {
            $formData['_token'] = $tokenNode->attr('value');
        }

        $client->request('POST', sprintf('/administration/etablissement/%d/api-keys', $etablissement->getId()), [
            'etablissement_api_keys' => $formData,
        ]);

        $this->assertResponseRedirects(sprintf('/administration/etablissement/%d/api-keys', $etablissement->getId()));

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $updatedEtablissement = $em->getRepository(Etablissement::class)->find($etablissement->getId());
        $this->assertNotNull($updatedEtablissement);
        $this->assertCount(1, $updatedEtablissement->getApiKeys());
        $this->assertSame('Apogée', $updatedEtablissement->getApiKeys()[0]['plateforme']);
        $this->assertSame('mon_token_secret', $updatedEtablissement->getApiKeys()[0]['apiKey']);
    }
}
