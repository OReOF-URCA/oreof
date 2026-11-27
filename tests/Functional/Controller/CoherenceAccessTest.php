<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CoherenceAccessTest extends WebTestCase
{
    public function testNonAdminUserIsDeniedAccessToCoherenceRoutes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $parcours = $em->getRepository(Parcours::class)->findOneBy([]);
        $fiche = $em->getRepository(FicheMatiere::class)->findOneBy([]);
        self::assertNotNull($parcours);
        self::assertNotNull($fiche);

        $user = $this->getOrCreateStandardUser();
        $client->loginUser($user);

        $client->request('GET', '/coherence/parcours/' . $parcours->getId());
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('POST', '/coherence/parcours/' . $parcours->getId() . '/analyser');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('GET', '/coherence/fiche-matiere/' . $fiche->getId());
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('POST', '/coherence/fiche-matiere/' . $fiche->getId() . '/analyser');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('GET', '/administration/pilotage-coherence');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminUserCanAccessCoherenceRoutes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test']);
        $parcours = $em->getRepository(Parcours::class)->findOneBy([]);
        $fiche = $em->getRepository(FicheMatiere::class)->findOneBy([]);
        $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);

        self::assertNotNull($admin);
        self::assertNotNull($parcours);
        self::assertNotNull($fiche);

        $client->loginUser($admin);
        $client->request('GET', '/');
        if ($campagne !== null) {
            $client->getRequest()->getSession()->set('campagneCollecte', $campagne->getId());
        }

        $client->request('GET', '/coherence/parcours/' . $parcours->getId());
        self::assertResponseIsSuccessful();

        $client->request('GET', '/coherence/fiche-matiere/' . $fiche->getId());
        self::assertResponseIsSuccessful();

        $client->request('GET', '/administration/pilotage-coherence');
        self::assertResponseIsSuccessful();
    }

    private function getOrCreateStandardUser(): User
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'user-coherence-test']);

        if ($user === null) {
            $user = (new User())
                ->setUsername('user-coherence-test')
                ->setEmail('user-coherence@oreof.invalid')
                ->setRoles(['ROLE_USER']);
            $user->setNom('User');
            $user->setPrenom('Test');
            $em->persist($user);
            $em->flush();
        }

        return $user;
    }
}
