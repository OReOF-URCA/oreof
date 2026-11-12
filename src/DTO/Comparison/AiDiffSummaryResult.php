<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class AiDiffSummaryResult
{
    /**
     * @param list<string> $highlights
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $highlights,
        public readonly bool $isAiGenerated,
        public readonly string $providerLabel,
        public readonly bool $hasRegulatoryImpact,
        public readonly ?string $fallbackReason = null,
    ) {
    }
}

