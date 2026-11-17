<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\Service\Assistance\IntegrationMailAssistance;
use App\Service\Assistance\ReleveWebAssistance;
use App\Service\Assistance\Source\EmlSource;
use App\Service\Assistance\Source\MailEntrantFactory;
use App\Service\Assistance\VerrouReleveAssistance;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

class ReleveWebAssistanceTest extends TestCase
{
    use DatabaseTrait;

    private const DOSSIER = __DIR__ . '/../../Fixtures/assistance';

    private MockClock $horloge;

    private string $fichierVerrou;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        // les fixtures sont datées de novembre 2026 : la relève lit la veille de cette date
        $this->horloge = new MockClock('2026-11-14 08:00:00');
        $this->fichierVerrou = sys_get_temp_dir() . '/assistance-releve-test-' . uniqid() . '.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->fichierVerrou);
        $this->tearDownDatabase();
        parent::tearDown();
    }

    private function releve(?VerrouReleveAssistance $verrou = null): ReleveWebAssistance
    {
        return new ReleveWebAssistance(
            new EmlSource(self::DOSSIER, new MailEntrantFactory()),
            $this->getService(IntegrationMailAssistance::class),
            $verrou ?? new VerrouReleveAssistance($this->fichierVerrou),
            new ArrayAdapter(),
            $this->horloge,
        );
    }

    public function testReleveIntegreLesMails(): void
    {
        self::assertGreaterThan(0, $this->releve()->relever());
    }

    public function testDeuxiemeReleveRapprocheeEstIgnoree(): void
    {
        $releve = $this->releve();
        $releve->relever();

        $this->horloge->sleep(ReleveWebAssistance::INTERVALLE_MINIMUM - 1);
        self::assertNull($releve->relever(), 'Relève trop rapprochée : rien n\'est relu');

        $this->horloge->sleep(1);
        self::assertSame(0, $releve->relever(), 'Après l\'intervalle, la relève repart (aucun mail nouveau)');
    }

    public function testReleveForceeIgnoreLIntervalle(): void
    {
        $releve = $this->releve();
        $releve->relever();

        self::assertSame(0, $releve->relever(true));
    }

    public function testReleveEnCoursEstIgnoree(): void
    {
        $autre = new VerrouReleveAssistance($this->fichierVerrou);
        self::assertTrue($autre->acquerir());

        try {
            self::assertNull($this->releve(new VerrouReleveAssistance($this->fichierVerrou))->relever(true));
        } finally {
            $autre->liberer();
        }
    }
}
