<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Formation;
use App\Entity\Parcours;

final class ParcoursDuplicationAnalysis
{
    /**
     * @param array<string, int> $counts
     * @param list<array{type: string, titre: string, description: string, severity: string, details?: list<string>}> $warnings
     * @param list<string> $elementsRecopies
     * @param list<string> $elementsReinitialises
     * @param list<string> $elementsExclus
     */
    public function __construct(
        public readonly Parcours $parcours,
        public readonly ?Formation $formation,
        public readonly array $counts,
        public readonly array $warnings = [],
        public readonly array $elementsRecopies = [],
        public readonly array $elementsReinitialises = [],
        public readonly array $elementsExclus = [],
    ) {
    }

    public function getNbSemestres(): int
    {
        return $this->counts['semestres'] ?? 0;
    }

    public function getNbUes(): int
    {
        return $this->counts['ues'] ?? 0;
    }

    public function getNbEcs(): int
    {
        return $this->counts['ecs'] ?? 0;
    }

    public function getNbFichesMatieres(): int
    {
        return $this->counts['fiches_matieres'] ?? 0;
    }

    public function getNbMcccs(): int
    {
        return $this->counts['mcccs'] ?? 0;
    }

    public function getNbBlocs(): int
    {
        return $this->counts['blocs'] ?? 0;
    }

    public function getNbCompetences(): int
    {
        return $this->counts['competences'] ?? 0;
    }

    public function getNbContacts(): int
    {
        return $this->counts['contacts'] ?? 0;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
}
