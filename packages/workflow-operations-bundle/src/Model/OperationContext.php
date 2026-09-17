<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class OperationContext
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ?object $actor = null,
        public array $input = [],
        public array $metadata = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function workflowContext(): array
    {
        return [
            ...$this->metadata,
            'actor' => $this->actor,
            'input' => $this->input,
        ];
    }
}
