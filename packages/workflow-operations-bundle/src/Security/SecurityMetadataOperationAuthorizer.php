<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Security;

use Dannebicque\WorkflowOperationsBundle\Contract\OperationAuthorizerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\AuthorizationDecision;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class SecurityMetadataOperationAuthorizer implements OperationAuthorizerInterface
{
    public function __construct(private AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function supports(object $subject, WorkflowOperation $operation): bool
    {
        return array_key_exists('authorization', $operation->metadata);
    }

    public function decide(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): AuthorizationDecision {
        $configuration = $this->normalizeConfiguration($operation->metadata['authorization'] ?? null);
        if (null === $configuration) {
            return AuthorizationDecision::deny('authorization.invalid_configuration');
        }

        $authorizationSubject = match ($configuration['subject']) {
            'none' => null,
            'operation' => new OperationAuthorizationSubject($subject, $operation, $context),
            default => $subject,
        };

        return $this->authorizationChecker->isGranted($configuration['attribute'], $authorizationSubject)
            ? AuthorizationDecision::allow()
            : AuthorizationDecision::deny($configuration['reason']);
    }

    /**
     * @return array{attribute: string, subject: 'subject'|'operation'|'none', reason: string}|null
     */
    private function normalizeConfiguration(mixed $configuration): ?array
    {
        if (is_string($configuration) && '' !== trim($configuration)) {
            return [
                'attribute' => $configuration,
                'subject' => 'subject',
                'reason' => 'authorization.denied',
            ];
        }

        if (!is_array($configuration)) {
            return null;
        }

        $attribute = $configuration['attribute'] ?? null;
        $subject = $configuration['subject'] ?? 'subject';
        if (!is_string($attribute) || '' === trim($attribute)) {
            return null;
        }

        if (!in_array($subject, ['subject', 'operation', 'none'], true)) {
            return null;
        }

        $reason = $configuration['reason'] ?? 'authorization.denied';

        return [
            'attribute' => $attribute,
            'subject' => $subject,
            'reason' => is_string($reason) && '' !== trim($reason) ? $reason : 'authorization.denied',
        ];
    }
}
