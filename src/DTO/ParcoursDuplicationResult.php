<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Parcours;

final class ParcoursDuplicationResult
{
    /**
     * @param array<string, int> $createdCounts
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?Parcours $nouveauParcours = null,
        public readonly ?string $errorMessage = null,
        public readonly array $createdCounts = [],
        public readonly array $warnings = [],
    ) {
    }

    public static function success(Parcours $nouveauParcours, array $createdCounts = [], array $warnings = []): self
    {
        return new self(
            success: true,
            nouveauParcours: $nouveauParcours,
            errorMessage: null,
            createdCounts: $createdCounts,
            warnings: $warnings
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            nouveauParcours: null,
            errorMessage: $errorMessage,
            createdCounts: [],
            warnings: []
        );
    }
}
