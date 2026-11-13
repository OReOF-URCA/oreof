<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\DTO\Assistance\MailEntrant;
use App\Service\Assistance\Source\EmlSource;
use App\Service\Assistance\Source\MailEntrantFactory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EmlSourceTest extends TestCase
{
    private const DOSSIER = __DIR__ . '/../../../Fixtures/assistance';

    /**
     * @return array<string, MailEntrant> mails indexés par le préfixe du fichier (« 01 »…)
     */
    private function lire(): array
    {
        $factory = new MailEntrantFactory();
        $mails = [];
        foreach (glob(self::DOSSIER . '/*.eml') as $fichier) {
            $lus = iterator_to_array((new EmlSource($fichier, $factory))->lire(new DateTimeImmutable()), false);
            $mails[substr(basename($fichier), 0, 2)] = $lus[0];
        }

        return $mails;
    }

    public function testLitTousLesFichiersDuDossier(): void
    {
        $source = new EmlSource(self::DOSSIER, new MailEntrantFactory());

        self::assertCount(6, iterator_to_array($source->lire(new DateTimeImmutable()), false));
    }

    public function testReponseExposeInReplyTo(): void
    {
        $mails = $this->lire();

        self::assertSame('<demande-geotp-1@univ-test.fr>', $mails['01']->messageId);
        self::assertSame($mails['01']->messageId, $mails['02']->inReplyTo);
        self::assertSame(['<demande-geotp-1@univ-test.fr>'], $mails['02']->references);
    }

    public function testDestinatairesIncluentLaCopie(): void
    {
        self::assertSame(['oreof@univ-reims.fr', 'jean.lignon@univ-test.fr'], $this->lire()['02']->destinataires);
    }

    public function testSujetEncodeEstDecode(): void
    {
        $mails = $this->lire();

        self::assertSame('Validation offre de formation Odontologie été', $mails['04']->sujet);
        self::assertSame('Demande de mise à jour des informations du parcours LP GEOTP', $mails['01']->sujet);
    }

    public function testHtmlSeulProduitUnCorpsTexte(): void
    {
        $mail = $this->lire()['04'];

        self::assertNotNull($mail->corpsHtml);
        self::assertStringContainsString('la validation de l\'offre est bloquée', $mail->corpsTexte);
        self::assertStringNotContainsString('<', $mail->corpsTexte);
    }

    public function testPieceJointeEstLue(): void
    {
        $mail = $this->lire()['01'];

        self::assertCount(1, $mail->piecesJointes);
        self::assertSame('fiche.pdf', $mail->piecesJointes[0]->nom);
        self::assertSame('application/pdf', $mail->piecesJointes[0]->mimeType);
        self::assertStringStartsWith('%PDF', $mail->piecesJointes[0]->contenu);
    }

    public function testRebondEstAutomatique(): void
    {
        $mails = $this->lire();

        self::assertTrue($mails['06']->automatique);
        self::assertSame('mailer-daemon@univ-test.fr', $mails['06']->expediteurEmail);
        self::assertFalse($mails['01']->automatique);
    }

    public function testSansMessageIdDonneNull(): void
    {
        self::assertNull($this->lire()['05']->messageId);
    }

    public function testDateEtExpediteur(): void
    {
        $mail = $this->lire()['01'];

        self::assertSame('2026-11-13T08:27:00+01:00', $mail->date->format(DATE_ATOM));
        self::assertSame('catalin.popa@univ-test.fr', $mail->expediteurEmail);
        self::assertSame('Catalin Popa', $mail->expediteurNom);
    }
}
