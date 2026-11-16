<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\DTO\Assistance\MailEntrant;
use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Enums\StatutDemandeAssistanceEnum;
use App\Service\Assistance\IntegrationMailAssistance;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use DateTimeImmutable;

/**
 * Mail de masse envoyé par l'équipe (annonce) : chaque usager qui y répond a sa propre demande.
 */
class AnnonceAssistanceTest extends TestCase
{
    use DatabaseTrait;

    private const SUJET = '***IMPORTANT*** ORéOF - Ouverture de la campagne';
    private const ANNONCE = '<annonce-1@univ-reims.fr>';

    private IntegrationMailAssistance $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->integration = $this->getService(IntegrationMailAssistance::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    /**
     * @param list<string> $references
     * @param list<string> $destinataires
     */
    private function mail(string $id, string $expediteur, array $destinataires, array $references = [], string $sujet = 'Re: ' . self::SUJET, string $date = '2026-11-12 18:00'): MessageAssistance
    {
        $message = $this->integration->integrer(new MailEntrant(
            messageId: $id,
            inReplyTo: $references === [] ? null : $references[array_key_last($references)],
            references: $references,
            expediteurEmail: $expediteur,
            expediteurNom: null,
            destinataires: $destinataires,
            sujet: $sujet,
            corpsTexte: 'Texte',
            corpsHtml: null,
            date: new DateTimeImmutable($date),
            automatique: false,
        ));
        self::assertNotNull($message);

        return $message;
    }

    private function annonce(string $id = self::ANNONCE, array $references = []): MessageAssistance
    {
        $destinataires = array_map(static fn (int $i) => sprintf('personne%d@univ-reims.fr', $i), range(1, 11));

        return $this->mail($id, 'oreof@univ-reims.fr', $destinataires, $references, self::SUJET, '2026-11-12 17:58');
    }

    public function testMailDeLEquipeAPlusDe10DestinatairesEstUneAnnonce(): void
    {
        $demande = $this->annonce()->getDemande();

        self::assertTrue($demande->isAnnonce());
        self::assertSame(StatutDemandeAssistanceEnum::RESOLUE, $demande->getStatut());
    }

    public function testMailDeLEquipeA10DestinatairesNEstPasUneAnnonce(): void
    {
        $destinataires = array_map(static fn (int $i) => sprintf('personne%d@univ-reims.fr', $i), range(1, 10));

        self::assertFalse($this->mail('<groupe@univ-reims.fr>', 'oreof@univ-reims.fr', $destinataires, [], self::SUJET)->getDemande()->isAnnonce());
    }

    public function testChaqueReponseAUneAnnonceOuvreSaPropreDemande(): void
    {
        $annonce = $this->annonce()->getDemande();

        $a = $this->mail('<a-1@univ-reims.fr>', 'alice@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE])->getDemande();
        $b = $this->mail('<b-1@univ-reims.fr>', 'bob@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE])->getDemande();

        self::assertNotSame($annonce, $a);
        self::assertNotSame($a, $b);
        self::assertFalse($a->isAnnonce());
        self::assertSame('alice@univ-reims.fr', $a->getDemandeurEmail());
        self::assertSame('bob@univ-reims.fr', $b->getDemandeurEmail());
        self::assertSame(StatutDemandeAssistanceEnum::NOUVELLE, $a->getStatut());
        self::assertCount(1, $annonce->getMessages());
    }

    public function testLaSuiteDeLEchangeRejointLaDemandeDeLUsager(): void
    {
        $this->annonce();
        $a = $this->mail('<a-1@univ-reims.fr>', 'alice@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE])->getDemande();
        $this->mail('<b-1@univ-reims.fr>', 'bob@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE]);

        $reponseEquipe = $this->mail('<o-1@univ-reims.fr>', 'oreof@univ-reims.fr', ['alice@univ-reims.fr'], [self::ANNONCE, '<a-1@univ-reims.fr>'], date: '2026-11-12 19:00');
        $relance = $this->mail('<a-2@univ-reims.fr>', 'alice@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE, '<a-1@univ-reims.fr>', '<o-1@univ-reims.fr>'], date: '2026-11-12 20:00');
        // nouvelle réponse d'Alice directement à l'annonce : même demande qu'avant
        $direct = $this->mail('<a-3@univ-reims.fr>', 'alice@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE], date: '2026-11-12 21:00');

        self::assertSame($a, $reponseEquipe->getDemande());
        self::assertSame($a, $relance->getDemande());
        self::assertSame($a, $direct->getDemande());
        self::assertCount(4, $a->getMessages());
    }

    public function testMessageDeLEquipeAUnUsagerDansLeFilDUneAnnonceOuvreSaDemande(): void
    {
        $this->annonce();

        $transfert = $this->mail('<fwd@univ-reims.fr>', 'oreof@univ-reims.fr', ['carole@univ-reims.fr'], [self::ANNONCE], 'Fwd: ' . self::SUJET);

        self::assertFalse($transfert->getDemande()->isAnnonce());
        self::assertSame('carole@univ-reims.fr', $transfert->getDemande()->getDemandeurEmail());
    }

    public function testUneSecondeAnnonceDansLeFilRejointLaPremiere(): void
    {
        $premiere = $this->annonce()->getDemande();

        self::assertSame($premiere, $this->annonce('<annonce-2@univ-reims.fr>', [self::ANNONCE])->getDemande());
    }

    public function testMailSansFilAuSujetDUneAnnonceNeRejointPasLAnnonce(): void
    {
        $annonce = $this->annonce()->getDemande();

        $demande = $this->mail('<isole@univ-reims.fr>', 'alice@univ-reims.fr', ['oreof@univ-reims.fr'])->getDemande();

        self::assertNotSame($annonce, $demande);
        self::assertFalse($demande->isAnnonce());
    }

    public function testLesAnnoncesNeSontPasDesDemandesOuvertes(): void
    {
        $this->annonce();
        $this->mail('<a-1@univ-reims.fr>', 'alice@univ-reims.fr', ['oreof@univ-reims.fr'], [self::ANNONCE]);

        self::assertSame(1, $this->entityManager->getRepository(DemandeAssistance::class)->compterOuvertes());
    }
}
