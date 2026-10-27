<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ParcoursDeleteTest extends WebTestCase
{
    private function getOrCreateAdmin(EntityManagerInterface $em): User
    {
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-parcours-delete']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-parcours-delete');
            $admin->setEmail('admin-parcours-del@univ-test.fr');
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
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'user-test-no-admin']);

        if ($user === null) {
            $user = new User();
            $user->setUsername('user-test-no-admin');
            $user->setEmail('user-no-admin@univ-test.fr');
            $user->setNom('User');
            $user->setPrenom('Standard');
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        return $user;
    }

    private function getOrCreateParcoursTest(EntityManagerInterface $em): Parcours
    {
        $formation = $em->getRepository(Formation::class)->findOneBy([]);
        if ($formation === null) {
            $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
            $formation = new Formation($campagne);
            $formation->setMentionTexte('Formation Test Deletion');
            $formation->setSlug('formation-test-deletion-' . uniqid());
            $em->persist($formation);
        }

        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Test Delete ' . uniqid());
        $em->persist($parcours);
        $em->flush();

        return $parcours;
    }

    public function testSupprimerRequiresAdminRole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $parcours = $this->getOrCreateParcoursTest($em);

        // 1. Non connecté => Redirection vers connexion
        $client->request('GET', '/parcours/v2/' . $parcours->getId() . '/supprimer');
        $this->assertResponseRedirects('/connexion');

        // 2. Connecté utilisateur simple (non-admin) => 403
        $user = $this->getOrCreateUser($em);
        $client->loginUser($user);

        $client->request('GET', '/parcours/v2/' . $parcours->getId() . '/supprimer');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSupprimerPageRendersForAdmin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/parcours/v2/' . $parcours->getId() . '/supprimer');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Suppression définitive et irréversible', (string) $client->getResponse()->getContent());
        $this->assertSelectorExists('form[action="/parcours/v2/' . $parcours->getId() . '/supprimer"]');
        $this->assertSelectorExists('input#confirmation_text');
    }

    public function testSupprimerPostRejectsInvalidConfirmationText(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/parcours/v2/' . $parcours->getId() . '/supprimer');
        $csrfToken = (string)$crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/' . $parcours->getId() . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'texte_totalement_incorrect',
        ]);

        $this->assertResponseRedirects('/parcours/v2/' . $parcours->getId() . '/supprimer');
        $client->followRedirect();
        $this->assertSelectorTextContains('.fixed[role="status"]', 'ne correspond pas au libellé');

        // Le parcours doit toujours exister
        $parcoursInDb = $em->getRepository(Parcours::class)->find($parcours->getId());
        $this->assertNotNull($parcoursInDb);
    }

    public function testSupprimerPostWithValidConfirmationDeletesParcours(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);
        $parcoursId = $parcours->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/parcours/v2/' . $parcoursId . '/supprimer');
        $csrfToken = (string)$crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/' . $parcoursId . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'SUPPRIMER',
            'force' => '1',
        ]);

        $this->assertResponseRedirects();

        // Le parcours ne doit plus exister en base
        $em->clear();
        $parcoursInDb = $em->getRepository(Parcours::class)->find($parcoursId);
        $this->assertNull($parcoursInDb);
    }

    public function testSupprimerOpensModalViaTurboStream(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);

        $client->loginUser($admin);
        $client->request('GET', '/parcours/v2/' . $parcours->getId() . '/supprimer', [], [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('target="modal_title"', $content);
        $this->assertStringContainsString('Supprimer définitivement le parcours', $content);
        $this->assertStringContainsString('target="modal_body"', $content);
        $this->assertStringContainsString('id="modal_form"', $content);
        $this->assertStringContainsString('name="confirmation_text"', $content);
        $this->assertStringContainsString('target="modal_footer"', $content);
        $this->assertStringContainsString('app-modal-footer-btn--danger', $content);
    }

    public function testSupprimerPostTurboStreamRejectsInvalidConfirmation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);

        $client->loginUser($admin);

        // Récupérer le token CSRF
        $crawler = $client->request('GET', '/parcours/v2/' . $parcours->getId() . '/supprimer');
        $csrfToken = (string)$crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/' . $parcours->getId() . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'faux_texte',
        ], [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('target="flash_toasts"', $content);
        $this->assertStringContainsString('border-l-danger', $content);
        $this->assertStringContainsString('ne correspond pas au libellé', $content);
    }

    public function testSupprimerPostRedirectsToReferer(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);
        $parcoursId = $parcours->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/parcours/v2/' . $parcoursId . '/supprimer');
        $csrfToken = (string)$crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/' . $parcoursId . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'SUPPRIMER',
            'force' => '1',
        ], [], [
            'HTTP_REFERER' => 'http://localhost/formation',
        ]);

        $this->assertResponseRedirects('http://localhost/formation');
    }

    public function testSupprimerPostTurboStreamSuccess(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $parcours = $this->getOrCreateParcoursTest($em);
        $formationId = $parcours->getFormation()?->getId();
        $parcoursId = $parcours->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/parcours/v2/' . $parcoursId . '/supprimer');
        $csrfToken = (string)$crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/' . $parcoursId . '/supprimer', [
            '_token' => $csrfToken,
            'confirmation_text' => 'SUPPRIMER',
            'force' => '1',
        ], [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_REFERER' => 'http://localhost/formation',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('target="parcours_' . $formationId . '"', $content);
        $this->assertStringContainsString('target="formation_parcours_count_' . $formationId . '"', $content);
        $this->assertStringContainsString('target="flash_toasts"', $content);
        $this->assertStringContainsString('a été définitivement supprimé', $content);
    }
}

