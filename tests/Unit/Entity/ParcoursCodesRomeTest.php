<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Parcours;
use PHPUnit\Framework\TestCase;

class ParcoursCodesRomeTest extends TestCase
{
    public function testGetCodesRomeNormalizedDefaultEmpty(): void
    {
        $parcours = new Parcours();
        $this->assertSame([], $parcours->getCodesRomeNormalized());
    }

    public function testGetCodesRomeNormalizedWithExplicitEmptyArray(): void
    {
        $parcours = new Parcours();
        $parcours->setCodesRome([]);
        $this->assertSame([], $parcours->getCodesRomeNormalized());
    }

    public function testGetCodesRomeNormalizedWithNull(): void
    {
        $parcours = new Parcours();
        $parcours->setCodesRome(null);
        $this->assertSame([], $parcours->getCodesRomeNormalized());
    }

    public function testGetCodesRomeNormalizedExtractsCodesAndNormalizes(): void
    {
        $parcours = new Parcours();
        $parcours->setCodesRome([
            ['code' => 'M1808 - Information géographique'],
            ['code' => 'K1802 - Développement local'],
            ['code' => 'k1903'], // lowercase
            ['code' => ' K1206 '], // whitespace
            ['code' => 'M1808'], // duplicate
            ['code' => ''], // empty
            ['code' => 'INVALID'], // invalid
        ]);

        $this->assertSame(
            ['M1808', 'K1802', 'K1903', 'K1206'],
            $parcours->getCodesRomeNormalized()
        );
    }

    public function testGetCodesRomeNormalizedWithAssociativeOrScalarArray(): void
    {
        $parcours = new Parcours();
        $parcours->setCodesRome([
            '1' => ['code' => 'K1201'],
            '2' => ['code' => 'K1206'],
            '3' => 'M1805',
        ]);

        $this->assertSame(
            ['K1201', 'K1206', 'M1805'],
            $parcours->getCodesRomeNormalized()
        );
    }
}
