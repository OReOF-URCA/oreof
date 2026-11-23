<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DTO\StatsFichesMatieres;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Repository\FicheMatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class FicheMatiereRepositoryStatsTest extends TestCase
{
    public function testGetStatsForFormationsReturnsEmptyWhenNoFormations(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repo = new FicheMatiereRepository($registry);

        $campagne = new CampagneCollecte();
        $stats = $repo->getStatsForFormations([], $campagne);

        $this->assertSame([], $stats);
    }

    public function testGetStatsForFormationsReturnsDefaultZeroesWhenCampagneIsNull(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repo = new FicheMatiereRepository($registry);

        $f1 = new Formation();
        $ref = new \ReflectionProperty(Formation::class, 'id');
        $ref->setValue($f1, 42);

        $stats = $repo->getStatsForFormations([$f1], null);

        $this->assertArrayHasKey(42, $stats);
        $this->assertInstanceOf(StatsFichesMatieres::class, $stats[42]['stats']);
        $this->assertSame(0, $stats[42]['stats']->nbFiches);
        $this->assertSame(0, $stats[42]['stats']->nbFichesCompletes);
    }

    public function testGetStatsForFormationsCalculatesCountsCorrectly(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $meta = new ClassMetadata(FicheMatiere::class);
        $em->method('getClassMetadata')->with(FicheMatiere::class)->willReturn($meta);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(FicheMatiere::class)->willReturn($em);

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $em->method('createQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $rows = [
            [
                'formation_id' => 10,
                'id' => 101,
                'remplissage' => ['score' => 5, 'total' => 5],
                'etatFiche' => ['publie' => 1],
            ],
            [
                'formation_id' => 10,
                'id' => 102,
                'remplissage' => ['score' => 3, 'total' => 5],
                'etatFiche' => ['en_cours_redaction' => 1],
            ],
            [
                'formation_id' => 10,
                'id' => 103,
                'remplissage' => ['score' => 5, 'total' => 5],
                'etatFiche' => ['soumis_central' => 1],
            ],
            [
                'formation_id' => 10,
                'id' => 104,
                'remplissage' => ['score' => 5, 'total' => 5],
                'etatFiche' => ['valide_pour_publication' => 1],
            ],
            [
                'formation_id' => 10,
                'id' => 105,
                'remplissage' => null,
                'etatFiche' => [], // non validée
            ],
            [
                'formation_id' => 20,
                'id' => 201,
                'remplissage' => ['score' => 10, 'total' => 10],
                'etatFiche' => ['fiche_matiere' => 1],
            ],
        ];

        $query->method('getArrayResult')->willReturn($rows);

        $repo = new FicheMatiereRepository($registry);

        $f1 = new Formation();
        $ref = new \ReflectionProperty(Formation::class, 'id');
        $ref->setValue($f1, 10);

        $f2 = new Formation();
        $ref->setValue($f2, 20);

        $f3 = new Formation();
        $ref->setValue($f3, 30); // Pas de fiche

        $campagne = new CampagneCollecte();

        $stats = $repo->getStatsForFormations([$f1, $f2, $f3], $campagne);

        $this->assertCount(3, $stats);

        // Formation 10
        $st10 = $stats[10]['stats'];
        $this->assertSame(5, $st10->nbFiches);
        $this->assertSame(3, $st10->nbFichesCompletes);
        $this->assertSame(1, $st10->nbPubliees ?? $st10->nbFichesPubliees);
        $this->assertSame(1, $st10->nbEnCoursRedaction);
        $this->assertSame(1, $st10->nbFichesNonValideesSes);
        $this->assertSame(1, $st10->nbFichesValidees);
        $this->assertSame(1, $st10->nbFichesNonValidees);

        // Formation 20
        $st20 = $stats[20]['stats'];
        $this->assertSame(1, $st20->nbFiches);
        $this->assertSame(1, $st20->nbFichesCompletes);
        $this->assertSame(1, $st20->nbFichesNonValidees);
        $this->assertSame(0, $st20->nbFichesPubliees);

        // Formation 30 (aucune fiche)
        $st30 = $stats[30]['stats'];
        $this->assertSame(0, $st30->nbFiches);
        $this->assertSame(0, $st30->nbFichesCompletes);
    }
}
