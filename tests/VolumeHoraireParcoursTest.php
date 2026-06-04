<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/tests/VolumeHoraireParcoursTest.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/04/2026 18:44
 */

namespace App\Tests;

use App\Entity\VolumeHoraireParcours;
use PHPUnit\Framework\TestCase;

class VolumeHoraireParcoursTest extends TestCase
{
    public function testGetHeuresTotalWithoutTe(): void
    {
        $volume = (new VolumeHoraireParcours())
            ->setHeuresCmPres(100)
            ->setHeuresTdPres(50)
            ->setHeuresTpPres(25)
            ->setHeuresTePres(40)
            ->setHeuresCmDist(10)
            ->setHeuresTdDist(5)
            ->setHeuresTpDist(2);

        self::assertSame(175.0, $volume->getHeuresTotalPres());
        self::assertSame(17.0, $volume->getHeuresTotalDist());
        self::assertSame(192.0, $volume->getHeuresTotal());
    }

    public function testHeuresByAnneeAndSemestreFromStoredArrays(): void
    {
        $volume = (new VolumeHoraireParcours())
            ->setVolumesAnnee([
                1 => ['total' => 240.5],
                2 => ['total' => 180],
            ])
            ->setVolumesSemestre([
                1 => ['total' => 120.25],
                2 => ['total' => 120.25],
                3 => ['total' => 180],
            ]);

        self::assertSame(240.5, $volume->getHeuresAnnee(1));
        self::assertSame(180.0, $volume->getHeuresAnnee(2));
        self::assertSame(120.25, $volume->getHeuresSemestre(1));
        self::assertSame(0.0, $volume->getHeuresSemestre(99));
    }
}

