<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Service\Assistance\LienAutomatique;
use PHPUnit\Framework\TestCase;

class LienAutomatiqueTest extends TestCase
{
    private LienAutomatique $lien;

    protected function setUp(): void
    {
        $this->lien = new LienAutomatique();
    }

    public function testAdresseDansLeHtmlDevientUnLien(): void
    {
        self::assertSame(
            '<li><strong>Page :</strong> <a href="https://oreof.univ-reims.fr/offre/composante/3">https://oreof.univ-reims.fr/offre/composante/3</a></li>',
            $this->lien->html('<li><strong>Page :</strong> https://oreof.univ-reims.fr/offre/composante/3</li>')
        );
    }

    public function testPonctuationFinaleHorsDuLien(): void
    {
        self::assertSame(
            '<p>Voir <a href="https://x.fr/a?b=1&amp;c=2">https://x.fr/a?b=1&amp;c=2</a>.</p>',
            $this->lien->html('<p>Voir https://x.fr/a?b=1&amp;c=2.</p>')
        );
    }

    public function testLienExistantEtAttributsIntacts(): void
    {
        $html = '<p><a href="https://x.fr">https://x.fr</a> <span title="https://y.fr">texte</span></p>';

        self::assertSame($html, $this->lien->html($html));
    }

    public function testTexteEchappePuisLie(): void
    {
        self::assertSame(
            'Page &lt;b&gt; : <a href="https://x.fr/p">https://x.fr/p</a>',
            $this->lien->texte('Page <b> : https://x.fr/p')
        );
    }

    public function testGuillemetsEncodesHorsDuLien(): void
    {
        self::assertSame(
            '<p>&quot;<a href="https://x.fr/p">https://x.fr/p</a>&quot;</p>',
            $this->lien->html('<p>&quot;https://x.fr/p&quot;</p>')
        );
        self::assertSame(
            '&quot;<a href="https://x.fr/p">https://x.fr/p</a>&quot;',
            $this->lien->texte('"https://x.fr/p"')
        );
    }

    public function testHttpNonSecuriseNestPasLie(): void
    {
        self::assertSame('<p>http://x.fr</p>', $this->lien->html('<p>http://x.fr</p>'));
    }
}
