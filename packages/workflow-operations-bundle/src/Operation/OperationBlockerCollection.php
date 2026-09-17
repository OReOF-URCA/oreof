<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;

/** @implements \IteratorAggregate<int, OperationBlocker> */
final readonly class OperationBlockerCollection implements \Countable, \IteratorAggregate
{
    /** @param list<OperationBlocker> $blockers */
    public function __construct(private array $blockers = [])
    {
    }

    public function hasBlockingItems(): bool
    {
        foreach ($this->blockers as $blocker) {
            if ($blocker->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->blockers);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->blockers;
    }

    /** @return list<OperationBlocker> */
    public function all(): array
    {
        return $this->blockers;
    }
}
