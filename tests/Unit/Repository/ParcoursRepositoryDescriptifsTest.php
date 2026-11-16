<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Repository\ParcoursRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ParcoursRepositoryDescriptifsTest extends TestCase
{
    public function testGetStatsDescriptifsByCampagneWithNullReturnsZeroes(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repo = new ParcoursRepository($registry);

        $stats = $repo->getStatsDescriptifsByCampagne(null);

        $this->assertSame([
            'total' => 0,
            'count_auto' => 0,
            'count_manuel' => 0,
            'count_both' => 0,
        ], $stats);
    }
}
