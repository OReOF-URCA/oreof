<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Operation;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationAuthorizerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\AuthorizationDecision;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;

final readonly class OperationAuthorizationChecker
{
    /** @param iterable<OperationAuthorizerInterface> $authorizers */
    public function __construct(private iterable $authorizers)
    {
    }

    public function decide(
        object $subject,
        WorkflowOperation $operation,
        ?OperationContext $context = null,
    ): AuthorizationDecision {
        $context ??= OperationContext::empty();
        $reasons = [];
        $allowed = true;

        foreach ($this->authorizers as $authorizer) {
            if (!$authorizer->supports($subject, $operation)) {
                continue;
            }

            $decision = $authorizer->decide($subject, $operation, $context);
            if (!$decision->allowed) {
                $allowed = false;
                $reasons = [...$reasons, ...$decision->reasons];
            }
        }

        return $allowed
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::deny(...$reasons);
    }
}
