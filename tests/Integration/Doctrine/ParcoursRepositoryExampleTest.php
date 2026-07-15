<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\Parcours;
use App\Tests\Support\TestCase;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Fixtures\EntityFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;

/**
 * TEST D'INTÉGRATION - Persistance en base de données
 *
 * Cas d'usage : Tester les entités, relations, et requêtes Doctrine
 * AVEC la base de données (dans une transaction de test)
 *
 * ⚠️  À adapter : Hérite de TestCase + utilise DatabaseTrait
 */
class ParcoursRepositoryExampleTest extends TestCase
{
    use DatabaseTrait;
    use EntityFixturesTrait;

    /**
     * ✅ Scénario : Persiste et récupère un Parcours
     */
    public function testPersistAndRetrieveParcours(): void
    {
        // Arrange
        $parcours = $this->createMinimalParcours([
            'libelle' => 'Parcours Persisté',
            'libelleCourt' => 'PP',
        ]);

        // Act
        $this->persist($parcours);
        $id = $parcours->getId();

        $this->clearEntityManager();
        $retrieved = $this->entityManager
            ->getRepository(Parcours::class)
            ->find($id);

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals('Parcours Persisté', $retrieved->getLibelle());
        $this->assertEquals('PP', $retrieved->getLibelleCourt());
    }

    /**
     * ✅ Scénario : Récupère tous les Parcours actifs
     */
    public function testFindAllActiveParcours(): void
    {
        // Arrange
        $p1 = $this->createMinimalParcours(['libelle' => 'Actif 1', 'actif' => true]);
        $p2 = $this->createMinimalParcours(['libelle' => 'Actif 2', 'actif' => true]);
        $p3 = $this->createMinimalParcours(['libelle' => 'Inactif', 'actif' => false]);

        $this->persistAll([$p1, $p2, $p3]);

        // Act
        $actifs = $this->entityManager
            ->createQuery('SELECT p FROM App\Entity\Parcours p WHERE p.actif = true')
            ->getResult();

        // Assert
        $this->assertCount(2, $actifs);
    }

    /**
     * ❌ Scénario : Validation échoue avec libellé vide
     */
    public function testValidationFailsWithoutLibelle(): void
    {
        $this->markTestIncomplete('À impléter selon vos contraintes Doctrine');

        // $parcours = new Parcours();
        // $parcours->setLibelleCourt('X');

        // $this->expectException(\InvalidArgumentException::class);
        // $this->persist($parcours);
    }

    /**
     * ✅ Scénario : Supprime un Parcours (avec cascade ?)
     */
    public function testDeleteParcours(): void
    {
        // Arrange
        $parcours = $this->createMinimalParcours(['libelle' => 'À Supprimer']);
        $this->persist($parcours);
        $id = $parcours->getId();

        // Act
        $this->entityManager->remove($parcours);
        $this->entityManager->flush();

        $this->clearEntityManager();
        $retrieved = $this->entityManager->find(Parcours::class, $id);

        // Assert
        $this->assertNull($retrieved);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }
}
