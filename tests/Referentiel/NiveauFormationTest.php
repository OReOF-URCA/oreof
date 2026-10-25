<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Entity\Formation;
use App\Enums\NiveauFormationEnum;
use App\Referentiel\NiveauFormation;
use PHPUnit\Framework\TestCase;

final class NiveauFormationTest extends TestCase
{
    public function testLegacyKeysAreUnchanged(): void
    {
        self::assertSame('0', NiveauFormation::key(NiveauFormationEnum::NON_COMMUNIQUE));
        self::assertSame('6', NiveauFormation::key(NiveauFormationEnum::NIVEAU_III));
        self::assertSame('16', NiveauFormation::key(NiveauFormationEnum::NIVEAU_6));
        self::assertSame('17', NiveauFormation::key(NiveauFormationEnum::NIVEAU_7));

        self::assertSame(NiveauFormationEnum::NON_COMMUNIQUE, NiveauFormation::legacyCase('0'));
        self::assertSame(NiveauFormationEnum::NIVEAU_6, NiveauFormation::legacyCase('16'));
        self::assertSame(NiveauFormationEnum::NIVEAU_7, NiveauFormation::legacyCase('17'));
    }

    public function testCustomKeysAreNotCoercedToLegacyCases(): void
    {
        self::assertNull(NiveauFormation::legacyCase('niveau_custom'));
        self::assertNull(NiveauFormation::legacyCase('99'));
        self::assertNull(NiveauFormation::legacyCase(''));
        self::assertNull(NiveauFormation::legacyCase(null));
    }

    public function testFormationLegacyAndCustomNiveauHandling(): void
    {
        $formation = new Formation();

        // 1. Initial state is null
        self::assertNull($formation->getNiveauEntree());
        self::assertNull($formation->getNiveauEntreeValue());
        self::assertNull($formation->getNiveauSortie());
        self::assertNull($formation->getNiveauSortieValue());

        // 2. Setting legacy enum synchronizes both properties
        $formation->setNiveauEntree(NiveauFormationEnum::NIVEAU_6);
        $formation->setNiveauSortie(NiveauFormationEnum::NIVEAU_7);
        self::assertSame(NiveauFormationEnum::NIVEAU_6, $formation->getNiveauEntree());
        self::assertSame('16', $formation->getNiveauEntreeValue());
        self::assertSame(NiveauFormationEnum::NIVEAU_7, $formation->getNiveauSortie());
        self::assertSame('17', $formation->getNiveauSortieValue());

        // 3. Setting canonical key for historical value synchronizes legacy enum
        $formation->setNiveauEntreeValue('15');
        self::assertSame(NiveauFormationEnum::NIVEAU_5, $formation->getNiveauEntree());
        self::assertSame('15', $formation->getNiveauEntreeValue());

        // 4. Setting a custom key sets referential value and legacy getter returns null (no crash)
        $formation->setNiveauEntreeValue('diplome_etablissement');
        self::assertSame('diplome_etablissement', $formation->getNiveauEntreeValue());
        self::assertNull($formation->getNiveauEntree());

        // 5. Calling legacy setter with null does NOT wipe a custom referential value
        $formation->setNiveauEntree(null);
        self::assertSame('diplome_etablissement', $formation->getNiveauEntreeValue());

        // 6. Calling canonical setter with null properly resets both
        $formation->setNiveauEntreeValue(null);
        self::assertNull($formation->getNiveauEntreeValue());
        self::assertNull($formation->getNiveauEntree());
    }
}
