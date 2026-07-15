<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\StructureParcours;
use PHPUnit\Framework\TestCase;

/**
 * TEST UNITAIRE - Validation des DTO
 *
 * Cas d'usage : Tester la transformation et validation de données
 * Entre couches (requête HTTP → DTO → Service → Entity)
 */
class StructureParcoursExampleTest extends TestCase
{
    /**
     * ✅ Scénario : Crée un DTO avec données valides
     */
    public function testCreateWithValidData(): void
    {
        // Arrange
        $data = [
            'libelle' => 'Parcours 2024-2025',
            'libelleCourt' => 'P2024',
            'nombreSemesters' => 4,
            'ectsTotal' => 120,
        ];

        // Act
        try {
            $dto = new StructureParcours($data);

            // Assert
            $this->assertEquals('Parcours 2024-2025', $dto->libelle);
            $this->assertEquals(120, $dto->ectsTotal);
        } catch (\Exception $e) {
            $this->fail('StructureParcours should be created with valid data: ' . $e->getMessage());
        }
    }

    /**
     * ❌ Scénario : Échoue avec données incomplètes
     */
    public function testFailsWithMissingRequiredFields(): void
    {
        $this->markTestIncomplete('À impléter selon vos validations');

        // $data = ['libelle' => 'Test']; // Manque libelleCourt

        // $this->expectException(\InvalidArgumentException::class);
        // new StructureParcours($data);
    }

    /**
     * ❌ Scénario : Échoue avec ECTS invalides
     */
    public function testFailsWithInvalidEcts(): void
    {
        $this->markTestIncomplete('À impléter');

        // $data = [
        //     'libelle' => 'Test',
        //     'libelleCourt' => 'T',
        //     'ectsTotal' => -10, // Invalide !
        // ];

        // $this->expectException(\InvalidArgumentException::class);
        // new StructureParcours($data);
    }
}
