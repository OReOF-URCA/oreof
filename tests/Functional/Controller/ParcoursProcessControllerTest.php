<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\DpeParcours;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ParcoursProcessControllerTest extends WebTestCase
{
    private function getOrCreateAdmin(): User
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-process']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-process');
            $admin->setEmail('admin-process@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Process');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    public function testApplyModalRendersWithoutWarningWhenViewIsNull(): void
    {
        $client = static::createClient();
        $admin = $this->getOrCreateAdmin();
        $client->loginUser($admin);

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([]);

        if ($dpeParcours === null) {
            $this->markTestSkipped('Aucun DpeParcours disponible pour tester.');
        }

        // Appel de la modale pour une réouverture où TransitionModalViewBuilder retourne null
        $client->request('GET', sprintf('/parcours/v2/process/reouvrir/%d/reouvrir_tacite', $dpeParcours->getId()));

        $this->assertResponseIsSuccessful();
    }
}
