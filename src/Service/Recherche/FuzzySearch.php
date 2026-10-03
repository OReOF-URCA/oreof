<?php

declare(strict_types=1);

namespace App\Service\Recherche;

use App\DTO\Recherche\DocumentRecherche;
use App\DTO\Recherche\ResultatFuzzy;
use App\DTO\Recherche\ResultatsFuzzy;

/**
 * Recherche approchée en mémoire sur une liste de documents.
 *
 * Un document est retenu s'il contient, pour chaque terme de la requête, un mot proche (exact, préfixe ou à une/deux
 * fautes près). Le score cumule la meilleure similarité de chaque terme pondérée par le champ, un bonus si les termes
 * sont proches les uns des autres et un léger bonus de fréquence.
 */
final class FuzzySearch
{
    private const MOTS_AVANT_EXTRAIT = 12;
    private const MOTS_EXTRAIT = 40;
    private const FENETRE_PROXIMITE = 4;

    public function __construct(private readonly FuzzyMatcher $matcher)
    {
    }

    /**
     * @param list<DocumentRecherche> $documents
     * @param array<string, float>    $poids             poids par champ (1.0 par défaut)
     * @param list<string>            $champsSansExtrait champs jamais utilisés comme extrait (ex. titre déjà affiché)
     * @param bool                    $avecExtraits      false si seuls les identifiants trouvés sont utiles
     */
    public function rechercher(
        string $requete,
        array $documents,
        array $poids = [],
        array $champsSansExtrait = [],
        bool $avecExtraits = true,
    ): ResultatsFuzzy {
        $termes = $this->matcher->termesRequete($requete);
        if ($termes === [] || $documents === []) {
            return new ResultatsFuzzy();
        }

        [$frequences, $formes, $motsNormalises] = $this->vocabulaire($documents);

        $rapprochements = [];
        foreach ($termes as $terme) {
            $rapprochements[$terme] = $this->matcher->rapprocher($terme, array_keys($frequences));
        }

        [$corrections, $sansCorrespondance] = $this->corrections($rapprochements, $frequences, $formes);
        if ($sansCorrespondance !== []) {
            // Tous les termes doivent être trouvés : inutile de parcourir les documents.
            return new ResultatsFuzzy([], $corrections, $sansCorrespondance);
        }

        $resultats = [];
        foreach ($documents as $index => $document) {
            $resultat = $this->evaluer(
                $document,
                $motsNormalises[$index],
                $termes,
                $rapprochements,
                $poids,
                $avecExtraits ? $champsSansExtrait : null,
            );
            if ($resultat !== null) {
                $resultats[] = $resultat;
            }
        }

        usort($resultats, static fn (ResultatFuzzy $a, ResultatFuzzy $b): int => $b->score <=> $a->score);

        return new ResultatsFuzzy($resultats, $corrections, $sansCorrespondance);
    }

    /**
     * @param list<DocumentRecherche> $documents
     *
     * @return array{0: array<string, int>, 1: array<string, string>, 2: array<int, array<string, list<string>>>}
     *     fréquence et forme affichable de chaque mot, mots normalisés de chaque champ de chaque document
     */
    private function vocabulaire(array $documents): array
    {
        $frequences = [];
        $formes = [];
        $motsNormalises = [];
        foreach ($documents as $index => $document) {
            foreach ($document->champs as $champ => $contenu) {
                $normalises = [];
                foreach ($this->matcher->decouper($this->matcher->texteBrut($contenu)) as [$mot]) {
                    $normalise = $this->matcher->normaliser($mot);
                    $normalises[] = $normalise;
                    if (isset($frequences[$normalise])) {
                        ++$frequences[$normalise];
                    } else {
                        $frequences[$normalise] = 1;
                        $formes[$normalise] = mb_strtolower($mot);
                    }
                }
                $motsNormalises[$index][(string) $champ] = $normalises;
            }
        }

        return [$frequences, $formes, $motsNormalises];
    }

    /**
     * @param array<string, list<string>>         $motsNormalises    mots normalisés de chaque champ du document
     * @param list<string>                        $termes
     * @param array<string, array<string, float>> $rapprochements
     * @param array<string, float>                $poids
     * @param list<string>|null                   $champsSansExtrait null : pas d'extrait
     */
    private function evaluer(
        DocumentRecherche $document,
        array $motsNormalises,
        array $termes,
        array $rapprochements,
        array $poids,
        ?array $champsSansExtrait,
    ): ?ResultatFuzzy {
        $meilleurParTerme = array_fill_keys($termes, 0.0);
        $occurrences = 0;
        $bonusProximite = 0.0;
        /** @var array<string, array<int, string>> $champsTrouves position du mot trouvé => terme correspondant */
        $champsTrouves = [];

        foreach ($motsNormalises as $champ => $normalises) {
            $poidsChamp = $poids[$champ] ?? 1.0;
            $positionsParTerme = [];
            $trouves = [];

            foreach ($normalises as $index => $normalise) {
                foreach ($termes as $terme) {
                    $similarite = $rapprochements[$terme][$normalise] ?? null;
                    if ($similarite === null) {
                        continue;
                    }
                    $meilleurParTerme[$terme] = max($meilleurParTerme[$terme], $similarite * $poidsChamp);
                    $positionsParTerme[$terme][] = $index;
                    $trouves[$index] ??= $terme;
                    ++$occurrences;
                }
            }

            if ($trouves === []) {
                continue;
            }

            $champsTrouves[$champ] = $trouves;

            if (count($termes) > 1 && $this->termesProches($positionsParTerme, count($termes))) {
                $bonusProximite = max($bonusProximite, $poidsChamp);
            }
        }

        if (in_array(0.0, $meilleurParTerme, true)) {
            return null;
        }

        $score = array_sum($meilleurParTerme) + $bonusProximite + 0.1 * log(1 + $occurrences);
        [$champExtrait, $extrait] = $champsSansExtrait === null
            ? [null, []]
            : $this->extrait($document, $champsTrouves, $poids, $champsSansExtrait);

        return new ResultatFuzzy(
            $document->id,
            round($score, 4),
            array_map('strval', array_keys($champsTrouves)),
            $extrait,
            $champExtrait,
        );
    }

