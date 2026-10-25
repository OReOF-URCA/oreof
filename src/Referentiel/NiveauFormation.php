<?php

declare(strict_types=1);

namespace App\Referentiel;

use App\Enums\NiveauFormationEnum;

/**
 * Stable persistence keys for formation entry/exit levels.
 *
 * Historical numeric keys are preserved as strings; newly configured values
 * use their referential code. No integer coercion is performed for custom keys.
 */
final class NiveauFormation
{
    public static function key(string|int|NiveauFormationEnum $value): string
    {
        return $value instanceof NiveauFormationEnum
            ? (string) $value->value
            : (string) $value;
    }

    /**
     * Returns a historical enum only when the key matches exactly.
     * Custom codes are never cast to integers.
     */
    public static function legacyCase(string|int|NiveauFormationEnum|null $value): ?NiveauFormationEnum
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof NiveauFormationEnum) {
            return $value;
        }

        $key = self::key($value);
        if (!in_array($key, array_map(static fn (NiveauFormationEnum $case): string => (string) $case->value, NiveauFormationEnum::cases()), true)) {
            return null;
        }

        return NiveauFormationEnum::tryFrom((int) $key);
    }
}
