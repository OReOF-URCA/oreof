<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormationDeleteTest extends WebTestCase
{
    private function getOrCreateAdmin(EntityManagerInterface $em): User
    {
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-formation-delete']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-formation-delete');
            $admin->setEmail('admin-formation-del@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Delete');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    private function getOrCreateUser(EntityManagerInterface $em): User
    {
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'user-test-no-admin-formation']);

        if ($user === null) {
            $user = new User();
            $user->setUsername('user-test-no-admin-formation');
            $user->setEmail('user-no-admin-form@univ-test.fr');
            $user->setNom('User');
            $user->setPrenom('Standard');
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        return $user;
    }

    private function createFormationTest(EntityManagerInterface $em, bool $withDefaultParcours = true): Formation
    {
        $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        $typeDiplome = $em->getRepository(TypeDiplome::class)->findOneBy([]);
        $formation = new Formation($campagne);
        $formation->setTypeDiplome($typeDiplome);
        $formation->setMentionTexte('Formation Test Deletion ' . uniqid());
        $formation->setSlug('formation-test-del-' . uniqid());
        $em->persist($formation);

        if ($withDefaultParcours) {
            $parcours = new Parcours($formation);
            $parcours->setLibelle(Parcours::PARCOURS_DEFAUT);
            $em->persist($parcours);
        }

        $em->flush();

        return $formation;
    }

    public function testSupprimerRequiresAdminRole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $formation = $this->createFormationTest($em);

        // 1. Non connecté => Redirection vers connexion
        $client->request('GET', '/formation/v2/' . $formation->getId() . '/supprimer');
        $this->assertResponseRedirects('/connexion');

        // 2. Connecté utilisateur simple (non-admin) => 403
        $user = $this->getOrCreateUser($em);
        $client->loginUser($user);

        $client->request('GET', '/formation/v2/' . $formation->getId() . '/supprimer');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSupprimerPageRendersForAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $formation = $this->createFormationTest($em);

        $client->loginUser($admin);
        $client->request('GET', '/formation/v2/' . $formation->getId() . '/supprimer');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Suppression définitive et irréversible', (string) $client->getResponse()->getContent());
        $this->assertSelectorExists('form[action="/formation/v2/' . $formation->getId() . '/supprimer"]');
        $this->assertSelectorExists('input#confirmation_text');
    }

    public function testSupprimerOpensModalViaTurboStream(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $formation = $this->createFormationTest($em);

        $client->loginUser($admin);
        $client->request(
            'GET',
            '/formation/v2/' . $formation->getId() . '/supprimer',
            [],
            [],
            ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('turbo-stream', $content);
        $this->assertStringContainsString('target="modal_title"', $content);
        $this->assertStringContainsString('Supprimer définitivement la formation', $content);
    }

    public function testSupprimerPostRejectsInvalidConfirmationText(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $formation = $this->createFormationTest($em);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/formation/v2/' . $formation->getId() . '/supprimer');
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/formation/v2/' . $formation->getId() . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'texte_incorrect',
        ]);

        $this->assertResponseRedirects('/formation/v2/' . $formation->getId() . '/supprimer');
        $client->followRedirect();
        $this->assertSelectorTextContains('.fixed[role="status"]', 'ne correspond pas au libellé');

        // La formation doit toujours exister
        $formationInDb = $em->getRepository(Formation::class)->find($formation->getId());
        $this->assertNotNull($formationInDb);
    }

    public function testSupprimerPostWithValidConfirmationDeletesFormation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $formation = $this->createFormationTest($em);
        $formationId = $formation->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/formation/v2/' . $formationId . '/supprimer');
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/formation/v2/' . $formationId . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'SUPPRIMER',
            'force' => '1',
        ]);

        $this->assertResponseRedirects();

        // La formation ne doit plus exister en base
        $em->clear();
        $formationInDb = $em->getRepository(Formation::class)->find($formationId);
        $this->assertNull($formationInDb);
    }

    public function testSupprimerPostBlockedWhenMultipleParcours(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $formation = $this->createFormationTest($em, true);

        // Ajout d'un deuxième parcours
        $p2 = new Parcours($formation);
        $p2->setLibelle('Second Parcours');
        $formation->getParcours()->add($p2);
        $em->persist($p2);
        $em->flush();
        $em->clear();

        $client->loginUser($admin);
        $client->request('GET', '/formation/v2/' . $formation->getId() . '/supprimer');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Suppression non autorisée', (string) $client->getResponse()->getContent());
        $this->assertStringContainsString('contient 2 parcours', (string) $client->getResponse()->getContent());
    }

    public function testFormationWithoutParcoursAppearsInFormationList(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $formation = $this->createFormationTest($em, false); // No parcours

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/formation/liste');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#formation_row_' . $formation->getId());
        $this->assertSelectorTextContains('#formation_parcours_count_' . $formation->getId(), 'Aucun parcours');
    }
}