    /**
     * Vrai si tous les termes apparaissent dans une même fenêtre de quelques mots.
     *
     * @param array<string, list<int>> $positionsParTerme
     */
    private function termesProches(array $positionsParTerme, int $nombreTermes): bool
    {
        if (count($positionsParTerme) < $nombreTermes) {
            return false;
        }

        $listes = array_values($positionsParTerme);
        $fenetre = $nombreTermes + self::FENETRE_PROXIMITE;
        foreach ($listes[0] as $position) {
            foreach (array_slice($listes, 1) as $autres) {
                $proche = false;
                foreach ($autres as $autre) {
                    if (abs($autre - $position) <= $fenetre) {
                        $proche = true;
                        break;
                    }
                }
                if (!$proche) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Passage du champ le plus pertinent (celui qui réunit le plus de termes), découpé en segments à surligner.
     *
     * @param array<string, array<int, string>> $champsTrouves
     * @param array<string, float>              $poids
     * @param list<string>                      $champsSansExtrait
     *
     * @return array{0: ?string, 1: list<array{texte: string, surligne: bool}>}
     */
    private function extrait(DocumentRecherche $document, array $champsTrouves, array $poids, array $champsSansExtrait): array
    {
        $champRetenu = null;
        $meilleur = -1.0;
        foreach ($champsTrouves as $champ => $trouves) {
            if (in_array($champ, $champsSansExtrait, true)) {
                continue;
            }
            $valeur = count(array_unique($trouves)) * ($poids[$champ] ?? 1.0);
            if ($valeur > $meilleur) {
                $meilleur = $valeur;
                $champRetenu = (string) $champ;
            }
        }

        if ($champRetenu === null) {
            return [null, []];
        }

        $trouves = $champsTrouves[$champRetenu];
        $texte = $this->matcher->texteBrut($document->champs[$champRetenu] ?? null);
        $mots = $this->matcher->decouper($texte);

        $debut = max(0, $this->debutFenetre($trouves) - self::MOTS_AVANT_EXTRAIT);
        $fin = min(count($mots) - 1, $debut + self::MOTS_EXTRAIT - 1);

        $segments = [];
        $curseur = $debut > 0 ? $mots[$debut][1] : 0;
        if ($debut > 0) {
            $segments[] = ['texte' => '… ', 'surligne' => false];
        }

        for ($index = $debut; $index <= $fin; ++$index) {
            if (!isset($trouves[$index])) {
                continue;
            }
            [$mot, $position] = $mots[$index];
            if ($position > $curseur) {
                $segments[] = ['texte' => substr($texte, $curseur, $position - $curseur), 'surligne' => false];
            }
            $segments[] = ['texte' => $mot, 'surligne' => true];
            $curseur = $position + strlen($mot);
        }

        $finTexte = $fin < count($mots) - 1 ? $mots[$fin][1] + strlen($mots[$fin][0]) : strlen($texte);
        if ($finTexte > $curseur) {
            $segments[] = ['texte' => substr($texte, $curseur, $finTexte - $curseur), 'surligne' => false];
        }
        if ($fin < count($mots) - 1) {
            $segments[] = ['texte' => ' …', 'surligne' => false];
        }

        return [$champRetenu, $segments];
    }

    /**
     * Position du mot trouvé à partir duquel l'extrait réunit le plus de termes différents.
     *
     * @param array<int, string> $trouves position => terme
     */
    private function debutFenetre(array $trouves): int
    {
        $positions = array_keys($trouves);
        $meilleurePosition = $positions[0];
        $meilleurCompte = 0;
        foreach ($positions as $position) {
            $termes = [];
            foreach ($trouves as $autre => $terme) {
                if ($autre >= $position && $autre < $position + self::MOTS_EXTRAIT - self::MOTS_AVANT_EXTRAIT) {
                    $termes[$terme] = true;
                }
            }
            if (count($termes) > $meilleurCompte) {
                $meilleurCompte = count($termes);
                $meilleurePosition = $position;
            }
        }

        return $meilleurePosition;
    }

    /**
     * Termes sans mot exact ni préfixe dans les textes : on signale le mot proche retenu (le plus fréquent).
     *
     * @param array<string, array<string, float>> $rapprochements
     * @param array<string, int>                  $frequences
     * @param array<string, string>               $formes
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function corrections(array $rapprochements, array $frequences, array $formes): array
    {
        $corrections = [];
        $sansCorrespondance = [];

        foreach ($rapprochements as $terme => $proches) {
            $terme = (string) $terme;
            if ($proches === []) {
                $sansCorrespondance[] = $terme;
                continue;
            }
            if (max($proches) >= FuzzyMatcher::SCORE_PREFIXE) {
                continue;
            }

            $retenu = null;
            foreach ($proches as $mot => $similarite) {
                $mot = (string) $mot;
                if ($retenu === null
                    || $similarite > $proches[$retenu]
                    || ($similarite === $proches[$retenu] && $frequences[$mot] > $frequences[$retenu])) {
                    $retenu = $mot;
                }
            }

            $corrections[$terme] = $formes[$retenu];
        }

        return [$corrections, $sansCorrespondance];
    }
}
