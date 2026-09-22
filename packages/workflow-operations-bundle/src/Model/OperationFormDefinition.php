<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class OperationFormDefinition
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public string $type,
        public array $options = [],
        public ?string $template = null,
    ) {
    }
}
