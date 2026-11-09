<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class SemestreDiff
{
    /**
     * @param list<UeDiff> $ues
     * @param array<string, float> $totauxN1
     * @param array<string, float> $totauxN
     * @param array<string, float> $totauxDiff
     */
    public function __construct(
        public readonly int $ordre,
        public readonly string $libelle,
        public readonly bool $ouvertN1,
        public readonly bool $ouvertN,
        public readonly array $ues = [],
        public readonly array $totauxN1 = [],
        public readonly array $totauxN = [],
        public readonly array $totauxDiff = [],
    ) {
    }

    public function hasDifferences(): bool
    {
        if ($this->ouvertN1 !== $this->ouvertN) {
            return true;
        }

        foreach ($this->totauxDiff as $diff) {
            if (abs($diff) > 0.001) {
                return true;
            }
        }

        foreach ($this->ues as $ue) {
            if ($ue->hasDifferences()) {
                return true;
            }
        }

        return false;
    }

    public function countEcs(): int
    {
        $count = 0;
        foreach ($this->ues as $ue) {
            $count += count($ue->ecs);
        }

        return $count;
    }
}
