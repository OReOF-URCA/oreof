<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\TypeModificationDpeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class OffreConfigurerSansCapaciteTest extends WebTestCase
{
    private function getOrCreateAdmin(EntityManagerInterface $em): User
    {
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-sans-capacite']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-sans-capacite');
            $admin->setEmail('admin-sans-capacite@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Capacite');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    /**
     * @param array<string, string> $champs
     */
    private function sauvegarder(KernelBrowser $client, Formation $formation, array $champs): void
    {
        static::getContainer()->get('request_stack')->push($client->getRequest());
        /** @var CsrfTokenManagerInterface $tokenManager */
        $tokenManager = static::getContainer()->get('security.csrf.token_manager');
        $champs['_token'] = (string) $tokenManager->getToken('offre_v2_configurer_' . $formation->getId());

        $client->request('POST', sprintf('/offre/%s/configurer/sauvegarder', $formation->getSlug()), $champs);
    }

    public function testCasePasDeCapaciteEnregistreeEtCapaciteRemiseAZero(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client->loginUser($this->getOrCreateAdmin($em));

        $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        $formation = $em->getRepository(Formation::class)->createQueryBuilder('f')
            ->where('f.slug IS NOT NULL')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($campagne === null || $formation === null) {
            $this->markTestSkipped('Campagne par défaut ou formation avec slug indisponible.');
        }

        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Test Sans Capacité ' . uniqid());
        $em->persist($parcours);
        $formation->addParcour($parcours);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setCampagneCollecte($campagne);
        $dpeParcours->setFormation($formation);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
        $em->persist($dpeParcours);
        $parcours->addDpeParcour($dpeParcours);

        $annee = new Annee();
        $annee->setParcours($parcours);
        $annee->setOrdre(2);
        $annee->setCapaciteAccueil(30);
        $em->persist($annee);
        $parcours->addAnnee($annee);
        $em->flush();

        $anneeId = $annee->getId();
        $client->request('GET', '/');

        // Case cochée : pas de capacité, la capacité saisie est ignorée
        $this->sauvegarder($client, $formation, [
            'annee_' . $anneeId . '_capaciteAccueil' => '30',
            'annee_' . $anneeId . '_sansCapacite_present' => '1',
            'annee_' . $anneeId . '_sansCapacite' => '1',
        ]);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $annee = $em->getRepository(Annee::class)->find($anneeId);
        self::assertFalse($annee->hasCapacite());
        self::assertSame(0, $annee->getCapaciteAccueil());

        // Case décochée : la capacité redevient saisissable
        $this->sauvegarder($client, $formation, [
            'annee_' . $anneeId . '_capaciteAccueil' => '25',
            'annee_' . $anneeId . '_sansCapacite_present' => '1',
        ]);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $annee = $em->getRepository(Annee::class)->find($anneeId);
        self::assertTrue($annee->hasCapacite());
        self::assertSame(25, $annee->getCapaciteAccueil());
    }
}
