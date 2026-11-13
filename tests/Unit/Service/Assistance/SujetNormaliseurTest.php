<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Service\Assistance\SujetNormaliseur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SujetNormaliseurTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sujets(): iterable
    {
        yield 'réponse simple' => ['Re: Demande de mise à jour des informations du parcours LP GEOTP', 'demande de mise à jour des informations du parcours lp geotp'];
        yield 'préfixes empilés' => ['RE: TR: Re:  Demande de mise à jour des informations du parcours LP GEOTP', 'demande de mise à jour des informations du parcours lp geotp'];
        yield 'transferts' => ['Fwd: Fwd: URGENT : OREOF - Validation offre de formation Odontologie', 'urgent : oreof - validation offre de formation odontologie'];
        yield 'balise ORéOF' => ['[ORéOF] Accès à l\'application', 'accès à l\'application'];
        yield 'numéro de réponse' => ['Re[2]: Accès DPE', 'accès dpe'];
        yield 'mention importante' => ['Re: ***IMPORTANT*** ORéOF - Ouverture de la campagne', 'oréof - ouverture de la campagne'];
        yield 'vide' => ['', ''];
    }

    #[DataProvider('sujets')]
    public function testNormaliser(string $sujet, string $attendu): void
    {
        self::assertSame($attendu, (new SujetNormaliseur())->normaliser($sujet));
    }

    public function testSujetTropLongEstTronque(): void
    {
        self::assertSame(255, mb_strlen((new SujetNormaliseur())->normaliser(str_repeat('é', 300))));
    }
}
