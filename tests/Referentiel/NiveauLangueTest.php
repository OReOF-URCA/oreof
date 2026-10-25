<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Entity\Parcours;
use App\Enums\NiveauLangueEnum;
use App\Referentiel\NiveauLangue;
use PHPUnit\Framework\TestCase;

final class NiveauLangueTest extends TestCase
{
    public function testLegacyKeysAreUnchanged(): void
    {
        self::assertSame('A1', NiveauLangue::key(NiveauLangueEnum::A1));
        self::assertSame('A2', NiveauLangue::key(NiveauLangueEnum::A2));
        self::assertSame('B1', NiveauLangue::key(NiveauLangueEnum::B1));
        self::assertSame('B2', NiveauLangue::key(NiveauLangueEnum::B2));
        self::assertSame('C1', NiveauLangue::key(NiveauLangueEnum::C1));
        self::assertSame('C2', NiveauLangue::key(NiveauLangueEnum::C2));

        self::assertSame(NiveauLangueEnum::A1, NiveauLangue::legacyCase('A1'));
        self::assertSame(NiveauLangueEnum::B2, NiveauLangue::legacyCase('B2'));
        self::assertSame(NiveauLangueEnum::C2, NiveauLangue::legacyCase('C2'));
    }

    public function testCustomKeysAreNotCoercedToLegacyCases(): void
    {
        self::assertNull(NiveauLangue::legacyCase('B2+'));
        self::assertNull(NiveauLangue::legacyCase('custom_lang'));
        self::assertNull(NiveauLangue::legacyCase(''));
        self::assertNull(NiveauLangue::legacyCase(null));
    }

    public function testParcoursLegacyAndCustomNiveauLangueHandling(): void
    {
        $parcours = new Parcours();

        // 1. Initial state defaults to B2
        self::assertSame(NiveauLangueEnum::B2, $parcours->getNiveauFrancais());
        self::assertSame('B2', $parcours->getNiveauFrancaisValue());

        // 2. Setting legacy enum synchronizes both properties
        $parcours->setNiveauFrancais(NiveauLangueEnum::C1);
        self::assertSame(NiveauLangueEnum::C1, $parcours->getNiveauFrancais());
        self::assertSame('C1', $parcours->getNiveauFrancaisValue());

        // 3. Setting canonical key for historical value synchronizes legacy enum
        $parcours->setNiveauFrancaisValue('B1');
        self::assertSame(NiveauLangueEnum::B1, $parcours->getNiveauFrancais());
        self::assertSame('B1', $parcours->getNiveauFrancaisValue());

        // 4. Setting a custom key sets referential value and legacy getter returns null (no crash)
        $parcours->setNiveauFrancaisValue('C1+');
        self::assertSame('C1+', $parcours->getNiveauFrancaisValue());
        self::assertNull($parcours->getNiveauFrancais());

        // 5. Calling legacy setter with null does NOT wipe a custom referential value
        $parcours->setNiveauFrancais(null);
        self::assertSame('C1+', $parcours->getNiveauFrancaisValue());

        // 6. Calling canonical setter with null properly resets
        $parcours->setNiveauFrancaisValue(null);
        self::assertSame('B2', $parcours->getNiveauFrancaisValue()); // Fallback default
    }
}
