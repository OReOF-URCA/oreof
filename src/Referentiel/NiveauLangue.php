<?php

declare(strict_types=1);

namespace App\Referentiel;

use App\Enums\NiveauLangueEnum;

/**
 * Stable persistence keys for language levels (CECRL).
 *
 * String enum backed values are preserved; newly configured values
 * use their referential code.
 */
final class NiveauLangue
{
    public static function key(string|NiveauLangueEnum $value): string
    {
        return $value instanceof NiveauLangueEnum
            ? $value->value
            : (string) $value;
    }

    /**
     * Returns a historical enum only when the key matches exactly.
     * Custom codes return null.
     */
    public static function legacyCase(string|NiveauLangueEnum|null $value): ?NiveauLangueEnum
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof NiveauLangueEnum) {
            return $value;
        }

        return NiveauLangueEnum::tryFrom((string) $value);
    }
}
