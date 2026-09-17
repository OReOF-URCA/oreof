<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class OperationBlocker
{
    /** @param array<string, scalar|null> $parameters */
    public function __construct(
        public string $code,
        public string $message,
        public BlockerSeverity $severity = BlockerSeverity::Error,
        public ?string $path = null,
        public array $parameters = [],
    ) {
        if ('' === trim($this->code)) {
            throw new \InvalidArgumentException('An operation blocker code cannot be empty.');
        }

        if ('' === trim($this->message)) {
            throw new \InvalidArgumentException('An operation blocker message cannot be empty.');
        }
    }

    public function isBlocking(): bool
    {
        return BlockerSeverity::Error === $this->severity;
    }
}
