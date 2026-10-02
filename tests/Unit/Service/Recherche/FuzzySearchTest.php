<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Recherche;

use App\DTO\Recherche\DocumentRecherche;
use App\Service\Recherche\FuzzyMatcher;
use App\Service\Recherche\FuzzySearch;
use PHPUnit\Framework\TestCase;

class FuzzySearchTest extends TestCase
{
    private FuzzySearch $search;

    protected function setUp(): void
    {
        $this->search = new FuzzySearch(new FuzzyMatcher());
    }

    public function testRechercheUnTermeAvecFauteEtSignaleLaCorrection(): void
    {
        $resultats = $this->search->rechercher('matematiques', $this->documents());

        $this->assertSame([2], $resultats->ids());
        $this->assertSame(['matematiques' => 'mathématiques'], $resultats->corrections);
        $this->assertSame([], $resultats->termesSansCorrespondance);
    }

    public function testRechercheExigeTousLesTermesDeLaRequete(): void
    {
        $documents = $this->documents();

        // Le document 1 contient les deux termes, le document 3 seulement « droit ».
        $this->assertSame([1], $this->search->rechercher('droit environnement', $documents)->ids());
        $this->assertSame([3], $this->search->rechercher('droit histoire', $documents)->ids());
    }

    public function testRechercheSignaleLesTermesSansCorrespondance(): void
    {
        $resultats = $this->search->rechercher('zzzz', $this->documents());

        $this->assertSame([], $resultats->ids());
        $this->assertSame(['zzzz'], $resultats->termesSansCorrespondance);
        $this->assertSame([], $resultats->corrections);
    }

    public function testRechercheClasseParPoidsDeChamp(): void
    {
        $documents = [
            new DocumentRecherche(10, ['titre' => 'Algorithmique', 'contenu' => 'Cours avancé de compilation.']),
            new DocumentRecherche(11, ['titre' => 'Cours', 'contenu' => 'Algorithmique de base et compilation.']),
        ];

        $resultats = $this->search->rechercher('algorithmique', $documents, ['titre' => 3.0, 'contenu' => 1.0]);

        $this->assertSame([10, 11], $resultats->ids());
        $this->assertSame(['titre'], $resultats->resultats[0]->champs);
        $this->assertSame(['contenu'], $resultats->resultats[1]->champs);
        $this->assertGreaterThan($resultats->resultats[1]->score, $resultats->resultats[0]->score);
    }

    public function testRechercheNIndexePasLesBalisesHtml(): void
    {
        // Le document 4 ne contient que « <strong>texte</strong> » : le nom de la balise n'est pas indexé.
        $resultats = $this->search->rechercher('strong', $this->documents());

        $this->assertSame([], $resultats->ids());
        $this->assertSame(['strong'], $resultats->termesSansCorrespondance);
    }

    public function testResultatListeLesChampsAyantUnTerme(): void
    {
        $resultats = $this->search->rechercher('environnement', $this->documents());

        $this->assertSame([1], $resultats->ids());
        $this->assertSame(['contenu'], $resultats->resultats[0]->champs);
    }

    public function testExtraitSurligneChaqueMotTrouveDansLeChampLePlusPertinent(): void
    {
        $resultats = $this->search->rechercher('droit environnement', $this->documents());
        $resultat = $resultats->resultats[0];

        $this->assertSame('contenu', $resultat->champExtrait);
        $this->assertSame([
            ['texte' => 'Présentation du ', 'surligne' => false],
            ['texte' => 'droit', 'surligne' => true],
            ['texte' => ' de l\'', 'surligne' => false],
            ['texte' => 'environnement', 'surligne' => true],
            ['texte' => ' et de la propriété intellectuelle.', 'surligne' => false],
        ], $resultat->extrait);
    }

    public function testExtraitConserveLaFormeOrigineDuMotTrouve(): void
    {
        $resultats = $this->search->rechercher('economie', $this->documentsAvecAccents());
        $resultat = $resultats->resultats[0];

        $this->assertSame('titre', $resultat->champExtrait);
        $this->assertSame([
            ['texte' => 'Économie', 'surligne' => true],
            ['texte' => ' de l\'entreprise', 'surligne' => false],
        ], $resultat->extrait);
    }

    public function testExtraitIgnoreLesChampsDeclaresSansExtrait(): void
    {
        $documents = $this->documentsAvecAccents();

        $resultats = $this->search->rechercher('economie', $documents, [], ['titre']);
        $resultat = $resultats->resultats[0];

        $this->assertSame('contenu', $resultat->champExtrait);
        $this->assertSame([
            ['texte' => 'Ce cours d\'', 'surligne' => false],
            ['texte' => 'économie', 'surligne' => true],
            ['texte' => ' décrit les marchés et les entreprises en détail.', 'surligne' => false],
        ], $resultat->extrait);
    }

    public function testRechercheSansExtraitsRenvoieUnExtraitVide(): void
    {
        $resultats = $this->search->rechercher('economie', $this->documentsAvecAccents(), [], [], false);
        $resultat = $resultats->resultats[0];

        $this->assertSame([20], $resultats->ids());
        $this->assertSame([], $resultat->extrait);
        $this->assertNull($resultat->champExtrait);
        $this->assertSame(['titre', 'contenu'], $resultat->champs);
    }

    public function testRequeteVideRenvoieAucunResultat(): void
    {
        $resultats = $this->search->rechercher('   ', $this->documents());

        $this->assertSame([], $resultats->ids());
        $this->assertSame([], $resultats->corrections);
        $this->assertSame([], $resultats->termesSansCorrespondance);
    }

    public function testListeDeDocumentsVideRenvoieAucunResultat(): void
    {
        $resultats = $this->search->rechercher('droit', []);

        $this->assertSame([], $resultats->ids());
        $this->assertSame([], $resultats->corrections);
        $this->assertSame([], $resultats->termesSansCorrespondance);
    }

    /**
     * @return list<DocumentRecherche>
     */
    private function documents(): array
    {
        return [
            new DocumentRecherche(1, [
                'titre' => 'Licence Droit',
                'contenu' => 'Présentation du droit de l\'environnement et de la propriété intellectuelle.',
            ]),
            new DocumentRecherche(2, [
                'titre' => 'Licence Mathématiques',
                'contenu' => 'UE de mathématiques discrètes et d\'analyse.',
            ]),
            new DocumentRecherche(3, [
                'titre' => 'Droit public',
                'contenu' => 'Droit constitutionnel et histoire des institutions.',
            ]),
            new DocumentRecherche(4, [
                'titre' => 'Bloc fort',
                'contenu' => '<strong>texte</strong>',
            ]),
        ];
    }

    /**
     * @return list<DocumentRecherche>
     */
    private function documentsAvecAccents(): array
    {
        return [
            new DocumentRecherche(20, [
                'titre' => 'Économie de l\'entreprise',
                'contenu' => 'Ce cours d\'économie décrit les marchés et les entreprises en détail.',
            ]),
        ];
    }
}
