<?php

declare(strict_types=1);

namespace App\Referentiel;

use App\Enums\RegimeInscriptionEnum;

/**
 * Stable persisted keys: the existing enum backed values remain canonical.
 * New referential values can use their code as their persisted key.
 */
final class RegimeInscription
{
    public static function key(string|RegimeInscriptionEnum $value): string
    {
        return $value instanceof RegimeInscriptionEnum ? $value->value : $value;
    }

    /** @param array<array-key, string|RegimeInscriptionEnum> $values
     *  @return list<string>
     */
    public static function normalize(array $values): array
    {
        return array_values(array_unique(array_map(self::key(...), $values)));
    }

    /**
     * @deprecated Compatibility adapter: custom referential keys are discarded.
     *             Use normalize() or entity getRegimeInscriptionValues() instead.
     * @return list<RegimeInscriptionEnum>
     */
    public static function legacyCases(array $values): array
    {
        $cases = [];
        foreach ($values as $value) {
            $case = RegimeInscriptionEnum::tryFrom(self::key($value));
            if ($case !== null) {
                $cases[] = $case;
            }
        }
        return $cases;
    }
}
