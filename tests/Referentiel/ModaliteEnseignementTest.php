<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Enums\ModaliteEnseignementEnum;
use App\Referentiel\ModaliteEnseignement;
use PHPUnit\Framework\TestCase;

final class ModaliteEnseignementTest extends TestCase
{
    public function testEveryLegacyKeyRemainsStable(): void
    {
        foreach (ModaliteEnseignementEnum::cases() as $case) {
            self::assertSame((string) $case->value, ModaliteEnseignement::key($case));
            self::assertSame($case, ModaliteEnseignement::legacyCase($case->value));
            self::assertSame($case, ModaliteEnseignement::legacyCase((string) $case->value));
        }
    }

    public function testCustomKeysAreNotCoercedToLegacyCases(): void
    {
        self::assertSame('hybride_flexible', ModaliteEnseignement::key('hybride_flexible'));
        self::assertNull(ModaliteEnseignement::legacyCase('hybride_flexible'));
        self::assertNull(ModaliteEnseignement::legacyCase('2extra'));
        self::assertNull(ModaliteEnseignement::legacyCase(null));
    }
}
