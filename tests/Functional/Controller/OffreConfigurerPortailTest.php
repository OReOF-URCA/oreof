<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Annee;
use App\Entity\Formation;
use App\Entity\Portail;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Synchronisation des années rattachées à un portail (entre formations).
 */
class OffreConfigurerPortailTest extends WebTestCase
{
    /** @var list<int> */
    private array $portailIds = [];

    /** @var array<int, int> capacité d'origine par id d'année, restaurée en fin de test */
    private array $capacitesOrigine = [];

    private function getOrCreateAdmin(EntityManagerInterface $em): User
    {
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin-test-portail']);

        if ($admin === null) {
            $admin = new User();
            $admin->setUsername('admin-test-portail');
            $admin->setEmail('admin-portail@univ-test.fr');
            $admin->setNom('Admin');
            $admin->setPrenom('Portail');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    /**
     * Années libres (sans portail) des formations sans tronc commun, groupées par formation.
     *
     * @return array<int, list<Annee>>
     */
    private function anneesParFormation(EntityManagerInterface $em): array
    {
        $annees = $em->getRepository(Annee::class)->createQueryBuilder('a')
            ->join('a.parcours', 'p')
            ->join('p.formation', 'f')
            ->where('a.portail IS NULL')
            ->andWhere('f.slug IS NOT NULL')
            ->andWhere('a.ordre = 1')
            ->setMaxResults(400)
            ->getQuery()
            ->getResult();

        $parFormation = [];
        foreach ($annees as $annee) {
            $formation = $annee->getParcours()?->getFormation();
            if ($formation === null || $formation->getTroncsCommuns() !== [] || $annee->getParcours()?->getId() === null) {
                continue;
            }
            $parFormation[$formation->getId()][] = $annee;
        }

        return $parFormation;
    }

    /**
     * @param list<array{0: string, 1: string}> $champs
     */
    private function sauvegarder(KernelBrowser $client, Formation $formation, array $champs): void
    {
        $crawler = $client->request('GET', sprintf('/offre/%s/configurer', $formation->getSlug()));
        $this->assertResponseIsSuccessful();

        $paires = ['_token=' . urlencode((string) $crawler->filter('input[name="_token"]')->first()->attr('value'))];
        foreach ($champs as [$cle, $valeur]) {
            $paires[] = str_replace(['%5B', '%5D'], ['[', ']'], urlencode($cle)) . '=' . urlencode($valeur);
        }
        parse_str(implode('&', $paires), $corps);

        $client->request('POST', sprintf('/offre/%s/configurer/sauvegarder', $formation->getSlug()), $corps, [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->portailIds !== [] || $this->capacitesOrigine !== []) {
            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get('doctrine.orm.entity_manager');
            $em->clear();
            foreach ($this->portailIds as $id) {
                $portail = $em->getRepository(Portail::class)->find($id);
                if ($portail !== null) {
                    foreach ($portail->getAnnees() as $annee) {
                        $annee->setPortail(null);
                    }
                    $em->remove($portail);
                }
            }
            foreach ($this->capacitesOrigine as $anneeId => $capacite) {
                $annee = $em->getRepository(Annee::class)->find($anneeId);
                $annee?->setCapaciteAccueil($capacite);
            }
            $em->flush();
        }

        parent::tearDown();
    }

    public function testCreationPortailSynchroniseImmediatementLesAutresFormations(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client->loginUser($this->getOrCreateAdmin($em));

        $parFormation = $this->anneesParFormation($em);
        if (count($parFormation) < 2) {
            $this->markTestSkipped('Deux formations sans tronc commun sont nécessaires.');
        }
        [$premiere, $seconde] = array_slice(array_values($parFormation), 0, 2);
        $source = $premiere[0];
        $cible = $seconde[0];
        $formationSource = $source->getParcours()->getFormation();
        $this->capacitesOrigine[$source->getId()] = (int) $source->getCapaciteAccueil();
        $this->capacitesOrigine[$cible->getId()] = (int) $cible->getCapaciteAccueil();
        $sourceId = $source->getId();
        $cibleId = $cible->getId();
        $formationCibleId = $cible->getParcours()->getFormation()->getId();

        $this->sauvegarder($client, $formationSource, [
            ['has_portail_config', '1'],
            ['portails[nouveau][libelle]', 'Portail test sync'],
            ['portails[nouveau][annees][]', (string) $sourceId],
            ['portails[nouveau][annees][]', (string) $cibleId],
            ['portails[nouveau][formations_presentes][]', (string) $formationSource->getId()],
            ['portails[nouveau][formations_presentes][]', (string) $formationCibleId],
            ['annee_' . $sourceId . '_capaciteAccueil', '37'],
        ]);
        $this->assertResponseIsSuccessful();

        $em->clear();
        $source = $em->getRepository(Annee::class)->find($sourceId);
        $cible = $em->getRepository(Annee::class)->find($cibleId);
        self::assertNotNull($source->getPortail());
        $this->portailIds[] = $source->getPortail()->getId();
        self::assertSame($source->getPortail()->getId(), $cible->getPortail()?->getId());
        self::assertSame(37, $source->getCapaciteAccueil());
        self::assertSame(37, $cible->getCapaciteAccueil(), 'La capacité doit être propagée dès la création du portail.');
    }

    public function testSaisieSurUnParcoursNEstPasEcraseeParUnParcoursDeMemeOrdre(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $client->loginUser($this->getOrCreateAdmin($em));

        $paire = null;
        foreach ($this->anneesParFormation($em) as $annees) {
            if (count($annees) >= 2) {
                $paire = array_slice($annees, 0, 2);
                break;
            }
        }
        if ($paire === null) {
            $this->markTestSkipped('Une formation avec deux parcours d\'ordre 1 est nécessaire.');
        }
        [$premiere, $seconde] = $paire;
        $formation = $premiere->getParcours()->getFormation();
        $premiereId = $premiere->getId();
        $secondeId = $seconde->getId();
        $this->capacitesOrigine[$premiereId] = (int) $premiere->getCapaciteAccueil();
        $this->capacitesOrigine[$secondeId] = (int) $seconde->getCapaciteAccueil();

        // Création du portail (les deux années du même ordre)
        $this->sauvegarder($client, $formation, [
            ['has_portail_config', '1'],
            ['portails[nouveau][libelle]', 'Portail test source'],
            ['portails[nouveau][annees][]', (string) $premiereId],
            ['portails[nouveau][annees][]', (string) $secondeId],
            ['portails[nouveau][formations_presentes][]', (string) $formation->getId()],
            ['annee_' . $secondeId . '_capaciteAccueil', '9'],
        ]);
        $em->clear();
        $portailId = $em->getRepository(Annee::class)->find($secondeId)->getPortail()?->getId();
        self::assertNotNull($portailId);
        $this->portailIds[] = $portailId;

        // Saisie sur la seconde année uniquement : elle doit devenir la source
        $this->sauvegarder($client, $em->getRepository(Formation::class)->find($formation->getId()), [
            ['annee_' . $secondeId . '_capaciteAccueil', '12'],
        ]);
        $this->assertResponseIsSuccessful();

        $em->clear();
        self::assertSame(12, $em->getRepository(Annee::class)->find($secondeId)->getCapaciteAccueil());
        self::assertSame(12, $em->getRepository(Annee::class)->find($premiereId)->getCapaciteAccueil());
    }
}
