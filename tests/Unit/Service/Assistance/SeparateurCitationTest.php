<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Service\Assistance\SeparateurCitation;
use PHPUnit\Framework\TestCase;

class SeparateurCitationTest extends TestCase
{
    private SeparateurCitation $separateur;

    protected function setUp(): void
    {
        $this->separateur = new SeparateurCitation();
    }

    public function testHtmlSansCitationResteEntier(): void
    {
        self::assertSame(['<p>Bonjour</p>', null], $this->separateur->separerHtml('<p>Bonjour</p>'));
    }

    public function testHtmlCoupeALaLigneAEcrit(): void
    {
        [$visible, $historique] = $this->separateur->separerHtml('<p>Merci.</p><p>Le 12/11/2026 à 17:58, Emeline <a href="mailto:e@x.fr">e@x.fr</a> a écrit :</p><blockquote>Ancien</blockquote>');

        self::assertSame('<p>Merci.</p><p>', $visible);
        self::assertStringStartsWith('Le 12/11/2026', $historique);
        self::assertStringContainsString('Ancien', $historique);
    }

    public function testHtmlCoupeAuBlocDeReponseOutlook(): void
    {
        [$visible, $historique] = $this->separateur->separerHtml('<div>Voici ma réponse</div><hr><div id="divRplyFwdMsg"><b>De&nbsp;:</b> ORéOF<br><b>Envoyé&nbsp;:</b> jeudi</div><div>Ancien</div>');

        self::assertSame('<div>Voici ma réponse</div><hr>', $visible);
        self::assertStringContainsString('Ancien', $historique);
    }

    public function testHtmlCoupeAuxEnTetesDeOutlookSansIdentifiant(): void
    {
        [$visible, $historique] = $this->separateur->separerHtml('<p>Réponse</p><p><b>De :</b> Lou<br><b>Envoyé :</b> lundi<br><b>À :</b> oreof</p><p>Ancien</p>');

        self::assertSame('<p>Réponse</p><p><b>', $visible);
        self::assertStringContainsString('Ancien', $historique);
    }

    public function testHtmlCoupeAuMessageTransfere(): void
    {
        [$visible] = $this->separateur->separerHtml('<p>Pour info</p><div>-------- Message transféré --------</div><p>Ancien</p>');

        self::assertSame('<p>Pour info</p><div>', $visible);
    }

    public function testCitationDesLaPremiereLigneNeMasqueRien(): void
    {
        $html = '<blockquote>Tout le contenu est cité</blockquote>';

        self::assertSame([$html, null], $this->separateur->separerHtml($html));
    }

    public function testTexteCoupeALaLigneAEcrit(): void
    {
        [$visible, $historique] = $this->separateur->separerTexte("Merci.\n\nLe 12/11/2026 à 17:58, Emeline\n<e@x.fr> a écrit :\n> Ancien");

        self::assertSame('Merci.', $visible);
        self::assertStringContainsString('> Ancien', $historique);
    }

    public function testTexteCoupeAuxLignesCitees(): void
    {
        [$visible, $historique] = $this->separateur->separerTexte("Oui.\n> question\n> suite");

        self::assertSame('Oui.', $visible);
        self::assertSame("> question\n> suite", $historique);
    }

    public function testTexteSansCitationResteEntier(): void
    {
        self::assertSame(['Bonjour', null], $this->separateur->separerTexte('Bonjour'));
    }
}
