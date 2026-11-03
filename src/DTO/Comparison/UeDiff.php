<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

use App\Entity\Ue;

final class UeDiff
{
    public const STATUS_IDENTICAL = 'identical';
    public const STATUS_MODIFIED = 'modified';
    public const STATUS_MISSING_IN_N = 'missing_in_n';
    public const STATUS_ADDED_IN_N = 'added_in_n';

    /**
     * @param list<EcDiff> $ecs
     */
    public function __construct(
        public readonly string $status,
        public readonly string $libelle,
        public readonly ?int $ordre,
        public readonly ?string $codeApogee,
        public readonly ?Ue $ueN1,
        public readonly ?Ue $ueN,
        public readonly array $ecs = [],
    ) {
    }

    public function hasDifferences(): bool
    {
        if ($this->status !== self::STATUS_IDENTICAL) {
            return true;
        }

        foreach ($this->ecs as $ec) {
            if ($ec->hasDifferences()) {
                return true;
            }
        }

        return false;
    }

    public function countEcs(): int
    {
        return count($this->ecs);
    }
}
