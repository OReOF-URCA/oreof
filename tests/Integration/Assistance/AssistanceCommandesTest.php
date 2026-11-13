<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class AssistanceCommandesTest extends TestCase
{
    use DatabaseTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove((string) $this->container->getParameter('assistance_pieces_jointes_dir'));
        $this->tearDownDatabase();
        parent::tearDown();
    }

    private function commande(string $nom): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find($nom));
    }

    public function testImporterEmlIntegreLesMailsUneSeuleFois(): void
    {
        $commande = $this->commande('app:assistance:importer-eml');
        $dossier = __DIR__ . '/../../Fixtures/assistance';

        self::assertSame(Command::SUCCESS, $commande->execute(['chemin' => $dossier]));
        self::assertStringContainsString('6 mail(s) intégré(s).', $commande->getDisplay());

        self::assertSame(Command::SUCCESS, $commande->execute(['chemin' => $dossier]));
        self::assertStringContainsString('0 mail(s) intégré(s).', $commande->getDisplay());
    }

    public function testImporterEmlCheminIntrouvableEchoue(): void
    {
        self::assertSame(Command::FAILURE, $this->commande('app:assistance:importer-eml')->execute(['chemin' => '/inexistant']));
    }

    public function testReleverSansConfigurationEchoue(): void
    {
        $commande = $this->commande('app:assistance:relever');

        self::assertSame(Command::FAILURE, $commande->execute([]));
        self::assertStringContainsString('Boîte IMAP non configurée', $commande->getDisplay());
    }
}
