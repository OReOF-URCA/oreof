<?php

declare(strict_types=1);

namespace App\Workflow\Validator;

/**
 * Résultat d'une validation d'étape de workflow.
 *
 * Contient les erreurs bloquantes et les avertissements non-bloquants.
 * La validation est considérée comme réussie uniquement si aucune erreur n'est présente.
 */
final class ValidationResult
{
    /**
     * @param bool $isValid Indique si la validation est réussie
     * @param array<ValidationError> $errors Liste des erreurs bloquantes
     * @param array<ValidationWarning> $warnings Liste des avertissements non-bloquants
     * @param array<ValidationCheck> $checks Groupes de contrôles exécutés
     */
    public function __construct(
        private readonly bool  $isValid,
        private readonly array $errors = [],
        private readonly array $warnings = [],
        private readonly array $checks = [],
    )
    {
    }

    /**
     * Crée un résultat de validation réussi.
     *
     * @param array<ValidationWarning> $warnings Avertissements optionnels
     */
    public static function success(array $warnings = [], array $checks = []): self
    {
        return new self(true, [], $warnings, $checks);
    }

    /**
     * Crée un résultat de validation en échec.
     *
     * @param array<ValidationError> $errors Liste des erreurs
     * @param array<ValidationWarning> $warnings Avertissements optionnels
     */
    public static function failure(array $errors, array $warnings = [], array $checks = []): self
    {
        return new self(false, $errors, $warnings, $checks);
    }

    /**
     * Indique s'il y a des erreurs.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Indique s'il y a des avertissements.
     */
    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    /**
     * Retourne le nombre d'erreurs.
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Retourne le nombre d'avertissements.
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Fusionne ce résultat avec un autre.
     *
     * Le résultat fusionné est valide uniquement si les deux résultats sont valides.
     */
    public function merge(ValidationResult $other): self
    {
        return new self(
            $this->isValid && $other->isValid(),
            array_merge($this->errors, $other->getErrors()),
            array_merge($this->warnings, $other->getWarnings()),
            array_merge($this->checks, $other->getChecks()),
        );
    }

    /**
     * Indique si la validation est réussie (aucune erreur).
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Retourne la liste des erreurs bloquantes.
     *
     * @return array<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retourne la liste des avertissements non-bloquants.
     *
     * @return array<ValidationWarning>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /** @return array<ValidationCheck> */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * Convertit le résultat en tableau pour sérialisation.
     *
     * @return array{isValid: bool, errors: array, warnings: array, checks: array}
     */
    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'errors' => array_map(fn(ValidationError $e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn(ValidationWarning $w) => $w->toArray(), $this->warnings),
            'checks' => array_map(fn(ValidationCheck $check) => $check->toArray(), $this->checks),
        ];
    }
}
