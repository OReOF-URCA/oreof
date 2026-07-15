<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Support\TestCase;
use App\Tests\Support\DatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TEST FONCTIONNEL - Routes HTTP et Authentification
 *
 * Cas d'usage : Tester les contrôleurs, authentification, réponses HTTP
 * AVEC conteneur Symfony complet + client HTTP
 */
class ParcoursControllerExampleTest extends WebTestCase
{
    use DatabaseTrait;

    private $client;

    /**
     * ✅ Scénario : Affiche la liste des parcours
     */
    public function testListParcoursPageIsSuccessful(): void
    {
        // Act
        $this->client->request('GET', '/parcours');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertPageTitleContains('Parcours');
    }

    /**
     * ✅ Scénario : Crée un parcours valide
     */
    public function testCreateParcoursFormSubmit(): void
    {
        $this->markTestIncomplete('À impléter selon vos routes');

        // Act
        // $this->client->request('GET', '/parcours/new');
        // $this->client->submitForm('Create', [
        //     'Parcours[libelle]' => 'Mon Parcours',
        //     'Parcours[libelleCourt]' => 'MP',
        // ]);

        // Assert
        // $this->assertResponseRedirects();
        // $this->client->followRedirect();
        // $this->assertPageTitleContains('Mon Parcours');
    }

    /**
     * ❌ Scénario : Création échoue avec données invalides
     */
    public function testCreateParcoursFailsWithInvalidData(): void
    {
        $this->markTestIncomplete('À impléter');

        // $this->client->request('GET', '/parcours/new');
        // $this->client->submitForm('Create', [
        //     'Parcours[libelle]' => '', // Invalide
        // ]);

        // $this->assertResponseUnprocessableEntity();
        // $this->assertSelectorExists('.alert-danger');
    }

    /**
     * ❌ Scénario : Accès non authentifié redirige
     */
    public function testUnAuthenticatedUserRedirected(): void
    {
        $this->markTestIncomplete('À impléter selon votre sécurité');

        // $this->client->request('GET', '/parcours/admin');
        // $this->assertResponseRedirects('/login');
    }

    /**
     * ✅ Scénario : Suppression avec confirmation
     */
    public function testDeleteParcoursRequiresConfirmation(): void
    {
        $this->markTestIncomplete('À impléter');

        // $this->client->request('POST', '/parcours/123/delete');
        // $this->assertResponseStatusCodeSame(403); // CSRF ou confirmation
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        // Si besoin d'authentification :
        // $this->client->loginUser($this->createTestUser());
    }
}
