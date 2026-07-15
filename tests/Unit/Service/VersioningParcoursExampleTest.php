<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\VersioningParcours;
use PHPUnit\Framework\TestCase;

/**
 * TEST UNITAIRE - Logique métier SANS dépendances externes
 *
 * Cas d'usage : Tester la logique de versioning sans toucher à la DB
 * Avantages : Rapide, isolé, déterministe
 *
 * À ADAPTER : Remplacez les mocks selon vos vrais services
 */
class VersioningParcoursExampleTest extends TestCase
{
    private VersioningParcours $versioningService;

    /**
     * ✅ Scénario : Versionner un Parcours crée une nouvelle version
     */
    public function testVersioningCreateNewVersion(): void
    {
        $this->markTestIncomplete('À impléter : récupérer la classe VersioningParcours réelle');

        // Arrange
        // $parcours = new Parcours();
        // $parcours->setLibelle('Parcours v1');

        // Act
        // $newVersion = $this->versioningService->createVersion($parcours, 'Modifications initiales');

        // Assert
        // $this->assertNotNull($newVersion);
        // $this->assertEquals(2, $newVersion->getVersionNumber());
    }

    /**
     * ✅ Scénario : Incrémenter la version change le numéro correctement
     */
    public function testVersionNumberIncrements(): void
    {
        $this->markTestIncomplete('À impléter');

        // Arrange
        // $v1 = '1.0.0';

        // Act
        // $v2 = $this->versioningService->incrementMinor($v1);

        // Assert
        // $this->assertEquals('1.1.0', $v2);
    }

    /**
     * ❌ Scénario : Versionner sans raison échoue
     */
    public function testVersioningFailsWithoutReason(): void
    {
        $this->markTestIncomplete('À impléter');

        // $this->expectException(\InvalidArgumentException::class);
        // $this->versioningService->createVersion($parcours, '');
    }

    protected function setUp(): void
    {
        // Créer des mocks pour les dépendances externes
        // $mockRepository = $this->createMock(ParcoursRepository::class);
        // $this->versioningService = new VersioningParcours($mockRepository);

        // Pour l'instant, on montre la structure seulement
        // Remplacez par l'implémentation réelle
    }
}
