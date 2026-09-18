<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Workflow\Bulk\BulkWorkflowResult;
use PHPUnit\Framework\TestCase;

final class BulkWorkflowResultTest extends TestCase
{
    public function testItCountsProcessedAndRejectedItems(): void
    {
        $result = new BulkWorkflowResult(
            processed: [
                ['id' => 1, 'label' => 'Premier'],
                ['id' => 2, 'label' => 'Second'],
            ],
            rejected: [
                ['id' => 3, 'label' => 'Troisième', 'message' => 'Transition indisponible.'],
            ],
        );

        self::assertSame(2, $result->processedCount());
        self::assertSame(1, $result->rejectedCount());
    }
}
