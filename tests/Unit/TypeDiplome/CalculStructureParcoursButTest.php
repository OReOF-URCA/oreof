<?php

declare(strict_types=1);

namespace App\Tests\Unit\TypeDiplome;

use App\Entity\ElementConstitutif;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\TypeDiplome;
use App\Entity\Ue;
use App\Repository\ButCompetenceRepository;
use App\TypeDiplome\Diplomes\But\Services\ButMccc;
use App\TypeDiplome\Diplomes\But\Services\ButMcccVersion;
use App\TypeDiplome\Diplomes\But\Services\CalculStructureParcoursBut;
use App\TypeDiplome\Diplomes\But\StructureParcoursBut;
use App\TypeDiplome\Diplomes\But\ValideParcoursBut;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\Handler\ButHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CalculStructureParcoursButTest extends TestCase
{
    private function buildParcoursWithStructure(): array
    {
        $typeDiplome = new TypeDiplome();
        $typeDiplome->setLibelleCourt('BUT');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeDiplome);

        $parcours = new Parcours();
        $parcours->setLibelle('Parcours Déploiement');
        $formation->addParcour($parcours);

        $semestre = new Semestre();
        $semestre->setOrdre(1);

        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre(1);
        $sp->setOuvert(true);
        $parcours->addSemestreParcour($sp);
        $semestre->addSemestreParcour($sp);

        $ue = new Ue();
        $ue->setOrdre(1);
        $ue->setLibelle('Réaliser un développement d\'application');
        $ue->setEcts(8.0);
        $ue->setSemestre($semestre);
        $semestre->addUe($ue);

        $ec = new ElementConstitutif();
        $ec->setCode('R1.01');
        $ec->setLibelle('Initiation au développement');
        $ec->setUe($ue);
        $ue->addElementConstitutif($ec);

        return [$parcours, $sp, $semestre, $ue, $ec];
    }

    public function testCalculSemestrePopulatesUesAndEcs(): void
    {
        [$parcours, $sp, $semestre, $ue, $ec] = $this->buildParcoursWithStructure();

        $calculator = new CalculStructureParcoursBut();
        $options = new OptionsCalculStructure();

        $dtoSemestre = $calculator->calculSemestre($sp, $options, $parcours);

        $this->assertSame(1, $dtoSemestre->ordre);
        $this->assertCount(1, $dtoSemestre->ues);

        $dtoUe = $dtoSemestre->ues[1] ?? null;
        $this->assertNotNull($dtoUe);
        $this->assertSame($ue, $dtoUe->ue);
        $this->assertSame(8.0, $dtoUe->heuresEctsUe->sommeUeEcts);
        $this->assertCount(1, $dtoUe->elementConstitutifs);
        $this->assertSame($ec, $dtoUe->elementConstitutifs[0]->elementConstitutif);
    }

    public function testStructureParcoursButDelegatesCalculStructureSemestre(): void
    {
        [$parcours, $sp] = $this->buildParcoursWithStructure();

        $calculator = new CalculStructureParcoursBut();
        $structureParcoursBut = new StructureParcoursBut($calculator);

        $dtoSemestre = $structureParcoursBut->calculStructureSemestre($sp, $parcours);

        $this->assertCount(1, $dtoSemestre->ues);
    }

    public function testButHandlerDelegatesCalculStructureSemestre(): void
    {
        [$parcours, $sp] = $this->buildParcoursWithStructure();

        $calculator = new CalculStructureParcoursBut();
        $structureParcoursBut = new StructureParcoursBut($calculator);
        $em = $this->createStub(EntityManagerInterface::class);
        $butMccc = $this->createStub(ButMccc::class);
        $butMcccVersion = $this->createStub(ButMcccVersion::class);
        $competenceRepo = $this->createStub(ButCompetenceRepository::class);
        $valideParcours = $this->createStub(ValideParcoursBut::class);

        $handler = new ButHandler($em, $butMccc, $butMcccVersion, $competenceRepo, $structureParcoursBut, $valideParcours);

        $dtoSemestre = $handler->calculStructureSemestre($sp, $parcours);

        $this->assertCount(1, $dtoSemestre->ues);
    }
}
