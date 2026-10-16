<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\FixCoherenceOuvertureCommand;
use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmission;
use App\Entity\PlateformeAdmissionParametre;
use App\Enums\TypeModificationDpeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FixCoherenceOuvertureCommandTest extends TestCase
{
    public function testCampagneNotFoundReturnsFailure(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $campagneRepo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')
            ->willReturn($campagneRepo);

        $campagneRepo->method('findOneBy')->willReturn(null);

        $command = new FixCoherenceOuvertureCommand($em);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--campagne' => '2099-2100']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Campagne "2099-2100" introuvable.', $tester->getDisplay());
    }

    public function testDryRunDetectsInconsistenciesWithoutModifying(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $campagneRepo = $this->createMock(EntityRepository::class);
        $dpeRepo = $this->createMock(EntityRepository::class);

        $campagne = new CampagneCollecte();
        $campagne->setLibelle('2027-2028');

        $campagneRepo->method('findOneBy')
            ->willReturn($campagne);

        // Parcours 1 : Fermé mais avec une année ouverte
        $parcours1 = new Parcours(null);
        $parcours1->setLibelle('Parcours Ferme Test');
        $annee1 = new Annee();
        $annee1->setOrdre(1);
        $annee1->setIsOuvert(true);
        $annee1->setCapaciteAccueil(25);
        $parcours1->addAnnee($annee1);

        $dpe1 = new DpeParcours();
        $dpe1->setCampagneCollecte($campagne);
        $dpe1->setParcours($parcours1);
        $dpe1->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);

        // Parcours 2 : Ouvert mais avec toutes ses années fermées
        $parcours2 = new Parcours(null);
        $parcours2->setLibelle('Parcours Ouvert Annees Fermees');
        $annee2 = new Annee();
        $annee2->setOrdre(1);
        $annee2->setIsOuvert(false);
        $annee2->setCapaciteAccueil(0);
        $parcours2->addAnnee($annee2);

        $dpe2 = new DpeParcours();
        $dpe2->setCampagneCollecte($campagne);
        $dpe2->setParcours($parcours2);
        $dpe2->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $em->method('getRepository')->willReturnCallback(function (string $class) use ($campagneRepo, $dpeRepo) {
            if ($class === CampagneCollecte::class) {
                return $campagneRepo;
            }
            if ($class === DpeParcours::class) {
                return $dpeRepo;
            }
            return null;
        });

        $dpeRepo->method('findBy')
            ->willReturn([$dpe1, $dpe2]);

        // En mode dry-run, flush ne doit JAMAIS être appelé
        $em->expects($this->never())->method('flush');

        $command = new FixCoherenceOuvertureCommand($em);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Mode SIMULATION', $output);
        $this->assertStringContainsString('Parcours Ferme Test', $output);
        $this->assertStringContainsString('Parcours Ouvert Annees Fermees', $output);

        // Vérifier que rien n'a été muté
        $this->assertTrue($annee1->isOuvert());
        $this->assertSame(25, $annee1->getCapaciteAccueil());
        $this->assertSame(TypeModificationDpeEnum::OUVERT, $dpe2->getEtatReconduction());
    }

    public function testApplyModifiesEntitiesAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $campagneRepo = $this->createMock(EntityRepository::class);
        $dpeRepo = $this->createMock(EntityRepository::class);

        $campagne = new CampagneCollecte();
        $campagne->setLibelle('2027-2028');

        $campagneRepo->method('findOneBy')
            ->willReturn($campagne);

        // Parcours 1 : Fermé avec année ouverte et paramètre de plateforme
        $parcours1 = new Parcours(null);
        $parcours1->setLibelle('Parcours Ferme');
        $annee1 = new Annee();
        $annee1->setOrdre(1);
        $annee1->setIsOuvert(true);
        $annee1->setCapaciteAccueil(30);

        $plateformeParam = new PlateformeAdmissionParametre();
        $plateformeParam->setCampagne($campagne);
        $plateformeParam->setActive(true);
        $plateformeParam->setCapaciteGlobale(30);
        $annee1->addAdmissionPlateformeParametre($plateformeParam);

        $parcours1->addAnnee($annee1);

        $dpe1 = new DpeParcours();
        $dpe1->setCampagneCollecte($campagne);
        $dpe1->setParcours($parcours1);
        $dpe1->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE_CFVU);

        // Parcours 2 : Ouvert avec toutes années fermées
        $parcours2 = new Parcours(null);
        $parcours2->setLibelle('Parcours Ouvert Annees Fermees');
        $annee2 = new Annee();
        $annee2->setOrdre(1);
        $annee2->setIsOuvert(false);
        $parcours2->addAnnee($annee2);

        $dpe2 = new DpeParcours();
        $dpe2->setCampagneCollecte($campagne);
        $dpe2->setParcours($parcours2);
        $dpe2->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $em->method('getRepository')->willReturnCallback(function (string $class) use ($campagneRepo, $dpeRepo) {
            if ($class === CampagneCollecte::class) {
                return $campagneRepo;
            }
            if ($class === DpeParcours::class) {
                return $dpeRepo;
            }
            return null;
        });

        $dpeRepo->method('findBy')
            ->willReturn([$dpe1, $dpe2]);

        // Flush doit être appelé une fois
        $em->expects($this->once())->method('flush');

        $command = new FixCoherenceOuvertureCommand($em);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--apply' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Modifications appliquées avec succès', $output);

        // Vérification Incohérence 1
        $this->assertFalse($annee1->isOuvert());
        $this->assertSame(0, $annee1->getCapaciteAccueil());
        $this->assertFalse($plateformeParam->isActive());
        $this->assertNull($plateformeParam->getCapaciteGlobale());

        // Vérification Incohérence 2
        $this->assertSame(TypeModificationDpeEnum::NON_OUVERTURE, $dpe2->getEtatReconduction());
    }

    public function testActionFermerAnneesOnlyModifiesAnnees(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $campagneRepo = $this->createMock(EntityRepository::class);
        $dpeRepo = $this->createMock(EntityRepository::class);

        $campagne = new CampagneCollecte();
        $campagne->setLibelle('2027-2028');

        $campagneRepo->method('findOneBy')
            ->willReturn($campagne);

        $parcours1 = new Parcours(null);
        $annee1 = new Annee();
        $annee1->setIsOuvert(true);
        $parcours1->addAnnee($annee1);
        $dpe1 = new DpeParcours();
        $dpe1->setCampagneCollecte($campagne);
        $dpe1->setParcours($parcours1);
        $dpe1->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);

        $parcours2 = new Parcours(null);
        $annee2 = new Annee();
        $annee2->setIsOuvert(false);
        $parcours2->addAnnee($annee2);
        $dpe2 = new DpeParcours();
        $dpe2->setCampagneCollecte($campagne);
        $dpe2->setParcours($parcours2);
        $dpe2->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $em->method('getRepository')->willReturnCallback(function (string $class) use ($campagneRepo, $dpeRepo) {
            if ($class === CampagneCollecte::class) {
                return $campagneRepo;
            }
            if ($class === DpeParcours::class) {
                return $dpeRepo;
            }
            return null;
        });

        $dpeRepo->method('findBy')->willReturn([$dpe1, $dpe2]);
        $em->expects($this->once())->method('flush');

        $command = new FixCoherenceOuvertureCommand($em);
        $tester = new CommandTester($command);

        $tester->execute(['--apply' => true, '--action' => 'fermer-annees']);

        $this->assertFalse($annee1->isOuvert());
        // $dpe2 ne doit PAS avoir été modifié
        $this->assertSame(TypeModificationDpeEnum::OUVERT, $dpe2->getEtatReconduction());
    }
}
