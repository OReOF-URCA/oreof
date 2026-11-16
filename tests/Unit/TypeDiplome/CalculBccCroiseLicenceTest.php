<?php

declare(strict_types=1);

namespace App\Tests\Unit\TypeDiplome;

use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\BlocCompetence;
use App\Entity\Competence;
use App\Entity\ElementConstitutif;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Repository\BlocCompetenceRepository;
use App\TypeDiplome\Diplomes\Licence\Services\CalculBccCroiseLicence;
use App\TypeDiplome\StructureInterface;
use PHPUnit\Framework\TestCase;

class CalculBccCroiseLicenceTest extends TestCase
{
    public function testCalculBccCroiseLicenceStructuresDataAndComputesStats(): void
    {
        $formation = new Formation(null);
        $formation->setMentionTexte('Licence Informatique');

        $parcours = new Parcours();
        $parcours->setLibelle('Informatique Générale');
        $formation->addParcour($parcours);

        // BCC 1 avec 2 compétences (C1 mobilisée, C2 non mobilisée)
        $bcc = new BlocCompetence();
        $bcc->setCode('BCC 1');
        $bcc->setLibelle('Développement Logiciel');
        $bcc->setOrdre(1);
        $parcours->addBlocCompetence($bcc);

        $comp1 = new Competence($bcc);
        $comp1->setCode('1A');
        $comp1->setLibelle('Programmation orientée objet');
        $comp1->setOrdre(1);
        $bcc->addCompetence($comp1);

        $comp2 = new Competence($bcc);
        $comp2->setCode('1B');
        $comp2->setLibelle('Conception logicielle avancée');
        $comp2->setOrdre(2);
        $bcc->addCompetence($comp2);

        // Semestre 1 avec 1 UE et 1 EC
        $semestre = new Semestre();
        $semestre->setOrdre(1);

        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre(1);
        $sp->setOuvert(true);
        $parcours->addSemestreParcour($sp);

        $ue = new Ue();
        $ue->setOrdre(1);
        $ue->setLibelle('UE Informatique Fondamentale');
        $ue->setSemestre($semestre);

        $ec = new ElementConstitutif();
        $ec->setCode('INF101');
        $ec->setLibelle('Introduction à Java');
        $ec->setUe($ue);
        $ec->addCompetence($comp1); // Mobilise comp1
        $ue->addElementConstitutif($ec);

        // Mock StructureParcoursLicence
        $dtoStructure = StructureParcours::fromEntity($parcours, false, true);
        $dtoSemestre = new StructureSemestre($semestre, 1, false, $sp, false, true);
        $dtoUe = new StructureUe($ue, false, $ue->getLibelle(), null, false, true);
        $dtoEc = new StructureEc($ec, $parcours, false, false, true, false);

        $dtoUe->addEc($dtoEc);
        $dtoSemestre->addUe(1, $dtoUe);
        $dtoStructure->addSemestre(1, $dtoSemestre);

        $structureMock = $this->createMock(StructureInterface::class);
        $structureMock->expects($this->exactly(2))
            ->method('calcul')
            ->willReturn($dtoStructure);

        $blocRepoMock = $this->createMock(BlocCompetenceRepository::class);
        $blocRepoMock->expects($this->exactly(2))
            ->method('findByParcours')
            ->with($parcours)
            ->willReturn([$bcc]);

        $service = new CalculBccCroiseLicence($structureMock, $blocRepoMock);
        $result = $service->calcul($parcours);

        $this->assertArrayHasKey('semestresData', $result);
        $this->assertArrayHasKey(1, $result['semestresData']);
        $this->assertArrayHasKey('stats', $result);

        $sData = $result['semestresData'][1];
        $this->assertSame(1, $sData['ordre']);
        $this->assertCount(1, $sData['ueHeaders']);
        $this->assertSame('UE Informatique Fondamentale', $sData['ueHeaders'][0]['label']);
        $this->assertCount(1, $sData['leafColumns']);
        $this->assertSame('INF101', $sData['leafColumns'][0]['code']);

        $this->assertArrayHasKey('semestresDisponibles', $result);
        $this->assertArrayHasKey(1, $result['semestresDisponibles']);
        $this->assertSame(1, $result['semestresDisponibles'][1]['ordre']);

        $stats = $result['stats'];
        $this->assertSame(1, $stats['totalBcc']);
        $this->assertSame(2, $stats['totalCompetences']);
        $this->assertSame(1, $stats['totalEcs']);
        $this->assertSame(1, $stats['totalMobilisations']);
        $this->assertSame(1, $stats['nbCompetencesCouvertes']);
        $this->assertSame(50, $stats['tauxCouverture']);
        $this->assertCount(1, $stats['competencesNonCouvertes']);
        $this->assertSame($comp2, $stats['competencesNonCouvertes'][0]);

        // Test filtrage par semestre inexistant
        $resultS2 = $service->calcul($parcours, 2);
        $this->assertArrayNotHasKey(2, $resultS2['semestresData']);
        $this->assertArrayHasKey(1, $resultS2['semestresDisponibles']);
    }
}
