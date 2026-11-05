<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class FieldDiff
{
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly mixed $valN1,
        public readonly mixed $valN,
        public readonly bool $isDiff,
    ) {
    }
}
