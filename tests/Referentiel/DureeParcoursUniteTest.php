<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Entity\Parcours;
use App\Enums\DureeParcoursUniteEnum;
use App\Referentiel\DureeParcoursUnite;
use PHPUnit\Framework\TestCase;

final class DureeParcoursUniteTest extends TestCase
{
    public function testLegacyKeysAreUnchanged(): void
    {
        self::assertSame('heures', DureeParcoursUnite::key(DureeParcoursUniteEnum::HEURES));
        self::assertSame('jours', DureeParcoursUnite::key(DureeParcoursUniteEnum::JOURS));
        self::assertSame('semaines', DureeParcoursUnite::key(DureeParcoursUniteEnum::SEMAINES));
        self::assertSame('mois', DureeParcoursUnite::key(DureeParcoursUniteEnum::MOIS));
        self::assertSame('semestres', DureeParcoursUnite::key(DureeParcoursUniteEnum::SEMESTRES));
        self::assertSame('annees', DureeParcoursUnite::key(DureeParcoursUniteEnum::ANNEES));

        self::assertSame(DureeParcoursUniteEnum::HEURES, DureeParcoursUnite::legacyCase('heures'));
        self::assertSame(DureeParcoursUniteEnum::JOURS, DureeParcoursUnite::legacyCase('jours'));
        self::assertSame(DureeParcoursUniteEnum::SEMAINES, DureeParcoursUnite::legacyCase('semaines'));
        self::assertSame(DureeParcoursUniteEnum::MOIS, DureeParcoursUnite::legacyCase('mois'));
        self::assertSame(DureeParcoursUniteEnum::SEMESTRES, DureeParcoursUnite::legacyCase('semestres'));
        self::assertSame(DureeParcoursUniteEnum::ANNEES, DureeParcoursUnite::legacyCase('annees'));
    }

    public function testCustomKeysAreNotCoercedToLegacyCases(): void
    {
        self::assertNull(DureeParcoursUnite::legacyCase('trimestres'));
        self::assertNull(DureeParcoursUnite::legacyCase('modules'));
        self::assertNull(DureeParcoursUnite::legacyCase(''));
        self::assertNull(DureeParcoursUnite::legacyCase(null));
    }

    public function testParcoursLegacyAndCustomDureeUniteHandling(): void
    {
        $parcours = new Parcours();

        // 1. Setting legacy enum synchronizes both properties
        $parcours->setDureeParcoursUnite(DureeParcoursUniteEnum::ANNEES);
        self::assertSame(DureeParcoursUniteEnum::ANNEES, $parcours->getDureeParcoursUnite());
        self::assertSame('annees', $parcours->getDureeParcoursUniteValue());

        // 2. Setting canonical key for historical value synchronizes legacy enum
        $parcours->setDureeParcoursUniteValue('mois');
        self::assertSame(DureeParcoursUniteEnum::MOIS, $parcours->getDureeParcoursUnite());
        self::assertSame('mois', $parcours->getDureeParcoursUniteValue());

        // 3. Setting a custom key sets referential value and legacy getter returns null (no crash)
        $parcours->setDureeParcoursUniteValue('trimestres');
        self::assertSame('trimestres', $parcours->getDureeParcoursUniteValue());
        self::assertNull($parcours->getDureeParcoursUnite());

        // 4. Calling legacy setter with null does NOT wipe a custom referential value
        $parcours->setDureeParcoursUnite(null);
        self::assertSame('trimestres', $parcours->getDureeParcoursUniteValue());

        // 5. Calling canonical setter with null properly resets both
        $parcours->setDureeParcoursUniteValue(null);
        self::assertNull($parcours->getDureeParcoursUniteValue());
        self::assertNull($parcours->getDureeParcoursUnite());
    }
}
