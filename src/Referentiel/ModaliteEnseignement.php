<?php

declare(strict_types=1);

namespace App\Referentiel;

use App\Enums\ModaliteEnseignementEnum;

/**
 * Stable persistence keys for teaching modalities.
 *
 * Historical numeric keys are preserved as strings; newly configured values
 * use their referential code. No integer coercion is performed for custom keys.
 */
final class ModaliteEnseignement
{
    public static function key(string|int|ModaliteEnseignementEnum $value): string
    {
        return $value instanceof ModaliteEnseignementEnum
            ? (string) $value->value
            : (string) $value;
    }

    /**
     * Returns a historical enum only when the key matches exactly.
     * Custom codes are never cast to integers.
     */
    public static function legacyCase(string|int|ModaliteEnseignementEnum|null $value): ?ModaliteEnseignementEnum
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof ModaliteEnseignementEnum) {
            return $value;
        }

        $key = self::key($value);
        if (!in_array($key, array_map(static fn (ModaliteEnseignementEnum $case): string => (string) $case->value, ModaliteEnseignementEnum::cases()), true)) {
            return null;
        }

        return ModaliteEnseignementEnum::tryFrom((int) $key);
    }
}
