<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RestoreParcoursFromN1Command;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RestoreParcoursFromN1CommandTest extends TestCase
{
    private function createCommand(
        ?EntityManagerInterface $em = null,
        ?ParcoursRepository $parcoursRepo = null,
        ?FormationRepository $formationRepo = null,
        ?CampagneCollecteRepository $campagneRepo = null,
        ?FicheMatiereRepository $ficheMatiereRepo = null,
    ): RestoreParcoursFromN1Command {
        return new RestoreParcoursFromN1Command(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $parcoursRepo ?? $this->createMock(ParcoursRepository::class),
            $formationRepo ?? $this->createMock(FormationRepository::class),
            $campagneRepo ?? $this->createMock(CampagneCollecteRepository::class),
            $ficheMatiereRepo ?? $this->createMock(FicheMatiereRepository::class),
        );
    }

    public function testParcoursNotFoundReturnsFailure(): void
    {
        $parcoursRepo = $this->createMock(ParcoursRepository::class);
        $parcoursRepo->expects($this->any())->method('find')->with('99999')->willReturn(null);

        $command = $this->createCommand(parcoursRepo: $parcoursRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--parcours' => '99999']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Parcours ID 99999 introuvable.', $tester->getDisplay());
    }

    public function testCampagneNotFoundReturnsFailure(): void
    {
        $campagneRepo = $this->createMock(CampagneCollecteRepository::class);
        $campagneRepo->method('findOneBy')->willReturn(null);

        $command = $this->createCommand(campagneRepo: $campagneRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--campagne' => '2099-2100']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Campagne "2099-2100" introuvable.', $tester->getDisplay());
    }

    public function testRestoreParcoursDryRunDoesNotFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $parcoursRepo = $this->createMock(ParcoursRepository::class);

        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT Info 2026');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT Info 2027');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        $parcoursRepo->expects($this->any())->method('find')->with('1')->willReturn($parcoursN);

        // Fiche N-1
        $ficheN1 = new FicheMatiere();
        $ficheN1->setSigle('R1.01');
        $ficheN1->setLibelle('Initiation Algo');
        $ficheN1->setTypeMatiere('ressource');
        $ficheN1->setVolumeCmPresentiel(20.0);
        $ficheN1->setVolumeTdPresentiel(30.0);
        $parcoursN1->addFicheMatiere($ficheN1);

        // Fiche N (écrasée)
        $ficheN = new FicheMatiere();
        $ficheN->setSigle('R1.01');
        $ficheN->setLibelle('Ecrasé par erreur');
        $ficheN->setTypeMatiere('ressource');
        $ficheN->setFicheMatiereOrigineCopie($ficheN1);
        $parcoursN->addFicheMatiere($ficheN);

        $em->expects($this->never())->method('flush');

        $command = $this->createCommand(em: $em, parcoursRepo: $parcoursRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--parcours' => '1', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Mode SIMULATION (dry-run)', $tester->getDisplay());
        $this->assertStringContainsString('Fiches restaurées: 1', $tester->getDisplay());
        // En dry-run, le libellé ne doit pas avoir changé
        $this->assertSame('Ecrasé par erreur', $ficheN->getLibelle());
    }

    public function testRestoreParcoursRestoresContentAndReattachesEc(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $parcoursRepo = $this->createMock(ParcoursRepository::class);

        // N-1
        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT Info 2026');

        $ficheN1 = new FicheMatiere();
        $ficheN1->setSigle('R1.01');
        $ficheN1->setLibelle('Initiation Algo Original');
        $ficheN1->setTypeMatiere('ressource');
        $ficheN1->setVolumeCmPresentiel(20.5);
        $parcoursN1->addFicheMatiere($ficheN1);

        $ueN1 = new Ue();
        $semestreN1 = new Semestre();
        $ueN1->setSemestre($semestreN1);
        $ecN1 = new ElementConstitutif();
        $ecN1->setCode('EC1');
        $ecN1->setFicheMatiere($ficheN1);
        $ueN1->addElementConstitutif($ecN1);

        // N
        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT Info 2027');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        // Fiche N corrompue
        $ficheN = new FicheMatiere();
        $ficheN->setSigle('R1.01');
        $ficheN->setLibelle('Données corrompues');
        $ficheN->setTypeMatiere('ressource');
        $ficheN->setFicheMatiereOrigineCopie($ficheN1);
        $parcoursN->addFicheMatiere($ficheN);

        // EC N détaché ou pointant sur une mauvaise fiche
        $semestreN = new Semestre();
        $spN = new SemestreParcours($semestreN, $parcoursN);
        $parcoursN->addSemestreParcour($spN);

        $ueN = new Ue();
        $ueN->setSemestre($semestreN);
        $semestreN->addUe($ueN);
        $ecN = new ElementConstitutif();
        $ecN->setCode('EC1');
        $ecN->setEcOrigineCopie($ecN1);
        $ecN->setFicheMatiere(null); // EC orphelin de fiche
        $ueN->addElementConstitutif($ecN);

        $parcoursRepo->expects($this->any())->method('find')->with('2')->willReturn($parcoursN);

        $em->expects($this->once())->method('flush');

        $command = $this->createCommand(em: $em, parcoursRepo: $parcoursRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--parcours' => '2']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // Vérification des données restaurées
        $this->assertSame('Initiation Algo Original', $ficheN->getLibelle());
        $this->assertSame(20.5, $ficheN->getVolumeCmPresentiel());
        // Vérification du rattachement de l'EC
        $this->assertSame($ficheN, $ecN->getFicheMatiere());
    }

    public function testCleansParasiticMutualisations(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $parcoursRepo = $this->createMock(ParcoursRepository::class);

        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT Info 2026');

        $ficheN1 = new FicheMatiere();
        $ficheN1->setSigle('R1.01');
        $ficheN1->setLibelle('Initiation Algo');
        $ficheN1->setTypeMatiere('ressource');
        $parcoursN1->addFicheMatiere($ficheN1);

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT Info 2027');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        $ficheN = new FicheMatiere();
        $ficheN->setSigle('R1.01');
        $ficheN->setLibelle('Initiation Algo');
        $ficheN->setTypeMatiere('ressource');
        $ficheN->setFicheMatiereOrigineCopie($ficheN1);
        $parcoursN->addFicheMatiere($ficheN);

        // Mutualisation parasite : parcoursN a une fiche mutualisée venant d'une autre formation
        $formationAutre = new Formation(null);
        $autreParcoursN = new Parcours();
        $autreParcoursN->setLibelle('BUT Autre 2027');
        $autreParcoursN->setFormation($formationAutre);

        $formationN = new Formation(null);
        $parcoursN->setFormation($formationN);

        $ficheParasite = new FicheMatiere();
        $ficheParasite->setSigle('R1.01');
        $ficheParasite->setLibelle('Algo Autre');
        $ficheParasite->setTypeMatiere('ressource');
        $ficheParasite->setParcours($autreParcoursN);

        $mutParasite = new FicheMatiereMutualisable();
        $mutParasite->setFicheMatiere($ficheParasite);
        $mutParasite->setParcours($parcoursN);
        $parcoursN->addFicheMatiereParcour($mutParasite);

        $parcoursRepo->expects($this->any())->method('find')->with('3')->willReturn($parcoursN);

        $em->expects($this->atLeastOnce())->method('remove')->with($mutParasite);
        $em->expects($this->once())->method('flush');

        $command = $this->createCommand(em: $em, parcoursRepo: $parcoursRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--parcours' => '3']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Mutus parasites supprimées: 1', $tester->getDisplay());
    }
}
