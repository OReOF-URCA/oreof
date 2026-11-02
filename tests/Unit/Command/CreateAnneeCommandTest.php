<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CreateAnneeCommand;
use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\TypeDiplome;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAnneeCommandTest extends TestCase
{
    public function testCampagneNotFoundReturnsFailure(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $campagneRepo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')
            ->willReturn($campagneRepo);

        $campagneRepo->method('findOneBy')->willReturn(null);

        $command = new CreateAnneeCommand($em);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--campagne' => '2099-2100']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Campagne "2099-2100" introuvable.', $tester->getDisplay());
    }

    public function testFiltersByTypeDiplomeAndCampagne(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $campagneRepo = $this->createMock(EntityRepository::class);
        $parcoursRepo = $this->createMock(EntityRepository::class);

        $campagne2027 = new CampagneCollecte();
        $campagne2027->setLibelle('2027-2028');
        $campagne2027->setAnnee(2027);

        $campagneRepo->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($campagne2027) {
                if (($criteria['libelle'] ?? null) === '2027' || ($criteria['annee'] ?? null) === 2027) {
                    return $campagne2027;
                }
                return null;
            });

        // Type diplome BUT
        $typeBut = new TypeDiplome();
        $typeBut->setLibelleCourt('BUT');
        $typeBut->setLibelle('Bachelor Universitaire de Technologie');
        $typeBut->setSemestreDebut(1);
        $typeBut->setSemestreFin(6);

        // Type diplome Licence
        $typeLicence = new TypeDiplome();
        $typeLicence->setLibelleCourt('LICENCE');
        $typeLicence->setLibelle('Licence générale');
        $typeLicence->setSemestreDebut(1);
        $typeLicence->setSemestreFin(6);

        // Parcours 1: BUT en 2027 avec 6 semestres sans Annee
        $formationBut = new Formation(null);
        $formationBut->setTypeDiplome($typeBut);
        $parcoursBut = new Parcours();
        $parcoursBut->setLibelle('BUT Info');
        $formationBut->addParcour($parcoursBut);

        $dpeBut = new DpeParcours();
        $dpeBut->setCampagneCollecte($campagne2027);
        $dpeBut->setParcours($parcoursBut);
        $parcoursBut->addDpeParcour($dpeBut);

        for ($i = 1; $i <= 6; $i++) {
            $semestre = new Semestre();
            $semestre->setOrdre($i);
            $sp = new SemestreParcours($semestre, $parcoursBut);
            $sp->setOrdre($i);
            $parcoursBut->addSemestreParcour($sp);
        }

        // Parcours 2: Licence en 2027
        $formationLicence = new Formation(null);
        $formationLicence->setTypeDiplome($typeLicence);
        $parcoursLicence = new Parcours();
        $parcoursLicence->setLibelle('Licence Math');
        $formationLicence->addParcour($parcoursLicence);

        $dpeLicence = new DpeParcours();
        $dpeLicence->setCampagneCollecte($campagne2027);
        $dpeLicence->setParcours($parcoursLicence);
        $parcoursLicence->addDpeParcour($dpeLicence);

        $parcoursRepo->method('findAll')
            ->willReturn([$parcoursBut, $parcoursLicence]);

        $em->method('getRepository')
            ->willReturnCallback(function (string $class) use ($campagneRepo, $parcoursRepo) {
                if ($class === CampagneCollecte::class) {
                    return $campagneRepo;
                }
                return $parcoursRepo;
            });

        $em->expects($this->once())->method('flush');

        $command = new CreateAnneeCommand($em);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--campagne' => '2027',
            '--type-diplome' => 'BUT',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Campagne ciblée', $output);
        $this->assertStringContainsString('Type de diplôme ciblé : BUT', $output);
        $this->assertStringContainsString('1 parcours traité(s)', $output);
        $this->assertStringContainsString('3 année(s) créée(s)', $output);
        $this->assertStringContainsString('semestre(s) rattaché(s)', $output);

        // Vérifier que le parcours BUT a bien reçu ses 3 années et ses 6 semestres liés
        $this->assertCount(3, $parcoursBut->getAnnees());
        foreach ($parcoursBut->getSemestreParcours() as $sp) {
            $this->assertNotNull($sp->getAnnee());
            $this->assertSame((int) ceil($sp->getOrdre() / 2), $sp->getAnnee()->getOrdre());
        }

        // Vérifier que le parcours Licence n'a pas été touché
        $this->assertCount(0, $parcoursLicence->getAnnees());
    }

    public function testDryRunDoesNotPersistOrFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $parcoursRepo = $this->createMock(EntityRepository::class);

        $parcours = new Parcours();
        $parcours->setLibelle('BUT Test');

        $semestre = new Semestre();
        $semestre->setOrdre(1);
        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre(1);
        $parcours->addSemestreParcour($sp);

        $parcoursRepo->method('findAll')->willReturn([$parcours]);
        $em->method('getRepository')->willReturn($parcoursRepo);

        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $command = new CreateAnneeCommand($em);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Mode SIMULATION (dry-run)', $output);
        $this->assertStringContainsString('Simulation terminée', $output);
        $this->assertCount(0, $parcours->getAnnees());
    }
}
