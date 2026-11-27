<?php

declare(strict_types=1);

namespace App\Message;

final readonly class AnalyzeParcoursCoherence
{
    public function __construct(
        public int  $parcoursId,
        public bool $useAi = true,
        public bool $forceRefresh = false,
    )
    {
    }
}
