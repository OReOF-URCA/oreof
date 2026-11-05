<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Parcours;

use App\DTO\ParcoursMoveAnalysis;
use App\Entity\BlocCompetence;
use App\Entity\CampagneCollecte;
use App\Entity\ChangeParcours;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Service\Parcours\ParcoursMoveService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

final class ParcoursMoveServiceTest extends TestCase
{
    public function testAnalyzeParcours(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('count')->willReturn(3);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle(Parcours::PARCOURS_DEFAUT);
        $formation->getParcours()->add($parcours);

        $dpeParcours = new DpeParcours();
        $parcours->addDpeParcour($dpeParcours);

        $bloc = new BlocCompetence();
        $parcours->addBlocCompetence($bloc);

        $service = new ParcoursMoveService($em, new NullLogger(), $security);
        $analysis = $service->analyze($parcours);

        $this->assertInstanceOf(ParcoursMoveAnalysis::class, $analysis);
        $this->assertTrue($analysis->isParcoursDefaut);
        $this->assertTrue($analysis->isSeulParcoursDeLaFormation);
        $this->assertSame(1, $analysis->nbDpeParcours);
        $this->assertSame(1, $analysis->nbBlocCompetences);
        $this->assertSame(3, $analysis->nbDpeDemandes);
        $this->assertSame(3, $analysis->nbChangeParcours);
    }

    public function testMoveRejectsSameFormation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);

        $formation = new Formation(null);
        // Set an ID via reflection
        $reflection = new \ReflectionClass(Formation::class);
        $prop = $reflection->getProperty('id');
        $prop->setValue($formation, 42);

        $parcours = new Parcours($formation);

        $service = new ParcoursMoveService($em, new NullLogger(), $security);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Le parcours est déjà rattaché à cette formation.');

        $service->move($parcours, $formation);
    }

    public function testMoveRejectsDifferentCampagne(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);

        $campagne1 = new CampagneCollecte();
        $campagne2 = new CampagneCollecte();
        $cRef = new \ReflectionClass(CampagneCollecte::class);
        $cProp = $cRef->getProperty('id');
        $cProp->setValue($campagne1, 1);
        $cProp->setValue($campagne2, 2);

        $fRef = new \ReflectionClass(Formation::class);
        $fProp = $fRef->getProperty('id');

        $f1 = new Formation(null);
        $fProp->setValue($f1, 10);
        $f1->setDpe($campagne1);

        $f2 = new Formation(null);
        $fProp->setValue($f2, 20);
        $f2->setDpe($campagne2);

        $parcours = new Parcours($f1);

        $service = new ParcoursMoveService($em, new NullLogger(), $security);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('La formation de destination doit appartenir à la même campagne de collecte.');

        $service->move($parcours, $f2);
    }

    public function testMoveSuccessfullyUpdatesAllEntities(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $demandeRepo = $this->createMock(EntityRepository::class);
        $changeRepo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturnCallback(function (string $entityClass) use ($demandeRepo, $changeRepo) {
            return match ($entityClass) {
                DpeDemande::class => $demandeRepo,
                ChangeParcours::class => $changeRepo,
                default => $this->createMock(EntityRepository::class),
            };
        });

        $campagne = new CampagneCollecte();
        $cRef = new \ReflectionClass(CampagneCollecte::class);
        $cProp = $cRef->getProperty('id');
        $cProp->setValue($campagne, 1);

        $fRef = new \ReflectionClass(Formation::class);
        $fProp = $fRef->getProperty('id');

        $f1 = new Formation(null);
        $fProp->setValue($f1, 10);
        $f1->setDpe($campagne);
        $f1->setHasParcours(true);

        $f2 = new Formation(null);
        $fProp->setValue($f2, 20);
        $f2->setDpe($campagne);
        $f2->setHasParcours(false);

        $parcours = new Parcours($f1);
        $parcours->setLibelle(Parcours::PARCOURS_DEFAUT);
        $f1->getParcours()->add($parcours);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setFormation($f1);
        $parcours->addDpeParcour($dpeParcours);

        $bloc = new BlocCompetence();
        $bloc->setFormation($f1);
        $parcours->addBlocCompetence($bloc);

        $demande = new DpeDemande();
        $demande->setFormation($f1);
        $demandeRepo->method('findBy')->willReturn([$demande]);

        $change = new ChangeParcours();
        $change->setFormation($f1);
        $changeRepo->method('findBy')->willReturn([$change]);

        $em->expects($this->once())->method('flush');

        $service = new ParcoursMoveService($em, new NullLogger(), $security);
        $service->move($parcours, $f2);

        $this->assertSame($f2, $parcours->getFormation());
        $this->assertFalse($parcours->isParcoursDefaut());
        $this->assertSame($f2, $dpeParcours->getFormation());
        $this->assertSame($f2, $bloc->getFormation());
        $this->assertSame($f2, $demande->getFormation());
        $this->assertSame($f2, $change->getFormation());

        $this->assertTrue($f2->isHasParcours());
        $this->assertFalse($f1->isHasParcours());
    }
}
