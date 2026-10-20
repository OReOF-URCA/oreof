<?php
namespace App\Referentiel;

use App\Entity\ReferentielValeur;

final class LegacyEnumMapper
{
    public function __construct(private readonly ReferentielManager $manager) {}

    public function fromEnum(string $referentiel, \BackedEnum $enum): ?ReferentielValeur
    {
        return $this->manager->findByLegacyValue($referentiel, $enum->value);
    }

    /** @param class-string<\BackedEnum> $enumClass */
    public function toEnum(ReferentielValeur $valeur, string $enumClass): ?\BackedEnum
    {
        $legacy = $valeur->getLegacyValue();
        if ($legacy === null || !enum_exists($enumClass)) { return null; }
        foreach ($enumClass::cases() as $case) {
            if ((string) $case->value === $legacy) { return $case; }
        }
        return null;
    }
}
