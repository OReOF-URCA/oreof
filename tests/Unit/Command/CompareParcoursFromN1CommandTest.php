<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CompareParcoursFromN1Command;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Service\Parcours\ParcoursN1ComparatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CompareParcoursFromN1CommandTest extends TestCase
{
    private function createCommand(
        ?ParcoursRepository $parcoursRepo = null,
        ?FormationRepository $formationRepo = null,
        ?CampagneCollecteRepository $campagneRepo = null,
        ?ParcoursN1ComparatorService $comparator = null,
    ): CompareParcoursFromN1Command {
        return new CompareParcoursFromN1Command(
            $parcoursRepo ?? $this->createMock(ParcoursRepository::class),
            $formationRepo ?? $this->createMock(FormationRepository::class),
            $campagneRepo ?? $this->createMock(CampagneCollecteRepository::class),
            $comparator ?? new ParcoursN1ComparatorService(),
        );
    }

    public function testParcoursNotFoundReturnsFailure(): void
    {
        $parcoursRepo = $this->createMock(ParcoursRepository::class);
        $parcoursRepo->expects(self::any())->method('find')->with('99999')->willReturn(null);

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

        $exitCode = $tester->execute(['--campagne' => '9999-9999']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Campagne "9999-9999" introuvable.', $tester->getDisplay());
    }

    public function testCompareParcoursOutputsTableAndSummary(): void
    {
        $parcoursRepo = $this->createMock(ParcoursRepository::class);

        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT Info N-1');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT Info N');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        $parcoursRepo->expects(self::any())->method('find')->with('42')->willReturn($parcoursN);

        $command = $this->createCommand(parcoursRepo: $parcoursRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--parcours' => '42',
            '--type-diplome' => 'all',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Comparaison de maquette et fiches matières N-1 => N', $output);
        $this->assertStringContainsString('BUT Info N', $output);
        $this->assertStringContainsString('BUT Info N-1', $output);
    }

    public function testCompareParcoursJsonOutput(): void
    {
        $parcoursRepo = $this->createMock(ParcoursRepository::class);

        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT MMI N-1');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT MMI N');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        $parcoursRepo->expects(self::any())->method('find')->with('12')->willReturn($parcoursN);

        $command = $this->createCommand(parcoursRepo: $parcoursRepo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--parcours' => '12',
            '--type-diplome' => 'all',
            '--json' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_parcours', $data);
        $this->assertSame(1, $data['total_parcours']);
        $this->assertSame('BUT MMI N', $data['data'][0]['parcours_n']['libelle']);
    }
}
