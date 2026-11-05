<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class TotauxDiff
{
    public function __construct(
        public readonly float $ectsN1,
        public readonly float $ectsN,
        public readonly float $diffEcts,
        public readonly float $cmPresN1,
        public readonly float $cmPresN,
        public readonly float $diffCmPres,
        public readonly float $tdPresN1,
        public readonly float $tdPresN,
        public readonly float $diffTdPres,
        public readonly float $tpPresN1,
        public readonly float $tpPresN,
        public readonly float $diffTpPres,
        public readonly float $tePresN1,
        public readonly float $tePresN,
        public readonly float $diffTePres,
        public readonly float $totalPresN1,
        public readonly float $totalPresN,
        public readonly float $diffTotalPres,
        public readonly float $totalDistN1,
        public readonly float $totalDistN,
        public readonly float $diffTotalDist,
        public readonly float $totalHeuresN1,
        public readonly float $totalHeuresN,
        public readonly float $diffTotalHeures,
    ) {
    }

    public function hasDifferences(): bool
    {
        return abs($this->diffEcts) > 0.001
            || abs($this->diffCmPres) > 0.001
            || abs($this->diffTdPres) > 0.001
            || abs($this->diffTpPres) > 0.001
            || abs($this->diffTePres) > 0.001
            || abs($this->diffTotalPres) > 0.001
            || abs($this->diffTotalDist) > 0.001
            || abs($this->diffTotalHeures) > 0.001;
    }
}
