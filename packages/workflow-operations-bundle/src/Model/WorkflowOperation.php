<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class WorkflowOperation
{
    /**
     * @param list<string> $fromPlaces
     * @param list<string> $toPlaces
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $workflowName,
        public string $transitionName,
        public array $fromPlaces = [],
        public array $toPlaces = [],
        public array $metadata = [],
    ) {
        if ('' === trim($this->workflowName)) {
            throw new \InvalidArgumentException('A workflow operation workflow name cannot be empty.');
        }

        if ('' === trim($this->transitionName)) {
            throw new \InvalidArgumentException('A workflow operation transition name cannot be empty.');
        }
    }

    public function primaryTargetPlace(): ?string
    {
        return $this->toPlaces[0] ?? null;
    }
}
