<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

use App\Entity\FicheMatiere;

final class MutualisationDiff
{
    public const STATUS_CONSERVED = 'conserved';
    public const STATUS_LOST_IN_N = 'lost_in_n';
    public const STATUS_ADDED_IN_N = 'added_in_n';
    public const STATUS_EXTERNAL = 'external';

    public function __construct(
        public readonly string $status,
        public readonly string $sigle,
        public readonly string $libelle,
        public readonly string $sourceParcours,
        public readonly string $targetParcours,
        public readonly ?FicheMatiere $ficheMatiere = null,
        public readonly ?string $warningMessage = null,
    ) {
    }

    public function isLost(): bool
    {
        return $this->status === self::STATUS_LOST_IN_N;
    }

    public function isExternal(): bool
    {
        return $this->status === self::STATUS_EXTERNAL;
    }

    public function isAdded(): bool
    {
        return $this->status === self::STATUS_ADDED_IN_N;
    }
}
