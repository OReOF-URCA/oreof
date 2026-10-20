<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Enums\RegimeInscriptionEnum;
use App\Referentiel\RegimeInscription;
use PHPUnit\Framework\TestCase;

final class RegimeInscriptionTest extends TestCase
{
    public function testLegacyKeysAreUnchanged(): void
    {
        foreach (RegimeInscriptionEnum::cases() as $case) {
            self::assertSame($case->value, RegimeInscription::key($case));
            self::assertSame([$case->value], RegimeInscription::normalize([$case]));
        }
    }

    public function testCustomKeysArePreservedAndDuplicatesRemoved(): void
    {
        self::assertSame(
            [RegimeInscriptionEnum::FI->value, 'nouveau_regime'],
            RegimeInscription::normalize([
                RegimeInscriptionEnum::FI,
                'nouveau_regime',
                RegimeInscriptionEnum::FI->value,
            ]),
        );
    }

    public function testLegacyCasesIgnoreCustomKeys(): void
    {
        self::assertSame(
            [RegimeInscriptionEnum::FC],
            RegimeInscription::legacyCases(['nouveau_regime', RegimeInscriptionEnum::FC->value]),
        );
    }
}
