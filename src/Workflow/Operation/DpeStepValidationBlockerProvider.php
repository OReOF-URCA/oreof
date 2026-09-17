<?php

declare(strict_types=1);

namespace App\Workflow\Operation;

use App\Entity\DpeParcours;
use App\Workflow\Service\ValidationService;
use Dannebicque\WorkflowOperationsBundle\Contract\OperationBlockerProviderInterface;
use Dannebicque\WorkflowOperationsBundle\Model\BlockerSeverity;
use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;

/**
 * Adapts ORéOF step validators to the generic operation blocker contract.
 */
final readonly class DpeStepValidationBlockerProvider implements OperationBlockerProviderInterface
{
    public function __construct(private ValidationService $validationService)
    {
    }

    public function supports(object $subject, WorkflowOperation $operation): bool
    {
        $validationStep = $this->resolveValidationStep($operation);

        return $subject instanceof DpeParcours
            && 'dpeParcours' === $operation->workflowName
            && null !== $validationStep
            && $this->validationService->hasValidatorForStep($validationStep);
    }

    public function getBlockers(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): iterable {
        if (!$subject instanceof DpeParcours) {
            throw new \InvalidArgumentException(sprintf('Expected %s, got %s.', DpeParcours::class, get_debug_type($subject)));
        }

        $validationStep = $this->resolveValidationStep($operation);
        if (null === $validationStep) {
            return;
        }

        $result = $this->validationService->validateForStep($subject, $validationStep);

        foreach ($result->getErrors() as $error) {
            yield new OperationBlocker(
                code: $error->getCode(),
                message: $error->getMessage(),
                severity: BlockerSeverity::Error,
                path: $error->getPath() ?? $error->getField(),
                parameters: $error->getParameters(),
            );
        }

        foreach ($result->getWarnings() as $warning) {
            yield new OperationBlocker(
                code: $warning->getCode(),
                message: $warning->getMessage(),
                severity: BlockerSeverity::Warning,
                path: $warning->getField(),
                parameters: $warning->getParameters(),
            );
        }
    }

    private function resolveValidationStep(WorkflowOperation $operation): ?string
    {
        $configuration = $operation->metadata['validation'] ?? null;
        if (is_array($configuration)) {
            if (false === ($configuration['enabled'] ?? true)) {
                return null;
            }

            $step = $configuration['step'] ?? null;
            if (is_string($step) && '' !== trim($step)) {
                return $step;
            }
        }

        return $operation->primaryTargetPlace();
    }
}
