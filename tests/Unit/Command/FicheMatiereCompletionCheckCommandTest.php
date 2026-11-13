<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\FicheMatiereCompletionCheckCommand;
use App\DTO\TabIssue;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereTabState;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FicheMatiereTabStateRepository;
use App\Service\FicheMatiere\FicheMatiereTabCompletionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FicheMatiereCompletionCheckCommandTest extends TestCase
{
    public function testIncompatibleDryRunAndSave(): void
    {
        $checker = $this->createMock(FicheMatiereTabCompletionChecker::class);
        $ficheRepo = $this->createMock(FicheMatiereRepository::class);
        $campagneRepo = $this->createMock(CampagneCollecteRepository::class);
        $tabStateRepo = $this->createMock(FicheMatiereTabStateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $command = new FicheMatiereCompletionCheckCommand(
            $checker,
            $ficheRepo,
            $campagneRepo,
            $tabStateRepo,
            $em,
            new NullLogger()
        );

        $tester = new CommandTester($command);
        $code = $tester->execute([
            '--dry-run' => true,
            '--save' => true,
        ]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Les options --dry-run et --save sont incompatibles', $tester->getDisplay());
    }

    public function testCampagneNotFound(): void
    {
        $checker = $this->createMock(FicheMatiereTabCompletionChecker::class);
        $ficheRepo = $this->createMock(FicheMatiereRepository::class);
        $campagneRepo = $this->createMock(CampagneCollecteRepository::class);
        $tabStateRepo = $this->createMock(FicheMatiereTabStateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $campagneRepo->method('find')->willReturn(null);

        $command = new FicheMatiereCompletionCheckCommand(
            $checker,
            $ficheRepo,
            $campagneRepo,
            $tabStateRepo,
            $em,
            new NullLogger()
        );

        $tester = new CommandTester($command);
        $code = $tester->execute([
            '--campagne' => 999,
        ]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Campagne 999 introuvable', $tester->getDisplay());
    }

    public function testExecuteWithFicheMatiere(): void
    {
        $checker = $this->createMock(FicheMatiereTabCompletionChecker::class);
        $ficheRepo = $this->createMock(FicheMatiereRepository::class);
        $campagneRepo = $this->createMock(CampagneCollecteRepository::class);
        $tabStateRepo = $this->createMock(FicheMatiereTabStateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $fiche = new FicheMatiere();
        $ref = new \ReflectionProperty(FicheMatiere::class, 'id');
        $ref->setValue($fiche, 42);
        $fiche->setLibelle('Algorithmique Avancée');

        $ficheRepo->method('find')->willReturn($fiche);
        $tabStateRepo->method('indexByTabKey')->willReturn([]);

        $checker->method('getTabIssues')->willReturnCallback(function (FicheMatiere $f, string $tab): array {
            if ($tab === 'identite') {
                return [];
            }
            return [new TabIssue('field', 'Field', 'Issue message')];
        });

        $em->expects($this->once())->method('flush');

        $command = new FicheMatiereCompletionCheckCommand(
            $checker,
            $ficheRepo,
            $campagneRepo,
            $tabStateRepo,
            $em,
            new NullLogger()
        );

        $tester = new CommandTester($command);
        $code = $tester->execute([
            '--fiche-matiere' => 42,
            '--save' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Démarrage du traitement de 1 fiches matières', $display);
        $this->assertStringContainsString('Traitement terminé : 1 / 1 fiches matières traitées, 1 modifiées', $display);
    }

    public function testExecuteWithCampagne(): void
    {
        $checker = $this->createMock(FicheMatiereTabCompletionChecker::class);
        $ficheRepo = $this->createMock(FicheMatiereRepository::class);
        $campagneRepo = $this->createMock(CampagneCollecteRepository::class);
        $tabStateRepo = $this->createMock(FicheMatiereTabStateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $campagne = new CampagneCollecte();
        $campagneRepo->method('find')->willReturn($campagne);

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $ficheRepo->method('createQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getScalarResult')->willReturn([
            ['id' => '101'],
            ['id' => '102'],
        ]);

        $fiche1 = new FicheMatiere();
        $ref = new \ReflectionProperty(FicheMatiere::class, 'id');
        $ref->setValue($fiche1, 101);
        $fiche1->setLibelle('EC 1');

        $fiche2 = new FicheMatiere();
        $ref->setValue($fiche2, 102);
        $fiche2->setLibelle('EC 2');

        $ficheRepo->method('find')->willReturnMap([
            [101, $fiche1],
            [102, $fiche2],
        ]);

        $tabStateRepo->method('indexByTabKey')->willReturn([]);
        $checker->method('getTabIssues')->willReturn([]);

        $command = new FicheMatiereCompletionCheckCommand(
            $checker,
            $ficheRepo,
            $campagneRepo,
            $tabStateRepo,
            $em,
            new NullLogger()
        );

        $tester = new CommandTester($command);
        $code = $tester->execute([
            '--campagne' => 12,
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Démarrage du traitement de 2 fiches matières', $display);
        $this->assertStringContainsString('Traitement terminé : 2 / 2 fiches matières traitées, 2 modifiées', $display);
    }
}
