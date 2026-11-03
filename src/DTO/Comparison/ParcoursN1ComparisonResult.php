<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

use App\Entity\Parcours;

final class ParcoursN1ComparisonResult
{
    /**
     * @param list<FicheMatiereDiff> $fiches
     * @param list<SemestreDiff> $semestres
     * @param list<MutualisationDiff> $mutualisations
     * @param list<AnomalyItem> $anomalies
     */
    public function __construct(
        public readonly Parcours $parcoursN,
        public readonly ?Parcours $parcoursN1,
        public readonly array $fiches = [],
        public readonly array $semestres = [],
        public readonly array $mutualisations = [],
        public readonly ?TotauxDiff $totauxGlobal = null,
        public readonly array $anomalies = [],
        public readonly bool $isParcoursOriginDirect = true,
        public readonly string $scope = 'parcours',
        public readonly int $totalFichesParcours = 0,
        public readonly int $totalFichesFormation = 0,
    ) {
    }

    public function hasParcoursN1(): bool
    {
        return $this->parcoursN1 !== null;
    }

    public function countErrors(): int
    {
        return count(array_filter($this->anomalies, static fn (AnomalyItem $a) => $a->isError()));
    }

    public function countWarnings(): int
    {
        return count(array_filter($this->anomalies, static fn (AnomalyItem $a) => $a->isWarning()));
    }

    public function countInfos(): int
    {
        return count(array_filter($this->anomalies, static fn (AnomalyItem $a) => $a->isInfo()));
    }

    public function countFichesDifferences(): int
    {
        return count(array_filter($this->fiches, static fn (FicheMatiereDiff $f) => $f->hasDifferences()));
    }

    public function countEcsDifferences(): int
    {
        $count = 0;
        foreach ($this->semestres as $semestre) {
            foreach ($semestre->ues as $ue) {
                foreach ($ue->ecs as $ec) {
                    if ($ec->hasDifferences()) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function countMutualisationsDifferences(): int
    {
        return count(array_filter($this->mutualisations, static fn (MutualisationDiff $m) => $m->status !== MutualisationDiff::STATUS_CONSERVED));
    }

    public function hasDifferences(): bool
    {
        return $this->countErrors() > 0
            || $this->countWarnings() > 0
            || $this->countFichesDifferences() > 0
            || $this->countEcsDifferences() > 0
            || $this->countMutualisationsDifferences() > 0
            || ($this->totauxGlobal !== null && $this->totauxGlobal->hasDifferences());
    }
}
