<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Enums\SensMessageAssistanceEnum;
use App\Service\Assistance\FusionDemandesAssistance;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use DateTimeImmutable;

class FusionDemandesAssistanceTest extends TestCase
{
    use DatabaseTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    private function demande(string $sujet, string $date): DemandeAssistance
    {
        $demande = (new DemandeAssistance())
            ->setSujet($sujet)
            ->setSujetNormalise($sujet)
            ->setDemandeurEmail('usager@univ-test.fr')
            ->setCreated(new DateTimeImmutable($date))
            ->setDateDernierMessage(new DateTimeImmutable($date));
        $demande->addMessage(
            (new MessageAssistance())
                ->setSens(SensMessageAssistanceEnum::ENTRANT)
                ->setMessageId('<' . $sujet . '@test>')
                ->setExpediteurEmail('usager@univ-test.fr')
                ->setSujet($sujet)
                ->setCorpsTexte('...')
                ->setDateEnvoi(new DateTimeImmutable($date))
        );
        $this->persist($demande);

        return $demande;
    }

    public function testFusionDeplaceLesMessagesEtSupprimeLaSource(): void
    {
        $cible = $this->demande('cible', '2026-11-01 08:00');
        $source = $this->demande('source', '2026-11-05 09:00');
        $sourceId = $source->getId();

        $this->getService(FusionDemandesAssistance::class)->fusionner($cible, $source);
        $this->clearEntityManager();

        $relue = $this->entityManager->find(DemandeAssistance::class, $cible->getId());
        self::assertCount(2, $relue->getMessages());
        self::assertSame('2026-11-05 09:00', $relue->getDateDernierMessage()->format('Y-m-d H:i'));
        self::assertNull($this->entityManager->find(DemandeAssistance::class, $sourceId));
    }

    public function testFusionAvecElleMemeEstRefusee(): void
    {
        $demande = $this->demande('seule', '2026-11-01 08:00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('avec elle-même');
        $this->getService(FusionDemandesAssistance::class)->fusionner($demande, $demande);
    }
}
