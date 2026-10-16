<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Formation;
use App\Entity\Parcours;

final class ParcoursDeleteAnalysis
{
    /**
     * @param list<array{type: string, titre: string, description: string, severity: 'warning'|'danger', details?: list<string>}> $sharedDependencies
     * @param array<string, int> $internalElementsCount
     */
    public function __construct(
        public readonly Parcours $parcours,
        public readonly ?Formation $formation,
        public readonly bool $isSeulParcoursDeLaFormation,
        public readonly bool $isParcoursDefaut,
        public readonly array $sharedDependencies,
        public readonly array $internalElementsCount,
    ) {
    }

    public function hasSharedDependencies(): bool
    {
        return count($this->sharedDependencies) > 0;
    }

    public function getSharedDependenciesCount(): int
    {
        return count($this->sharedDependencies);
    }

    public function getTotalInternalElementsCount(): int
    {
        return (int)array_sum($this->internalElementsCount);
    }
}
