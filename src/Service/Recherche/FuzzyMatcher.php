<?php

declare(strict_types=1);

namespace App\Service\Recherche;

/**
 * Comparaison approchée de mots : normalisation (minuscules, sans accents), découpage en mots et
 * rapprochement d'un terme saisi avec un vocabulaire (exact, préfixe, fautes de frappe).
 */
final class FuzzyMatcher
{
    public const SCORE_EXACT = 1.0;
    public const SCORE_PREFIXE = 0.8;
    public const TERMES_MAX = 10;

    private const STOPWORDS = [
        'a', 'au', 'aux', 'avec', 'ce', 'ces', 'd', 'dans', 'de', 'des', 'du', 'en', 'et', 'l', 'la', 'le', 'les',
        'leur', 'leurs', 'ou', 'par', 'pour', 'qu', 'que', 'qui', 'sa', 'se', 'ses', 'son', 'sur', 'un', 'une',
    ];

    /** @var array<string, string> */
    private array $cacheNormalisation = [];

    private ?\Transliterator $transliterator = null;

    /**
     * Texte brut à partir d'un contenu HTML (Trix) : balises retirées, entités décodées, espaces compactés.
     */
    public function texteBrut(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $texte = strip_tags(str_replace(['<', '&nbsp;'], [' <', ' '], $html));
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $texte));
    }

    public function normaliser(string $mot): string
    {
        if (isset($this->cacheNormalisation[$mot])) {
            return $this->cacheNormalisation[$mot];
        }

        if (preg_match('/^[\x00-\x7F]*$/', $mot) === 1) {
            $normalise = strtolower($mot);
        } else {
            $this->transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            $resultat = $this->transliterator?->transliterate($mot);
            $normalise = is_string($resultat) ? $resultat : mb_strtolower($mot);
        }

        return $this->cacheNormalisation[$mot] = $normalise;
    }

    /**
     * Mots du texte avec leur position (en octets) dans le texte.
     *
     * @return list<array{0: string, 1: int}>
     */
    public function decouper(string $texte): array
    {
        if ($texte === '' || preg_match_all('/[\p{L}\p{N}]+/u', $texte, $correspondances, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        return $correspondances[0];
    }

    /**
     * Termes significatifs d'une requête (normalisés, sans doublon, TERMES_MAX au plus, mots vides retirés).
     * Une requête composée uniquement de mots vides (« de la ») ne donne aucun terme.
     *
     * @return list<string>
     */
    public function termesRequete(string $requete): array
    {
        $termes = [];
        foreach ($this->decouper($requete) as [$mot]) {
            $termes[] = $this->normaliser($mot);
        }
        $significatifs = array_filter(
            array_unique($termes),
            static fn (string $terme): bool => !in_array($terme, self::STOPWORDS, true)
        );

        return array_slice(array_values($significatifs), 0, self::TERMES_MAX);
    }

    /**
     * Nombre de fautes tolérées selon la longueur du terme.
     */
    public function distanceMax(int $longueur): int
    {
        return match (true) {
            $longueur < 4 => 0,
            $longueur < 8 => 1,
            default => 2,
        };
    }

    /**
     * Mots du vocabulaire proches du terme, avec leur similarité (0 < s <= 1).
     *
     * @param iterable<string> $vocabulaire mots normalisés
     *
     * @return array<string, float>
     */
    public function rapprocher(string $terme, iterable $vocabulaire): array
    {
        $longueurTerme = strlen($terme);
        $distanceMax = $this->distanceMax($longueurTerme);
        $proches = [];

        foreach ($vocabulaire as $mot) {
            $mot = (string) $mot;
            $similarite = $this->similarite($terme, $longueurTerme, $distanceMax, $mot);
            if ($similarite > 0.0) {
                $proches[$mot] = $similarite;
            }
        }

        arsort($proches);

        return $proches;
    }

    private function similarite(string $terme, int $longueurTerme, int $distanceMax, string $mot): float
    {
        if ($mot === $terme) {
            return self::SCORE_EXACT;
        }

        $longueurMot = strlen($mot);
        $meilleur = 0.0;

        if ($longueurTerme >= 3 && $longueurMot > $longueurTerme && str_starts_with($mot, $terme)) {
            $meilleur = self::SCORE_PREFIXE;
        }

        if ($distanceMax === 0) {
            return $meilleur;
        }

        if (abs($longueurMot - $longueurTerme) <= $distanceMax) {
            $distance = $this->distance($terme, $mot, $distanceMax);
            if ($distance <= $distanceMax) {
                $meilleur = max($meilleur, 0.95 - 0.2 * $distance);
            }
        }

        // Début de mot mal orthographié (« mathematiq » → « mathématiques »).
        if ($meilleur < 0.6 && $longueurTerme >= 5 && $longueurMot > $longueurTerme) {
            $distance = $this->distance($terme, substr($mot, 0, $longueurTerme), $distanceMax);
            if ($distance > 0 && $distance <= $distanceMax) {
                $meilleur = max($meilleur, 0.7 - 0.15 * $distance);
            }
        }

        return $meilleur;
    }

    /**
     * Distance de Levenshtein, où l'inversion de deux lettres voisines ne compte que pour une faute.
     */
    private function distance(string $a, string $b, int $distanceMax): int
    {
        $distance = levenshtein($a, $b);
        if ($distance < 2 || $distance > $distanceMax + 1) {
            return $distance;
        }

        return $this->distanceAvecInversions($a, $b);
    }

    private function distanceAvecInversions(string $a, string $b): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        $d = [];
        for ($i = 0; $i <= $la; ++$i) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lb; ++$j) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $la; ++$i) {
            for ($j = 1; $j <= $lb; ++$j) {
                $cout = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cout);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$la][$lb];
    }
}
