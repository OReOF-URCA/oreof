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
        $targetPlace = $operation->primaryTargetPlace();

        return $subject instanceof DpeParcours
            && 'dpeParcours' === $operation->workflowName
            && null !== $targetPlace
            && $this->validationService->hasValidatorForStep($targetPlace);
    }

    public function getBlockers(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): iterable {
        if (!$subject instanceof DpeParcours) {
            throw new \InvalidArgumentException(sprintf('Expected %s, got %s.', DpeParcours::class, get_debug_type($subject)));
        }

        $targetPlace = $operation->primaryTargetPlace();
        if (null === $targetPlace) {
            return;
        }

        $result = $this->validationService->validateForStep($subject, $targetPlace);

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
}
