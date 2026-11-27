<?php

declare(strict_types=1);

namespace App\Service\Coherence;

use App\DTO\Coherence\CoherenceIssue;

final class TextQualityDetector
{
    /**
     * Motifs manifestement fictifs, temporaires ou de test.
     *
     * @var list<string>
     */
    private const PLACEHOLDER_PATTERNS = [
        '/\blorem\s+ipsum\b/iu',
        '/\bdolor\s+sit\s+amet\b/iu',
        '/\bconsectetur\s+adipiscing\b/iu',
        '/\b([àa]\s+compl[eé]ter|[àa]\s+r[eé]diger|[àa]\s+d[eé]finir|[àa]\s+pr[eé]ciser|[àa]\s+remplir)\b/iu',
        '/\ben\s+cours\s+de\s+r[eé]daction\b/iu',
        '/\b(blabla+|toto|tata|titi|foobar|foo\s+bar|azerty|qwerty)\b/iu',
        '/\b(xxx+|yyy+|zzz+)\b/iu',
        '/\b(tbd|wip|todo|fixme)\b/iu',
        '/^\s*(test+|essai+|ras|n\/a|na|nc|néant|neant|rien|aucun|aucune|sans\s+objet|non\s+renseign[eé]|idem|cf\.?|ok|\.+|-+|\?+|!+)\s*$/iu',
    ];

    public function htmlToPlainText(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $withBreaks = preg_replace('/<\/(p|div|li|br|h[1-6]|tr)>/iu', "\n", $raw) ?? $raw;
        $stripped = strip_tags($withBreaks);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[ \t]+/u', ' ', $decoded) ?? $decoded;
        $normalized = preg_replace('/\n{3,}/u', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Analyse un champ textuel individuel et retourne les anomalies déterministes détectées.
     *
     * @param list<string> $referenceLabels Libellés (ex: libellé du parcours/formation) pour détecter un champ réduit à une copie du titre
     * @return list<CoherenceIssue>
     */
    public function analyzeField(
        string  $field,
        string  $fieldLabel,
        ?string $rawValue,
        bool    $expectSubstantialText = true,
        int     $minChars = 25,
        int     $minWords = 5,
        array   $referenceLabels = [],
    ): array
    {
        $text = $this->htmlToPlainText($rawValue);
        if ($text === '') {
            return [];
        }

        $issues = [];
        $excerpt = $this->makeExcerpt($text);

        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $issues[] = new CoherenceIssue(
                    field: $field,
                    fieldLabel: $fieldLabel,
                    severity: CoherenceIssue::SEVERITY_ALERT,
                    category: CoherenceIssue::CATEGORY_FICTIF,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: sprintf('Contenu temporaire ou fictif détecté (« %s »).', trim($match[0])),
                    excerpt: $excerpt,
                );

                return $issues;
            }
        }

        // Caractères répétés de façon suspecte (ex: "aaaaaa", "......", "------")
        if (preg_match('/(.)\1{5,}/u', $text) === 1) {
            $issues[] = new CoherenceIssue(
                field: $field,
                fieldLabel: $fieldLabel,
                severity: CoherenceIssue::SEVERITY_ALERT,
                category: CoherenceIssue::CATEGORY_REMPLISSAGE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: 'Suite de caractères répétés détectée (contenu de remplissage probable).',
                excerpt: $excerpt,
            );

            return $issues;
        }

        // Proportion anormalement faible de lettres (ex: suites de chiffres / symboles)
        $charCount = mb_strlen($text);
        preg_match_all('/\p{L}/u', $text, $letters);
        $letterCount = count($letters[0]);
        if ($charCount >= 10 && ($letterCount / $charCount) < 0.45) {
            $issues[] = new CoherenceIssue(
                field: $field,
                fieldLabel: $fieldLabel,
                severity: CoherenceIssue::SEVERITY_ALERT,
                category: CoherenceIssue::CATEGORY_REMPLISSAGE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: 'Le texte contient très peu de lettres exploitables (ponctuation ou chiffres excessifs).',
                excerpt: $excerpt,
            );

            return $issues;
        }

        // Champ identique au titre du parcours / de la formation
        if ($expectSubstantialText) {
            $normalizedText = $this->normalizeComparable($text);
            foreach ($referenceLabels as $ref) {
                $normalizedRef = $this->normalizeComparable($ref);
                if ($normalizedRef !== '' && $normalizedText === $normalizedRef) {
                    $issues[] = new CoherenceIssue(
                        field: $field,
                        fieldLabel: $fieldLabel,
                        severity: CoherenceIssue::SEVERITY_WARNING,
                        category: CoherenceIssue::CATEGORY_REMPLISSAGE,
                        source: CoherenceIssue::SOURCE_DETERMINISTIC,
                        message: 'Le champ se limite à recopier l\'intitulé sans réelle description.',
                        excerpt: $excerpt,
                    );

                    return $issues;
                }
            }
        }

        // Densité et longueur minimale sur les champs descriptifs attendus rédigés
        if ($expectSubstantialText) {
            $words = $this->extractWords($text);
            $wordCount = count($words);
            $uniqueWords = count(array_unique($words));

            if ($charCount < $minChars || $wordCount < $minWords) {
                $issues[] = new CoherenceIssue(
                    field: $field,
                    fieldLabel: $fieldLabel,
                    severity: CoherenceIssue::SEVERITY_WARNING,
                    category: CoherenceIssue::CATEGORY_REMPLISSAGE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: sprintf(
                        'Texte trop succinct (%d caractère%s, %d mot%s) pour être informatif.',
                        $charCount,
                        $charCount > 1 ? 's' : '',
                        $wordCount,
                        $wordCount > 1 ? 's' : ''
                    ),
                    excerpt: $excerpt,
                );

                return $issues;
            }

            // Répétition excessive du même mot (ex: "formation formation formation formation...")
            if ($wordCount >= 8 && ($uniqueWords / $wordCount) < 0.30) {
                $issues[] = new CoherenceIssue(
                    field: $field,
                    fieldLabel: $fieldLabel,
                    severity: CoherenceIssue::SEVERITY_ALERT,
                    category: CoherenceIssue::CATEGORY_REMPLISSAGE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: 'Diversité lexicale anormalement faible (répétition suspecte de mots).',
                    excerpt: $excerpt,
                );
            }
        }

        return $issues;
    }

