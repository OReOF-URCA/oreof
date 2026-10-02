<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Recherche;

use App\Service\Recherche\FuzzyMatcher;
use PHPUnit\Framework\TestCase;

class FuzzyMatcherTest extends TestCase
{
    private FuzzyMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new FuzzyMatcher();
    }

    public function testTexteBrutRetireBalisesDecodeEntitesEtCompacteEspaces(): void
    {
        $this->assertSame('Hello & world', $this->matcher->texteBrut('<p>Hello&nbsp;&amp;&nbsp;world</p>'));
        $this->assertSame('Découvrir le droit', $this->matcher->texteBrut('<div>D&eacute;couvrir&nbsp;le droit</div>'));
        $this->assertSame('espaces multiples ici', $this->matcher->texteBrut("<div>  espaces   multiples\n\t ici </div>"));
        $this->assertSame('a b', $this->matcher->texteBrut('<div>a<br>b</div>'));
    }

    public function testTexteBrutRenvoieChaineVidePourNullOuChaineVide(): void
    {
        $this->assertSame('', $this->matcher->texteBrut(null));
        $this->assertSame('', $this->matcher->texteBrut(''));
    }

    public function testNormaliserMinusculesEtSupprimeAccentsEtLigatures(): void
    {
        $this->assertSame('economie', $this->matcher->normaliser('Économie'));
        $this->assertSame('oeuvre', $this->matcher->normaliser('Œuvre'));
        $this->assertSame('ecole', $this->matcher->normaliser('ÉCOLE'));
    }

    public function testNormaliserMinusculesLesMotsSansAccent(): void
    {
        $this->assertSame('abc', $this->matcher->normaliser('ABC'));
    }

    public function testTermesRequeteRetireLesMotsVides(): void
    {
        $this->assertSame(['droit', 'environnement'], $this->matcher->termesRequete('le droit de l\'environnement'));
    }

    public function testTermesRequeteNeGardeAucunTermeSiUniquementDesMotsVides(): void
    {
        $this->assertSame([], $this->matcher->termesRequete('de la'));
    }

    public function testTermesRequeteLimiteLeNombreDeTermes(): void
    {
        $requete = implode(' ', array_map(static fn (int $i): string => 'mot' . $i, range(1, 15)));

        $this->assertCount(FuzzyMatcher::TERMES_MAX, $this->matcher->termesRequete($requete));
    }

    public function testTermesRequeteRetireLesDoublons(): void
    {
        $this->assertSame(['droit'], $this->matcher->termesRequete('Droit droit'));
    }

    public function testDistanceMaxCroissantAvecLaLongueurDuTerme(): void
    {
        $this->assertSame(0, $this->matcher->distanceMax(3));
        $this->assertSame(1, $this->matcher->distanceMax(4));
        $this->assertSame(1, $this->matcher->distanceMax(7));
        $this->assertSame(2, $this->matcher->distanceMax(8));
    }

    public function testRapprocherScoreExactPourUnMotIdentique(): void
    {
        $proches = $this->matcher->rapprocher('droit', ['droit']);

        $this->assertSame(['droit'], array_keys($proches));
        $this->assertEqualsWithDelta(FuzzyMatcher::SCORE_EXACT, $proches['droit'], 0.0001);
    }

    public function testRapprocherScorePrefixePourUnMotCommencantParLeTerme(): void
    {
        $proches = $this->matcher->rapprocher('informat', ['informatique']);

        $this->assertArrayHasKey('informatique', $proches);
        $this->assertEqualsWithDelta(FuzzyMatcher::SCORE_PREFIXE, $proches['informatique'], 0.0001);
    }

    public function testRapprocherTolerateOneTypo(): void
    {
        $proches = $this->matcher->rapprocher('psycologie', ['psychologie']);

        $this->assertArrayHasKey('psychologie', $proches);
        $this->assertGreaterThan(0.0, $proches['psychologie']);
        $this->assertLessThan(FuzzyMatcher::SCORE_PREFIXE, $proches['psychologie']);
    }

    public function testRapprocherTolererLInversionDeDeuxLettres(): void
    {
        $proches = $this->matcher->rapprocher('algorihtmique', ['algorithmique']);

        $this->assertArrayHasKey('algorithmique', $proches);
        $this->assertGreaterThan(0.0, $proches['algorithmique']);
        $this->assertLessThan(FuzzyMatcher::SCORE_PREFIXE, $proches['algorithmique']);
    }

    public function testRapprocherIgnoreUnTermeDeTroisLettresSansTolerance(): void
    {
        $this->assertSame([], $this->matcher->rapprocher('dro', ['dru']));
    }

    public function testRapprocherIgnoreUnMotTropEloigne(): void
    {
        $this->assertSame([], $this->matcher->rapprocher('chimie', ['histoire']));
    }

    public function testRapprocherTrieParSimilariteDecroissante(): void
    {
        $proches = $this->matcher->rapprocher('histoire', ['chimie', 'historiographie', 'histoires', 'histoire']);

        $this->assertSame(['histoire', 'histoires', 'historiographie'], array_keys($proches));
        $this->assertEqualsWithDelta(FuzzyMatcher::SCORE_EXACT, $proches['histoire'], 0.0001);
        $this->assertEqualsWithDelta(FuzzyMatcher::SCORE_PREFIXE, $proches['histoires'], 0.0001);
        $this->assertLessThan($proches['histoires'], $proches['historiographie']);
    }
}
