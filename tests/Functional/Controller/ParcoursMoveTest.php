<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CampagneCollecte;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ParcoursMoveTest extends WebTestCase
{
    private function getOrCreateSesUser(EntityManagerInterface $em): User
    {
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'ses-test-parcours-move']);

        if ($user === null) {
            $user = new User();
            $user->setUsername('ses-test-parcours-move');
            $user->setEmail('ses-parcours-move@univ-test.fr');
            $user->setNom('SES');
            $user->setPrenom('User');
            $em->persist($user);
        }
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        return $user;
    }

    private function getOrCreateSimpleUser(EntityManagerInterface $em): User
    {
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'user-test-no-ses']);

        if ($user === null) {
            $user = new User();
            $user->setUsername('user-test-no-ses');
            $user->setEmail('user-no-ses@univ-test.fr');
            $user->setNom('User');
            $user->setPrenom('Simple');
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        return $user;
    }

    private function createFormationsAndParcours(EntityManagerInterface $em): array
    {
        $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        $typeDiplome = $em->getRepository(TypeDiplome::class)->findOneBy([]);

        $f1 = new Formation($campagne);
        $f1->setTypeDiplome($typeDiplome);
        $f1->setMentionTexte('Formation Source ' . uniqid());
        $f1->setSlug('form-src-' . uniqid());
        $f1->setHasParcours(true);
        $em->persist($f1);

        $f2 = new Formation($campagne);
        $f2->setTypeDiplome($typeDiplome);
        $f2->setMentionTexte('Formation Cible ' . uniqid());
        $f2->setSlug('form-target-' . uniqid());
        $f2->setHasParcours(false);
        $em->persist($f2);

        $parcours = new Parcours($f1);
        $parcours->setLibelle('Parcours A Déplacer ' . uniqid());
        $em->persist($parcours);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setFormation($f1);
        $dpeParcours->setCampagneCollecte($campagne);
        $em->persist($dpeParcours);

        $em->flush();

        return [$f1, $f2, $parcours, $dpeParcours];
    }

    public function testDeplacerRequiresSesRole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        [, , $parcours] = $this->createFormationsAndParcours($em);

        // 1. Non authentifié => Redirection vers connexion
        $client->request('GET', '/parcours/v2/ses/deplacer/' . $parcours->getId());
        $this->assertResponseRedirects('/connexion');

        // 2. Simple utilisateur non-SES => 403
        $simpleUser = $this->getOrCreateSimpleUser($em);
        $client->loginUser($simpleUser);
        $client->request('GET', '/parcours/v2/ses/deplacer/' . $parcours->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeplacerModalRendersForSesUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $sesUser = $this->getOrCreateSesUser($em);
        [, $f2, $parcours] = $this->createFormationsAndParcours($em);

        $client->loginUser($sesUser);
        $client->request('GET', '/parcours/v2/ses/deplacer/' . $parcours->getId(), [], [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html',
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Déplacement de parcours', $content);
        $this->assertStringContainsString('Nouvelle formation de destination', $content);
        $this->assertStringContainsString('value="' . $f2->getId() . '"', $content);
    }

    public function testDeplacerPostFailsOnBadConfirmation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $sesUser = $this->getOrCreateSesUser($em);
        [, $f2, $parcours] = $this->createFormationsAndParcours($em);

        $client->loginUser($sesUser);

        // Fetch modal to get CSRF token
        $crawler = $client->request('GET', '/parcours/v2/ses/deplacer/' . $parcours->getId());
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/ses/deplacer/' . $parcours->getId(), [
            '_token' => $token,
            'target_formation_id' => $f2->getId(),
            'confirmation_text' => 'MAUVAIS_TEXTE',
        ]);

        $this->assertResponseRedirects('/parcours/v2/ses/deplacer/' . $parcours->getId());
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger, [role="alert"]');
    }

    public function testDeplacerPostSucceeds(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $sesUser = $this->getOrCreateSesUser($em);
        [$f1, $f2, $parcours, $dpeParcours] = $this->createFormationsAndParcours($em);

        $client->loginUser($sesUser);

        $crawler = $client->request('GET', '/parcours/v2/ses/deplacer/' . $parcours->getId());
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/parcours/v2/ses/deplacer/' . $parcours->getId(), [
            '_token' => $token,
            'target_formation_id' => $f2->getId(),
            'confirmation_text' => 'DEPLACER',
        ]);

        $this->assertResponseRedirects('/parcours/v2/' . $parcours->getId());

        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $reloadedParcours = $em->getRepository(Parcours::class)->find($parcours->getId());
        $reloadedDpe = $em->getRepository(DpeParcours::class)->find($dpeParcours->getId());
        $reloadedF1 = $em->getRepository(Formation::class)->find($f1->getId());
        $reloadedF2 = $em->getRepository(Formation::class)->find($f2->getId());

        $this->assertNotNull($reloadedParcours);
        $this->assertSame($f2->getId(), $reloadedParcours->getFormation()?->getId());
        $this->assertFalse($reloadedParcours->isParcoursDefaut());
        $this->assertSame($f2->getId(), $reloadedDpe?->getFormation()?->getId());
        $this->assertTrue($reloadedF2?->isHasParcours());
        $this->assertFalse($reloadedF1?->isHasParcours());
    }
}
