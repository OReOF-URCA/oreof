<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use App\Repository\DemandeAssistanceRepository;
use App\Repository\MessageAssistanceRepository;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use DateTimeImmutable;

class AssistancePersistenceTest extends TestCase
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

    public static function creerDemande(string $sujet, DateTimeImmutable $date): DemandeAssistance
    {
        $demande = new DemandeAssistance();
        $demande->setSujet($sujet);
        $demande->setSujetNormalise(mb_strtolower($sujet));
        $demande->setDemandeurEmail('usager@univ-test.fr');
        $demande->setCreated($date);
        $demande->setDateDernierMessage($date);

        return $demande;
    }

    public static function creerMessage(string $messageId, DateTimeImmutable $date): MessageAssistance
    {
        $message = new MessageAssistance();
        $message->setSens(SensMessageAssistanceEnum::ENTRANT);
        $message->setMessageId($messageId);
        $message->setExpediteurEmail('usager@univ-test.fr');
        $message->setSujet('Sujet');
        $message->setCorpsTexte('Bonjour');
        $message->setDateEnvoi($date);

        return $message;
    }

    public function testDemandeAvecMessagesEstPersistee(): void
    {
        $demande = self::creerDemande('Accès DPE', new DateTimeImmutable('2026-11-01 08:00'));
        $demande->addMessage(self::creerMessage('<m1@test>', new DateTimeImmutable('2026-11-01 08:00')));
        $demande->addMessage(self::creerMessage('<m2@test>', new DateTimeImmutable('2026-11-03 10:00')));
        $this->persist($demande);
        $id = $demande->getId();
        $this->clearEntityManager();

        $relue = $this->entityManager->find(DemandeAssistance::class, $id);

        self::assertCount(2, $relue->getMessages());
        self::assertSame(StatutDemandeAssistanceEnum::NOUVELLE, $relue->getStatut());
        self::assertSame('2026-11-03 10:00', $relue->getDateDernierMessage()->format('Y-m-d H:i'));
    }

    public function testFindOneByMessageIdsTrouveParReference(): void
    {
        $demande = self::creerDemande('Accès DPE', new DateTimeImmutable());
        $demande->addMessage(self::creerMessage('<a@x>', new DateTimeImmutable()));
        $this->persist($demande);

        $trouve = $this->getService(MessageAssistanceRepository::class)->findOneByMessageIds(['<z@x>', '<a@x>']);

        self::assertNotNull($trouve);
        self::assertSame('<a@x>', $trouve->getMessageId());
    }

    public function testFindRecenteParSujetIgnoreLesDemandesAnciennes(): void
    {
        $this->persist(self::creerDemande('Vieux sujet', new DateTimeImmutable('-31 days')));

        $trouve = $this->getService(DemandeAssistanceRepository::class)
            ->findRecenteParSujet('vieux sujet', new DateTimeImmutable('-30 days'));

        self::assertNull($trouve);
    }
}
