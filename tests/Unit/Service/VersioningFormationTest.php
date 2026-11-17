<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Formation;
use App\Entity\FormationVersioning;
use App\Repository\FormationVersioningRepository;
use App\Service\VersioningFormation;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class VersioningFormationTest extends TestCase
{
    public function testGetDifferencesWithoutLastVersionReturnsEmptyArray(): void
    {
        $repository = $this->createMock(FormationVersioningRepository::class);
        $repository->method('findLastVersion')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->with(FormationVersioning::class)
            ->willReturn($repository);

        $filesystem = $this->createMock(Filesystem::class);

        $service = new VersioningFormation($entityManager, $filesystem);

        $formation = new Formation();

        // Must return empty array without throwing "Typed property must not be accessed before initialization"
        $diffs = $service->getDifferencesBetweenFormationAndLastVersion($formation);
        $this->assertSame([], $diffs);

        $diffsCampagne = $service->getDifferencesBetweenFormationAndLastVersion($formation, true);
        $this->assertSame([], $diffsCampagne);
    }
}
