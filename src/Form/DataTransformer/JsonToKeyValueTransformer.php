<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforme un tableau JSON en chaîne JSON pour l'affichage et inversement.
 */
class JsonToKeyValueTransformer implements DataTransformerInterface
{
    /**
     * Transforme un tableau JSON en chaîne JSON pour l'affichage.
     *
     * @param array|null $value
     * @return string
     */
    public function transform($value): string
    {
        if (null === $value || [] === $value) {
            return '';
        }

        if (!is_array($value)) {
            // Si c'est déjà une chaîne, la retourner telle quelle
            if (is_string($value)) {
                return $value;
            }
            throw new TransformationFailedException('Expected an array or string.');
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Transforme une chaîne JSON en tableau pour la sauvegarde.
     *
     * @param string|null $value
     * @return array
     */
    public function reverseTransform($value): array
    {
        // Si vide, retourner un tableau vide
        if (empty($value) || $value === null) {
            return [];
        }

        // Si ce n'est pas une chaîne, erreur
        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        // Nettoyer la chaîne
        $value = trim($value);

        // Si la chaîne est vide après trim
        if ($value === '') {
            return [];
        }

        // Tenter de décoder le JSON
        $decoded = json_decode($value, true);

        // Vérifier les erreurs de décodage
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TransformationFailedException(
                sprintf('Invalid JSON: %s', json_last_error_msg())
            );
        }

        // S'assurer que c'est un tableau (pas null)
        if (!is_array($decoded)) {
            // Si le JSON décodé n'est pas un tableau, le transformer en tableau
            return [$decoded];
        }

        return $decoded;
    }
}
