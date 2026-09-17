<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Security;

use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Dannebicque\WorkflowOperationsBundle\Security\SecurityMetadataOperationAuthorizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SecurityMetadataOperationAuthorizerTest extends TestCase
{
    public function testItCanPassTheWholeOperationToAVoter(): void
    {
        $subject = new \stdClass();
        $operation = new WorkflowOperation(
            workflowName: 'document',
            transitionName: 'publish',
            metadata: [
                'authorization' => [
                    'attribute' => 'DOCUMENT_PUBLISH',
                    'subject' => 'operation',
                ],
            ],
        );

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker
            ->expects(self::once())
            ->method('isGranted')
            ->with(
                'DOCUMENT_PUBLISH',
                self::callback(static fn (mixed $value): bool => $value instanceof OperationAuthorizationSubject
                    && $value->subject === $subject
                    && $value->operation === $operation),
            )
            ->willReturn(true);

        $decision = (new SecurityMetadataOperationAuthorizer($checker))->decide(
            $subject,
            $operation,
            OperationContext::empty(),
        );

        self::assertTrue($decision->allowed);
    }

    public function testItDeniesAnInvalidConfiguration(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizer = new SecurityMetadataOperationAuthorizer($checker);

        $decision = $authorizer->decide(
            new \stdClass(),
            new WorkflowOperation('document', 'publish', metadata: ['authorization' => []]),
            OperationContext::empty(),
        );

        self::assertFalse($decision->allowed);
        self::assertSame(['authorization.invalid_configuration'], $decision->reasons);
    }
}
