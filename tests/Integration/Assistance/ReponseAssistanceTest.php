<?php

declare(strict_types=1);

namespace App\Tests\Integration\Assistance;

use App\DTO\Assistance\MailEntrant;
use App\Entity\DemandeAssistance;
use App\Entity\User;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use App\Service\Assistance\IntegrationMailAssistance;
use App\Service\Assistance\ReponseAssistance;
use App\Tests\Support\DatabaseTrait;
use App\Tests\Support\TestCase;
use DateTimeImmutable;
use Symfony\Component\Mime\Email;

class ReponseAssistanceTest extends TestCase
{
    use DatabaseTrait;

    private DemandeAssistance $demande;

    private User $auteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();

        $this->auteur = new User();
        $this->auteur->setUsername('admin-reponse-assistance');
        $this->auteur->setEmail('admin-reponse@univ-test.fr');
        $this->auteur->setNom('Admin');
        $this->auteur->setPrenom('Réponse');
        $this->auteur->setRoles(['ROLE_ADMIN']);
        $this->persist($this->auteur);

        $this->demande = $this->getService(IntegrationMailAssistance::class)->integrer(new MailEntrant(
            messageId: '<question-1@univ-test.fr>',
            inReplyTo: null,
            references: [],
            expediteurEmail: 'usager@univ-test.fr',
            expediteurNom: 'Usager Test',
            destinataires: ['oreof@univ-reims.fr', 'collegue@univ-test.fr'],
            sujet: 'Accès DPE',
            corpsTexte: 'Je n\'ai pas accès.',
            corpsHtml: null,
            date: new DateTimeImmutable('2026-11-13 09:00'),
            automatique: false,
        ))->getDemande();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    private function repondre(bool $resoudre = false): \App\Entity\MessageAssistance
    {
        return $this->getService(ReponseAssistance::class)->repondre(
            $this->demande,
            $this->auteur,
            '<p>Bonjour, c\'est <strong>corrigé</strong>.</p><script>x()</script>',
            ['usager@univ-test.fr'],
            $resoudre,
        );
    }

    public function testReponseEnvoieUnMailDansLeFil(): void
    {
        $this->repondre();

        self::assertEmailCount(1);
        /** @var Email $mail */
        $mail = self::getMailerMessage();
        self::assertSame('Re: Accès DPE', $mail->getSubject());
        self::assertSame('oreof@univ-reims.fr', $mail->getFrom()[0]->getAddress());
        self::assertSame('usager@univ-test.fr', $mail->getTo()[0]->getAddress());
        self::assertSame('<question-1@univ-test.fr>', $mail->getHeaders()->get('In-Reply-To')->getBodyAsString());
        self::assertStringContainsString('<question-1@univ-test.fr>', $mail->getHeaders()->get('References')->getBodyAsString());
        self::assertStringNotContainsString('<script', (string) $mail->getHtmlBody());
        self::assertStringContainsString('corrigé', (string) $mail->getTextBody());
    }

    public function testReponseEstEnregistreeEtChangeLeStatut(): void
    {
        $message = $this->repondre();

        $mail = self::getMailerMessage();
        self::assertSame(SensMessageAssistanceEnum::SORTANT, $message->getSens());
        self::assertSame($mail->getHeaders()->get('Message-ID')->getBodyAsString(), $message->getMessageId());
        self::assertSame($this->auteur, $message->getAuteur());
        self::assertSame(StatutDemandeAssistanceEnum::EN_ATTENTE_USAGER, $this->demande->getStatut());
    }

    public function testRepondreSansResponsableAssigneLAuteur(): void
    {
        self::assertNull($this->demande->getResponsable());

        $this->repondre();

        self::assertSame($this->auteur, $this->demande->getResponsable());
    }

    public function testRepondreNeRemplacePasUnResponsableExistant(): void
    {
        $collegue = new User();
        $collegue->setUsername('collegue-reponse-assistance');
        $collegue->setEmail('collegue-reponse@univ-test.fr');
        $collegue->setNom('Collègue');
        $collegue->setPrenom('Admin');
        $collegue->setRoles(['ROLE_ADMIN']);
        $this->persist($collegue);
        $this->demande->setResponsable($collegue);

        $this->repondre();

        self::assertSame($collegue, $this->demande->getResponsable());
    }

    public function testReponseEtResoudre(): void
    {
        $this->repondre(true);

        self::assertSame(StatutDemandeAssistanceEnum::RESOLUE, $this->demande->getStatut());
    }

    public function testReponseDeLUsagerSeRattache(): void
    {
        $envoye = $this->repondre();

        $retour = $this->getService(IntegrationMailAssistance::class)->integrer(new MailEntrant(
            messageId: '<retour-1@univ-test.fr>',
            inReplyTo: $envoye->getMessageId(),
            references: [$envoye->getMessageId()],
            expediteurEmail: 'usager@univ-test.fr',
            expediteurNom: 'Usager Test',
            destinataires: ['oreof@univ-reims.fr'],
            sujet: 'Re: Re: Accès DPE (autre formulation)',
            corpsTexte: 'Merci !',
            corpsHtml: null,
            date: new DateTimeImmutable('+1 hour'),
            automatique: false,
        ));

        self::assertSame($this->demande, $retour->getDemande());
        self::assertSame(StatutDemandeAssistanceEnum::EN_COURS, $this->demande->getStatut());
    }

    public function testDestinatairesProposesExcluentLEquipe(): void
    {
        self::assertSame(
            ['usager@univ-test.fr', 'collegue@univ-test.fr'],
            $this->getService(ReponseAssistance::class)->destinatairesProposes($this->demande)
        );
    }
}
