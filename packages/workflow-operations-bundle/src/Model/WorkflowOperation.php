<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class WorkflowOperation
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $workflowName,
        public string $transitionName,
        public array $metadata = [],
    ) {
        if ('' === trim($this->workflowName)) {
            throw new \InvalidArgumentException('A workflow operation workflow name cannot be empty.');
        }

        if ('' === trim($this->transitionName)) {
            throw new \InvalidArgumentException('A workflow operation transition name cannot be empty.');
        }
    }
}
