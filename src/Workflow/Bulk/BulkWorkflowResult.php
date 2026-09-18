<?php

declare(strict_types=1);

namespace App\Workflow\Bulk;

final readonly class BulkWorkflowResult
{
    /**
     * @param list<array{id: int, label: string}> $processed
     * @param list<array{id: int, label: string, message: string}> $rejected
     */
    public function __construct(
        public array $processed,
        public array $rejected,
    ) {
    }

    public function processedCount(): int
    {
        return count($this->processed);
    }

    public function rejectedCount(): int
    {
        return count($this->rejected);
    }
}
