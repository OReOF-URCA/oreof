<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Repository\AnneeRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\Offre\OffreStatistiquesService;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class OffreStatistiquesServiceTest extends TestCase
{
    private OffreStatistiquesService $service;

    protected function setUp(): void
    {
        $formationRepo = $this->createStub(FormationRepository::class);
        $dpeParcoursRepo = $this->createStub(DpeParcoursRepository::class);
        $anneeRepo = $this->createStub(AnneeRepository::class);
        $plateformeRepo = $this->createStub(PlateformeAdmissionRepository::class);
        $plateformeParamRepo = $this->createStub(PlateformeAdmissionParametreRepository::class);
        $composanteRepo = $this->createStub(ComposanteRepository::class);
        $typeDiplomeRepo = $this->createStub(TypeDiplomeRepository::class);
        $chartBuilder = $this->createStub(ChartBuilderInterface::class);

        $chart = new Chart(Chart::TYPE_BAR);
        $chartBuilder->method('createChart')->willReturn($chart);

        $this->service = new OffreStatistiquesService(
            $formationRepo,
            $dpeParcoursRepo,
            $anneeRepo,
            $plateformeRepo,
            $plateformeParamRepo,
            $composanteRepo,
            $typeDiplomeRepo,
            $chartBuilder
        );
    }

    public function testResolveNiveauInfoForLicence(): void
    {
        $td = new TypeDiplome();
        $td->setLibelle('Licence Informatique');
        $td->setLibelleCourt('LICENCE');

        $l1 = $this->service->resolveNiveauInfo($td, 1);
        self::assertSame('L1', $l1['code']);
        self::assertSame('Licence 1ère année', $l1['libelle']);
        self::assertSame('Licence', $l1['cycle']);

        $l2 = $this->service->resolveNiveauInfo($td, 2);
        self::assertSame('L2', $l2['code']);
        self::assertSame('Licence 2ème année', $l2['libelle']);

        $l3 = $this->service->resolveNiveauInfo($td, 3);
        self::assertSame('L3', $l3['code']);
    }

    public function testResolveNiveauInfoForBut(): void
    {
        $td = new TypeDiplome();
        $td->setLibelle('Bachelor Universitaire de Technologie');
        $td->setLibelleCourt('BUT');

        $but1 = $this->service->resolveNiveauInfo($td, 1);
        self::assertSame('BUT 1', $but1['code']);
        self::assertSame('Bachelor Univ. Tech. 1ère année', $but1['libelle']);
        self::assertSame('BUT', $but1['cycle']);

        $but2 = $this->service->resolveNiveauInfo($td, 2);
        self::assertSame('BUT 2', $but2['code']);

        $but3 = $this->service->resolveNiveauInfo($td, 3);
        self::assertSame('BUT 3', $but3['code']);
    }

    public function testResolveNiveauInfoForMaster(): void
    {
        $td = new TypeDiplome();
        $td->setLibelle('Master');
        $td->setLibelleCourt('MASTER');

        $m1 = $this->service->resolveNiveauInfo($td, 1);
        self::assertSame('M1', $m1['code']);
        self::assertSame('Master 1ère année', $m1['libelle']);
        self::assertSame('Master', $m1['cycle']);

        $m2 = $this->service->resolveNiveauInfo($td, 2);
        self::assertSame('M2', $m2['code']);
        self::assertSame('Master 2ème année', $m2['libelle']);
    }

    public function testResolveNiveauInfoForLicencePro(): void
    {
        $td = new TypeDiplome();
        $td->setLibelle('Licence Professionnelle');
        $td->setLibelleCourt('LP');

        $lp = $this->service->resolveNiveauInfo($td, 3);
        self::assertSame('LP', $lp['code']);
        self::assertSame('Licence Professionnelle', $lp['libelle']);
        self::assertSame('Licence Pro', $lp['cycle']);
    }

    public function testGetStatsWithTroncCommunDeduplication(): void
    {
        $campagne = new CampagneCollecte();
        $this->setId($campagne, 1);

        $composante = new Composante();
        $this->setId($composante, 10);
        $composante->setLibelle('UFR Sciences');

        $typeDiplome = new TypeDiplome();
        $this->setId($typeDiplome, 20);
        $typeDiplome->setLibelle('Licence');
        $typeDiplome->setLibelleCourt('LICENCE');

        $formation = new Formation($campagne);
        $this->setId($formation, 100);
        $formation->setComposantePorteuse($composante);
        $formation->setTypeDiplome($typeDiplome);
        $formation->setStructureSemestres(['annees_tronc_commun' => [1]]);

        $parcours1 = new Parcours($formation);
        $this->setId($parcours1, 101);
        $parcours1->setLibelle('Parcours Mathématiques');

        $parcours2 = new Parcours($formation);
        $this->setId($parcours2, 102);
        $parcours2->setLibelle('Parcours Informatique');

        $formation->addParcour($parcours1);
        $formation->addParcour($parcours2);

        $dpe1 = new \App\Entity\DpeParcours();
        $this->setId($dpe1, 201);
        $dpe1->setParcours($parcours1);

        $dpe2 = new \App\Entity\DpeParcours();
        $this->setId($dpe2, 202);
        $dpe2->setParcours($parcours2);

        // Parcours 1: Annee 1 (capacité 40), Annee 2 (capacité 25)
        $a1_1 = new \App\Entity\Annee();
        $this->setId($a1_1, 301);
        $a1_1->setOrdre(1);
        $a1_1->setIsOuvert(true);
        $a1_1->setCapaciteAccueil(40);
        $a1_1->setParcours($parcours1);

        $a1_2 = new \App\Entity\Annee();
        $this->setId($a1_2, 302);
        $a1_2->setOrdre(2);
        $a1_2->setIsOuvert(true);
        $a1_2->setCapaciteAccueil(25);
        $a1_2->setParcours($parcours1);

        // Parcours 2: Annee 1 (capacité 40 - Tronc commun avec parcours 1), Annee 2 (capacité 20)
        $a2_1 = new \App\Entity\Annee();
        $this->setId($a2_1, 303);
        $a2_1->setOrdre(1);
        $a2_1->setIsOuvert(true);
        $a2_1->setCapaciteAccueil(40);
        $a2_1->setParcours($parcours2);

        $a2_2 = new \App\Entity\Annee();
        $this->setId($a2_2, 304);
        $a2_2->setOrdre(2);
        $a2_2->setIsOuvert(true);
        $a2_2->setCapaciteAccueil(20);
        $a2_2->setParcours($parcours2);

        $formationRepo = $this->createStub(FormationRepository::class);
        $formationRepo->method('findByCampagneWithRelations')->willReturn([$formation]);

        $dpeParcoursRepo = $this->createStub(DpeParcoursRepository::class);
        $dpeParcoursRepo->method('findByCampagneCollecte')->willReturn([$dpe1, $dpe2]);

        $anneeRepo = $this->createStub(AnneeRepository::class);
        $anneeRepo->method('findByCampagneIndexedByParcours')->willReturn([
            101 => [$a1_1, $a1_2],
            102 => [$a2_1, $a2_2],
        ]);

        $plateformeRepo = $this->createStub(PlateformeAdmissionRepository::class);
        $plateformeRepo->method('findBy')->willReturn([]);

        $plateformeParamRepo = $this->createStub(PlateformeAdmissionParametreRepository::class);
        $plateformeParamRepo->method('findByCampagneIndexedByAnnee')->willReturn([]);

        $composanteRepo = $this->createStub(ComposanteRepository::class);
        $typeDiplomeRepo = $this->createStub(TypeDiplomeRepository::class);
        $chartBuilder = $this->createStub(ChartBuilderInterface::class);
        $chartBuilder->method('createChart')->willReturn(new Chart(Chart::TYPE_BAR));

        $service = new OffreStatistiquesService(
            $formationRepo,
            $dpeParcoursRepo,
            $anneeRepo,
            $plateformeRepo,
            $plateformeParamRepo,
            $composanteRepo,
            $typeDiplomeRepo,
            $chartBuilder
        );

        $stats = $service->getStats($campagne);

        // Vérification de la capacité globale : 40 (L1 dédoublonné) + 25 (L2 P1) + 20 (L2 P2) = 85
        // (Sans dédoublonnage, ce serait 40 + 40 + 25 + 20 = 125)
        self::assertSame(85, $stats['kpi']['totalCapacite']);
        self::assertSame(40, $stats['kpi']['totalNiveau1']);
        self::assertSame(1, $stats['kpi']['totalFormations']);
        self::assertSame(2, $stats['kpi']['totalParcours']);

        // Vérification du niveau L1 et L2 dans les stats par niveau
        $parNiveau = $stats['parNiveau'];
        self::assertArrayHasKey('L1', $parNiveau);
        self::assertArrayHasKey('L2', $parNiveau);
        self::assertSame(40, $parNiveau['L1']['capaciteTotale']);
        self::assertSame(45, $parNiveau['L2']['capaciteTotale']);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
