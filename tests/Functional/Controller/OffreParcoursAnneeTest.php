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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class OffreParcoursAnneeTest extends WebTestCase
{
    private function getOrCreateAdmin(EntityManagerInterface $em): User
    {
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-annee']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-annee');
            $admin->setEmail('admin-annee@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Annee');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    private function getOrCreateCampagne(EntityManagerInterface $em): CampagneCollecte
    {
        $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        if ($campagne === null) {
            $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy([]);
        }
        if ($campagne === null) {
            $campagne = new CampagneCollecte();
            $campagne->setLibelle('Campagne Test Offre');
            $campagne->setAnnee(2026);
            $campagne->setDefaut(true);
            $em->persist($campagne);
            $em->flush();
        }

        return $campagne;
    }

    public function testModalAddAnneeRendersSuccessfully(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $client->loginUser($admin);

        $parcours = $em->getRepository(Parcours::class)->findOneBy([]);
        if ($parcours === null) {
            $this->markTestSkipped('Aucun Parcours disponible.');
        }

        $client->request('GET', sprintf('/offre/parcours/%d/annee/modal-add', $parcours->getId()));
        $this->assertResponseIsSuccessful();
    }

    public function testSauvegarderAddAnneeAndSupprimerAnneeOnNouveauParcours(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $client->loginUser($admin);

        $campagne = $this->getOrCreateCampagne($em);

        // Trouver une formation existante
        $formation = $em->getRepository(Formation::class)->findOneBy([]);
        if ($formation === null) {
            $this->markTestSkipped('Aucune Formation disponible.');
        }

        // Créer un parcours nouvellement créé
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Test Nouveau ' . uniqid());
        $em->persist($parcours);
        $formation->addParcour($parcours);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setCampagneCollecte($campagne);
        $dpeParcours->setFormation($formation);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::CREATION);
        $em->persist($dpeParcours);
        $parcours->addDpeParcour($dpeParcours);

        $em->flush();

        // Récupérer la modale d'ajout pour obtenir le token CSRF
        $crawlerAdd = $client->request('GET', sprintf('/offre/parcours/%d/annee/modal-add', $parcours->getId()));
        $this->assertResponseIsSuccessful();
        $tokenAdd = (string)$crawlerAdd->filter('input[name="_token"]')->attr('value');

        // Choisir un ordre d'année valide et disponible pour ce parcours
        $ordresDispos = $parcours->getOrdresAnneesDisponibles();
        $this->assertNotEmpty($ordresDispos, 'Le parcours doit avoir au moins un ordre disponible.');
        $ordreValide = $ordresDispos[0];

        // Ajouter l'année avec cet ordre valide
        $client->request('POST', sprintf('/offre/parcours/%d/annee/modal-add/sauvegarder', $parcours->getId()), [
            '_token' => $tokenAdd,
            'ordre' => $ordreValide,
        ]);

        $this->assertResponseIsSuccessful();

        $em->clear();
        $anneeCreated = $em->getRepository(Annee::class)->findOneBy([
            'parcours' => $parcours->getId(),
            'ordre' => $ordreValide,
        ]);

        $this->assertNotNull($anneeCreated, sprintf('L\'année %d doit avoir été créée.', $ordreValide));
        $this->assertSame($ordreValide, $anneeCreated->getOrdre());

        // Test modal delete
        $crawlerDel = $client->request('GET', sprintf('/offre/annee/%d/modal-delete', $anneeCreated->getId()));
        $this->assertResponseIsSuccessful();
        $tokenDelete = (string)$crawlerDel->filter('input[name="csrf_token"]')->attr('value');

        // Test suppression
        $client->request('POST', sprintf('/offre/annee/%d/supprimer', $anneeCreated->getId()), [
            'csrf_token' => $tokenDelete,
        ]);

        $this->assertResponseIsSuccessful();

        $em->clear();
        $anneeDeleted = $em->getRepository(Annee::class)->find($anneeCreated->getId());
        $this->assertNull($anneeDeleted, 'L\'année doit avoir été supprimée.');
    }

    public function testCannotAddAnneeIncoherentePourTypeDiplome(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $client->loginUser($admin);

        $campagne = $this->getOrCreateCampagne($em);

        // Trouver une formation avec un type de diplôme défini
        $formation = $em->getRepository(Formation::class)->createQueryBuilder('f')
            ->join('f.typeDiplome', 'td')
            ->where('td.semestreFin IS NOT NULL')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($formation === null) {
            $this->markTestSkipped('Aucune Formation avec type de diplôme disponible.');
        }

        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Test Validation Bornes ' . uniqid());
        $em->persist($parcours);
        $formation->addParcour($parcours);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setCampagneCollecte($campagne);
        $dpeParcours->setFormation($formation);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::CREATION);
        $em->persist($dpeParcours);
        $parcours->addDpeParcour($dpeParcours);

        $em->flush();

        $crawler = $client->request('GET', sprintf('/offre/parcours/%d/annee/modal-add', $parcours->getId()));
        $this->assertResponseIsSuccessful();
        $tokenAdd = (string)$crawler->filter('input[name="_token"]')->attr('value');

        // Calculer un ordre strictement supérieur au max autorisé (ex: BUT4 si max = 3)
        $ordresAutorises = $parcours->getOrdresAnneesPossibles();
        $ordreIncoherent = max($ordresAutorises) + 1;

        // Tenter d'ajouter l'ordre incohérent
        $client->request('POST', sprintf('/offre/parcours/%d/annee/modal-add/sauvegarder', $parcours->getId()), [
            '_token' => $tokenAdd,
            'ordre' => $ordreIncoherent,
        ]);

        $this->assertResponseIsSuccessful();
        $content = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('incohérente', $content);

        // Vérifier qu'aucune année avec cet ordre n'a été créée
        $em->clear();
        $anneeInvalide = $em->getRepository(Annee::class)->findOneBy([
            'parcours' => $parcours->getId(),
            'ordre' => $ordreIncoherent,
        ]);
        $this->assertNull($anneeInvalide, 'Aucune année avec un ordre incohérent ne doit avoir été créée.');
    }

    public function testCannotDeleteAnneeOnReconductedParcours(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $admin = $this->getOrCreateAdmin($em);
        $client->loginUser($admin);

        $campagne = $this->getOrCreateCampagne($em);

        $formation = $em->getRepository(Formation::class)->findOneBy([]);
        if ($formation === null) {
            $this->markTestSkipped('Aucune Formation disponible.');
        }

        // Créer un faux parcours N-1 et un parcours reconduit
        $parcoursN1 = new Parcours($formation);
        $parcoursN1->setLibelle('Parcours N-1 ' . uniqid());
        $em->persist($parcoursN1);

        $parcoursReconduit = new Parcours($formation);
        $parcoursReconduit->setLibelle('Parcours Reconduit ' . uniqid());
        $parcoursReconduit->setParcoursOrigine($parcoursN1);
        $em->persist($parcoursReconduit);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcoursReconduit);
        $dpeParcours->setCampagneCollecte($campagne);
        $dpeParcours->setFormation($formation);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
        $em->persist($dpeParcours);

        $annee = new Annee();
        $annee->setParcours($parcoursReconduit);
        $annee->setOrdre(1);
        $em->persist($annee);
        $parcoursReconduit->addAnnee($annee);

        $em->flush();

        // Tester que la modal de suppression rejette la suppression pour un parcours reconduit
        $client->request('GET', sprintf('/offre/annee/%d/modal-delete', $annee->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Seules les années des parcours nouvellement créés', (string)$client->getResponse()->getContent());

        // Générer un jeton CSRF valide avec la session de la requête en cours
        static::getContainer()->get('request_stack')->push($client->getRequest());
        /** @var CsrfTokenManagerInterface $tokenManager */
        $tokenManager = static::getContainer()->get('security.csrf.token_manager');
        $tokenDelete = (string)$tokenManager->getToken('annee_delete_' . $annee->getId());

        // Tenter de supprimer l'année sur ce parcours reconduit
        $client->request('POST', sprintf('/offre/annee/%d/supprimer', $annee->getId()), [
            'csrf_token' => $tokenDelete,
        ]);

        $this->assertResponseIsSuccessful();
        $responseContent = (string)$client->getResponse()->getContent();
        $this->assertStringContainsString('Seules les années des parcours nouvellement créés', $responseContent);

        $em->clear();
        $anneeStillExists = $em->getRepository(Annee::class)->find($annee->getId());
        $this->assertNotNull($anneeStillExists, 'L\'année d\'un parcours reconduit ne doit PAS être supprimée.');
    }
}
