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
        public array $runtime = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Creates a context from normalized application input.
     *
     * Aliases map an input key to the top-level Workflow context key expected
     * by subscribers, for example ['argumentaire' => 'motif'].
     *
     * @param array<string, mixed>  $input
     * @param array<string, string> $aliases
     * @param array<string, mixed>  $metadata
     */
    public static function fromInput(
        ?object $actor,
        array $input,
        array $aliases = [],
        array $metadata = [],
    ): self {
        $aliasedInput = [];
        foreach ($aliases as $inputKey => $contextKey) {
            if (array_key_exists($inputKey, $input)) {
                $aliasedInput[$contextKey] = $input[$inputKey];
            }
        }

        return new self($actor, $input, [...$aliasedInput, ...$metadata]);
    }

    /** @param array<string, mixed> $runtime */
    public function withRuntime(array $runtime): self
    {
        return new self($this->actor, $this->input, $this->metadata, $runtime);
    }

    public function workflowContext(): array
    {
        return [
            ...$this->input,
            ...$this->metadata,
            'actor' => $this->actor,
            'input' => $this->input,
        ];
    }
}
