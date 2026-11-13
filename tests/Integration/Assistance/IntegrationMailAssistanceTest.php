<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\DTO\Assistance\MailEntrant;
use App\Entity\DemandeAssistance;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use App\Service\Assistance\IntegrationMailAssistance;
use App\Service\Assistance\Source\EmlSource;
use App\Service\Assistance\Source\MailEntrantFactory;
use App\Service\Assistance\StockagePieceJointe;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use DateTimeImmutable;

class IntegrationMailAssistanceTest extends TestCase
{
    use DatabaseTrait;

    private const DOSSIER = __DIR__ . '/../../Fixtures/assistance';

    private IntegrationMailAssistance $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->integration = $this->getService(IntegrationMailAssistance::class);
    }

    protected function tearDown(): void
    {
        (new \Symfony\Component\Filesystem\Filesystem())->remove((string) $this->container->getParameter('assistance_pieces_jointes_dir'));
        $this->tearDownDatabase();
        parent::tearDown();
    }

    private function mail(string $prefixe): MailEntrant
    {
        $fichier = glob(self::DOSSIER . '/' . $prefixe . '-*.eml')[0];

        return iterator_to_array((new EmlSource($fichier, new MailEntrantFactory()))->lire(new DateTimeImmutable()), false)[0];
    }

    private function nombreDemandes(): int
    {
        return $this->entityManager->getRepository(DemandeAssistance::class)->count([]);
    }

    public function testReponseRejointLaDemandeDOrigine(): void
    {
        $premier = $this->integration->integrer($this->mail('01'));
        $second = $this->integration->integrer($this->mail('02'));

        self::assertSame(1, $this->nombreDemandes());
        self::assertSame($premier->getDemande(), $second->getDemande());
        self::assertCount(2, $premier->getDemande()->getMessages());
        self::assertSame('catalin.popa@univ-test.fr', $premier->getDemande()->getDemandeurEmail());
        self::assertSame('demande de mise à jour des informations du parcours lp geotp', $premier->getDemande()->getSujetNormalise());
    }

    public function testMemeSujetSansFilRejointLaDemande(): void
    {
        $this->integration->integrer($this->mail('01'));
        $this->integration->integrer($this->mail('03'));

        self::assertSame(1, $this->nombreDemandes());
    }

    public function testMemeSujetApres30JoursCreeUneNouvelleDemande(): void
    {
        $ancienne = (new DemandeAssistance())
            ->setSujet('Demande de mise à jour des informations du parcours LP GEOTP')
            ->setSujetNormalise('demande de mise à jour des informations du parcours lp geotp')
            ->setDemandeurEmail('ancien@univ-test.fr')
            ->setCreated(new DateTimeImmutable('2026-10-10'))
            ->setDateDernierMessage(new DateTimeImmutable('2026-10-10'));
        $this->persist($ancienne);

        $message = $this->integration->integrer($this->mail('03'));

        self::assertSame(2, $this->nombreDemandes());
        self::assertNotSame($ancienne, $message->getDemande());
    }

    public function testDeuxiemeIntegrationNeCreePasDeDoublon(): void
    {
        $source = new EmlSource(self::DOSSIER, new MailEntrantFactory());

        self::assertSame(6, $this->integration->integrerTout($source, new DateTimeImmutable('-7 days')));
        self::assertSame(0, $this->integration->integrerTout($source, new DateTimeImmutable('-7 days')));
        self::assertNull($this->integration->integrer($this->mail('05')));
    }

    public function testEntrantRouvreUneDemandeResolue(): void
    {
        $demande = $this->integration->integrer($this->mail('01'))->getDemande();
        $demande->setStatut(StatutDemandeAssistanceEnum::RESOLUE);

        $this->integration->integrer($this->mail('02'));

        self::assertSame(StatutDemandeAssistanceEnum::EN_COURS, $demande->getStatut());
    }

    public function testRebondNeRouvrePas(): void
    {
        $demande = $this->integration->integrer($this->mail('01'))->getDemande();
        $demande->setStatut(StatutDemandeAssistanceEnum::RESOLUE);

        $rebond = $this->integration->integrer($this->mail('06'));

        self::assertSame($demande, $rebond->getDemande());
        self::assertTrue($rebond->isAutomatique());
        self::assertSame(StatutDemandeAssistanceEnum::RESOLUE, $demande->getStatut());
    }

    public function testMailDeLEquipeEstSortant(): void
    {
        $demande = $this->integration->integrer($this->mail('01'))->getDemande();

        $message = $this->integration->integrer(new MailEntrant(
            messageId: '<equipe-1@univ-reims.fr>',
            inReplyTo: '<demande-geotp-1@univ-test.fr>',
            references: ['<demande-geotp-1@univ-test.fr>'],
            expediteurEmail: 'oreof@univ-reims.fr',
            expediteurNom: 'ORéOF',
            destinataires: ['catalin.popa@univ-test.fr'],
            sujet: 'Re: Demande de mise à jour des informations du parcours LP GEOTP',
            corpsTexte: 'Bonjour, c\'est corrigé.',
            corpsHtml: null,
            date: new DateTimeImmutable('2026-11-13 11:00'),
            automatique: false,
        ));

        self::assertSame(SensMessageAssistanceEnum::SORTANT, $message->getSens());
        self::assertSame($demande, $message->getDemande());
        self::assertSame('catalin.popa@univ-test.fr', $demande->getDemandeurEmail());
    }

    public function testHtmlEstNettoye(): void
    {
        $html = $this->integration->integrer($this->mail('04'))->getCorpsHtml();

        self::assertNotNull($html);
        self::assertStringContainsString('urgent', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('http://', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testPieceJointeEstStockee(): void
    {
        $message = $this->integration->integrer($this->mail('01'));
        $piece = $message->getPiecesJointes()->first();
        $chemin = $this->getService(StockagePieceJointe::class)->cheminAbsolu($piece);

        self::assertSame('fiche.pdf', $piece->getNomOriginal());
        self::assertFileExists($chemin);
        self::assertStringStartsWith('%PDF', (string) file_get_contents($chemin));
    }
}
