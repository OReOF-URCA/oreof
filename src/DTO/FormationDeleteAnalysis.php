<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Formation;
use App\Entity\Parcours;

final class FormationDeleteAnalysis
{
    /**
     * @param list<string> $blockingReasons
     * @param list<array{type: string, titre: string, description: string, severity: 'warning'|'danger', details?: list<string>}> $sharedDependencies
     * @param array<string, int> $internalElementsCount
     */
    public function __construct(
        public readonly Formation $formation,
        public readonly ?Parcours $parcoursDefaut,
        public readonly int $parcoursCount,
        public readonly array $blockingReasons,
        public readonly array $sharedDependencies,
        public readonly array $internalElementsCount,
        public readonly ?ParcoursDeleteAnalysis $parcoursDefautAnalysis = null,
    ) {
    }

    public function canDelete(): bool
    {
        return count($this->blockingReasons) === 0;
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
        return (int) array_sum($this->internalElementsCount);
    }
}
