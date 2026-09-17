<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Model;

final readonly class AuthorizationDecision
{
    /** @param list<string> $reasons */
    private function __construct(
        public bool $allowed,
        public array $reasons = [],
    ) {
    }

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string ...$reasons): self
    {
        return new self(false, array_values(array_filter($reasons)));
    }
}
