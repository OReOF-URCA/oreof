<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Model;

use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use PHPUnit\Framework\TestCase;

final class WorkflowOperationTest extends TestCase
{
    public function testItExposesItsPrimaryTargetPlace(): void
    {
        $operation = new WorkflowOperation(
            workflowName: 'publication',
            transitionName: 'submit',
            fromPlaces: ['draft'],
            toPlaces: ['review', 'legal_review'],
        );

        self::assertSame('review', $operation->primaryTargetPlace());
    }

    public function testItHasNoPrimaryTargetWhenTransitionHasNoTarget(): void
    {
        $operation = new WorkflowOperation('publication', 'finish');

        self::assertNull($operation->primaryTargetPlace());
    }
}
