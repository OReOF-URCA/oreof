<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Operation;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationAuthorizerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\AuthorizationDecision;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationAuthorizationChecker;
use PHPUnit\Framework\TestCase;

final class OperationAuthorizationCheckerTest extends TestCase
{
    public function testItAllowsAnOperationWhenNoAuthorizerDeniesIt(): void
    {
        $checker = new OperationAuthorizationChecker([
            $this->authorizer(false, AuthorizationDecision::deny('ignored')),
            $this->authorizer(true, AuthorizationDecision::allow()),
        ]);

        $decision = $checker->decide(new \stdClass(), new WorkflowOperation('document', 'publish'));

        self::assertTrue($decision->allowed);
        self::assertSame([], $decision->reasons);
    }

    public function testItAggregatesDenialReasons(): void
    {
        $checker = new OperationAuthorizationChecker([
            $this->authorizer(true, AuthorizationDecision::deny('missing_role')),
            $this->authorizer(true, AuthorizationDecision::deny('outside_scope')),
        ]);

        $decision = $checker->decide(new \stdClass(), new WorkflowOperation('document', 'publish'));

        self::assertFalse($decision->allowed);
        self::assertSame(['missing_role', 'outside_scope'], $decision->reasons);
    }

    private function authorizer(
        bool $supports,
        AuthorizationDecision $decision,
    ): OperationAuthorizerInterface {
        return new class($supports, $decision) implements OperationAuthorizerInterface {
            public function __construct(
                private readonly bool $supported,
                private readonly AuthorizationDecision $decision,
            ) {
            }

            public function supports(object $subject, WorkflowOperation $operation): bool
            {
                return $this->supported;
            }

            public function decide(
                object $subject,
                WorkflowOperation $operation,
                OperationContext $context,
            ): AuthorizationDecision {
                return $this->decision;
            }
        };
    }
}
