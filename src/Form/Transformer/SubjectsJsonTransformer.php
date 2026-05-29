<?php
declare(strict_types=1);

namespace App\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

final class SubjectsJsonTransformer implements DataTransformerInterface
{
    /**
     * Transforme l'array (model) vers string JSON (view).
     * @param array<string,string>|null $value
     */
    public function transform($value): string
    {
        if (!is_array($value) || $value === []) {
            return "{}";
        }
        // Conserve l'ordre par clé pour stabilité visuelle
        ksort($value);
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Transforme le JSON (view) vers array<string,string> (model).
     * @return array<string,string>
     */
    public function reverseTransform($value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            // Au cas où quelqu'un nous passe déjà un array
            return $this->filterStrings($value);
        }

        $str = trim((string)$value);
        if ($str === '') {
            return [];
        }

        $decoded = json_decode($str, true);
        if (!is_array($decoded)) {
            // on lève une exception pour que le form marque une erreur de validation
            throw new \UnexpectedValueException('Le JSON fourni n’est pas un objet valide.');
        }

        return $this->filterStrings($decoded);
    }

    /**
     * Garde uniquement les paires clé=>string
     * @param array<string,mixed> $in
     * @return array<string,string>
     */
    private function filterStrings(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