    /**
     * Détecte les champs copiés-collés à l'identique entre eux (ex: objectifs == contenu == débouchés).
     *
     * @param array<string, array{label: string, text: string}> $fields
     * @return list<CoherenceIssue>
     */
    public function detectDuplicateFields(array $fields): array
    {
        $seen = [];
        $issues = [];

        foreach ($fields as $fieldKey => $info) {
            $normalized = $this->normalizeComparable($info['text']);
            if (mb_strlen($normalized) < 25) {
                continue;
            }

            if (isset($seen[$normalized])) {
                $first = $seen[$normalized];
                $issues[] = new CoherenceIssue(
                    field: $fieldKey,
                    fieldLabel: $info['label'],
                    severity: CoherenceIssue::SEVERITY_WARNING,
                    category: CoherenceIssue::CATEGORY_REMPLISSAGE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: sprintf(
                        'Contenu strictement identique au champ « %s » (copier-coller probable).',
                        $first['label']
                    ),
                    excerpt: $this->makeExcerpt($info['text']),
                );
            } else {
                $seen[$normalized] = ['key' => $fieldKey, 'label' => $info['label']];
            }
        }

        return $issues;
    }

    public function makeExcerpt(string $text, int $maxLength = 140): string
    {
        $singleLine = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if (mb_strlen($singleLine) <= $maxLength) {
            return $singleLine;
        }

        return mb_substr($singleLine, 0, $maxLength) . '…';
    }

    private function normalizeComparable(string $text): string
    {
        $lower = mb_strtolower(trim($text));
        $collapsed = preg_replace('/\s+/u', ' ', $lower) ?? $lower;

        return trim($collapsed);
    }

    /**
     * @return list<string>
     */
    private function extractWords(string $text): array
    {
        preg_match_all('/\p{L}[\p{L}\p{N}\'-]*/u', mb_strtolower($text), $matches);

        return $matches[0];
    }
}
