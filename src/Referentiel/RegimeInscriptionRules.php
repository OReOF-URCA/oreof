<?php

declare(strict_types=1);

namespace App\Referentiel;

use App\Enums\RegimeInscriptionEnum;

/**
 * Referential business flags with safe defaults for historical enum values.
 */
final class RegimeInscriptionRules
{
    public function __construct(private readonly ReferentielManager $referentiels)
    {
    }

    /** @param list<string|RegimeInscriptionEnum> $values */
    public function requiresAlternanceDetails(array $values): bool
    {
        foreach ($values as $value) {
            $key = RegimeInscription::key($value);
            $entry = $this->referentiels->findByLegacyValue('regime_inscription', $key)
                ?? $this->referentiels->find('regime_inscription', $key);
            $config = $entry?->getConfig() ?? [];
            if (array_key_exists('requires_alternance_details', $config)) {
                if ($config['requires_alternance_details'] === true) {
                    return true;
                }
                continue;
            }
            if ($entry === null && !in_array($key, [RegimeInscriptionEnum::FI->value, RegimeInscriptionEnum::FC->value], true)) {
                return true;
            }
            if ($entry !== null && !in_array($key, [RegimeInscriptionEnum::FI->value, RegimeInscriptionEnum::FC->value], true)) {
                return true;
            }
        }

        return false;
    }
}
