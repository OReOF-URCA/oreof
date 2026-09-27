<?php

declare(strict_types=1);

namespace App\DTO\Campagne;

use App\Entity\AnneeUniversitaire;
use App\Entity\CampagneCollecte;

final class CampagneDuplicationResultDTO
{
    public function __construct(
        public bool $success = false,
        public ?CampagneCollecte $targetCampagne = null,
        public ?AnneeUniversitaire $targetAnneeUniversitaire = null,
        public array $createdCounts = [],
        public array $logs = [],
        public array $errors = [],
        public float $executionTimeSeconds = 0.0,
    ) {
    }

    public function getTotalCreated(): int
    {
        return array_sum($this->createdCounts);
    }
}
