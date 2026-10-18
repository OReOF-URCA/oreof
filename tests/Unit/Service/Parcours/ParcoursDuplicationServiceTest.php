<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Parcours;

use App\DTO\ParcoursDuplicationAnalysis;
use App\DTO\ParcoursDuplicationConfig;
use App\DTO\ParcoursDuplicationResult;
use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Entity\User;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\SemestreMutualisableRepository;
use App\Repository\UeMutualisableRepository;
use App\Service\Parcours\ParcoursDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ParcoursDuplicationServiceTest extends TestCase
{
    public function testAnalyzeComputesCountsCorrectly(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $fmRepo = $this->createMock(FicheMatiereRepository::class);
        $smRepo = $this->createMock(SemestreMutualisableRepository::class);
        $ueMutuRepo = $this->createMock(UeMutualisableRepository::class);
        $fmMutuRepo = $this->createMock(FicheMatiereMutualisableRepository::class);

        $smRepo->method('findBy')->willReturn([]);
        $ueMutuRepo->method('findBy')->willReturn([]);
        $fmMutuRepo->method('findBy')->willReturn([]);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Initial');

        $semestre = new Semestre();
        $semestre->setOrdre(1);

        $ue = new Ue();
        $ue->setLibelle('UE Informatique');
        $semestre->addUe($ue);

        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre(1);
        $parcours->addSemestreParcour($sp);

        $service = new ParcoursDuplicationService(
            $em,
            new NullLogger(),
            $fmRepo,
            $smRepo,
            $ueMutuRepo,
            $fmMutuRepo
        );

        $analysis = $service->analyze($parcours);

        $this->assertInstanceOf(ParcoursDuplicationAnalysis::class, $analysis);
        $this->assertSame(1, $analysis->getNbSemestres());
        $this->assertSame(1, $analysis->getNbUes());
        $this->assertSame(0, $analysis->getNbEcs());
        $this->assertFalse($analysis->hasWarnings());
        $this->assertNotEmpty($analysis->elementsRecopies);
        $this->assertNotEmpty($analysis->elementsReinitialises);
        $this->assertNotEmpty($analysis->elementsExclus);
    }

    public function testDuplicatePerformsSafeReplication(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $fmRepo = $this->createMock(FicheMatiereRepository::class);
        $smRepo = $this->createMock(SemestreMutualisableRepository::class);
        $ueMutuRepo = $this->createMock(UeMutualisableRepository::class);
        $fmMutuRepo = $this->createMock(FicheMatiereMutualisableRepository::class);

        $em->method('wrapInTransaction')->willReturnCallback(function (callable $func) {
            return $func();
        });

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Master');
        $parcours->setSigle('PM');

        $config = new ParcoursDuplicationConfig();
        $config->nouveauLibelle = 'Parcours Master (copie)';
        $config->nouveauSigle = 'PM-2';
        $config->dupliquerContacts = true;
        $config->dupliquerFichesMatieres = true;

        $user = new User();
        $user->setEmail('resp@univ.fr');

        $campagne = new CampagneCollecte();

        $service = new ParcoursDuplicationService(
            $em,
            new NullLogger(),
            $fmRepo,
            $smRepo,
            $ueMutuRepo,
            $fmMutuRepo
        );

        $result = $service->duplicate($parcours, $config, $user, $campagne);

        $this->assertInstanceOf(ParcoursDuplicationResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertNotNull($result->nouveauParcours);
        $this->assertSame('Parcours Master (copie)', $result->nouveauParcours->getLibelle());
        $this->assertSame('PM-2', $result->nouveauParcours->getSigle());
        $this->assertSame($parcours, $result->nouveauParcours->getParcoursOrigineCopie());
    }
}
