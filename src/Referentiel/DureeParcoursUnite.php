<?php

declare(strict_types=1);

namespace App\Referentiel;

use App\Enums\DureeParcoursUniteEnum;

/**
 * Stable persistence keys for parcours duration units.
 *
 * String enum backed values are preserved; newly configured values
 * use their referential code. No integer coercion is performed for custom keys.
 */
final class DureeParcoursUnite
{
    public static function key(string|DureeParcoursUniteEnum $value): string
    {
        return $value instanceof DureeParcoursUniteEnum
            ? $value->value
            : (string) $value;
    }

    /**
     * Returns a historical enum only when the key matches exactly.
     * Custom codes return null.
     */
    public static function legacyCase(string|DureeParcoursUniteEnum|null $value): ?DureeParcoursUniteEnum
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DureeParcoursUniteEnum) {
            return $value;
        }

        return DureeParcoursUniteEnum::tryFrom((string) $value);
    }
}
