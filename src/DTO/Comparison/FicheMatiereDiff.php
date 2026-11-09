<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

use App\Entity\FicheMatiere;

final class FicheMatiereDiff
{
    public const STATUS_IDENTICAL = 'identical';
    public const STATUS_MODIFIED = 'modified';
    public const STATUS_MISSING_IN_N = 'missing_in_n';
    public const STATUS_ADDED_IN_N = 'added_in_n';
    public const STATUS_UNLINKED = 'unlinked';

    /**
     * @param array<string, FieldDiff> $differences
     */
    public function __construct(
        public readonly string $status,
        public readonly string $sigle,
        public readonly string $typeMatiere,
        public readonly ?string $libelleN1,
        public readonly ?string $libelleN,
        public readonly ?FicheMatiere $ficheN1,
        public readonly ?FicheMatiere $ficheN,
        public readonly array $differences = [],
        public readonly bool $isOriginLinkBroken = false,
        public readonly string $scopeSource = 'portee',
        public readonly bool $isDetachedFromParcours = false,
    ) {
    }

    public function isIdentical(): bool
    {
        return $this->status === self::STATUS_IDENTICAL;
    }

    public function isMissingInN(): bool
    {
        return $this->status === self::STATUS_MISSING_IN_N;
    }

    public function isAddedInN(): bool
    {
        return $this->status === self::STATUS_ADDED_IN_N;
    }

    public function isModified(): bool
    {
        return $this->status === self::STATUS_MODIFIED;
    }

    public function hasDifferences(): bool
    {
        return $this->status !== self::STATUS_IDENTICAL;
    }
}
