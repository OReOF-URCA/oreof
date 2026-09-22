<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Workflow/ModalView/TransitionModalViewBuilder.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2026 18:37
 */


namespace App\Workflow\ModalView;

use App\Entity\DpeParcours;
use App\Workflow\Service\ValidationService;
use App\Workflow\Validator\ValidationCheck;
use Dannebicque\WorkflowOperationsBundle\Model\BlockerSeverity;
use Dannebicque\WorkflowOperationsBundle\Model\OperationStatus;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationInspector;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

final class TransitionModalViewBuilder
{
    public function __construct(
        #[Target('dpeParcours')]
        private readonly WorkflowInterface $dpeParcoursWorkflow,
        private readonly WorkflowOperationInspector $operationInspector,
        private readonly ValidationService $validationService,
    )
    {
    }

    public function build(string $transition, DpeParcours $dpeParcours, array $rawMeta): ?TransitionModalView
    {
        $inspection = $this->operationInspector->inspect(
            $this->dpeParcoursWorkflow,
            $dpeParcours,
            $transition,
        );
        $blockers = $inspection->blockers;

        $validation = is_array($rawMeta['validation'] ?? null) ? $rawMeta['validation'] : [];
        $forceReport = isset($rawMeta['view'])
            || isset($validation['view'])
            || true === ($validation['display'] ?? false);

        if (OperationStatus::Ready === $inspection->status && 0 === count($blockers) && !$forceReport) {
            return null;
        }

        $messages = [];
        if (OperationStatus::Forbidden === $inspection->status) {
            $messages[] = [
                'level' => 'error',
                'code' => 'operation.forbidden',
                'message' => 'Vous n’êtes pas autorisé à effectuer cette action.',
                'path' => null,
                'parameters' => [],
            ];
        } elseif (OperationStatus::Unavailable === $inspection->status) {
            $messages[] = [
                'level' => 'error',
                'code' => 'operation.unavailable',
                'message' => 'Cette transition n’est pas disponible dans l’état actuel.',
                'path' => null,
                'parameters' => [],
            ];
        }

        foreach ($blockers as $blocker) {
            $messages[] = [
                'level' => match ($blocker->severity) {
                    BlockerSeverity::Error => 'error',
                    BlockerSeverity::Warning => 'warning',
                    BlockerSeverity::Information => 'information',
                },
                'code' => $blocker->code,
                'message' => $blocker->message,
                'path' => $blocker->path,
                'parameters' => $blocker->parameters,
            ];
        }

        $checks = [];
        $validationStep = $validation['step'] ?? null;
        if (is_string($validationStep) && $this->validationService->hasValidatorForStep($validationStep)) {
            $checks = array_map(
                static fn(ValidationCheck $check): array => $check->toArray(),
                $this->validationService->validateForStep($dpeParcours, $validationStep)->getChecks(),
            );
        }

        return new TransitionModalView(
            mode: 'report',
            canSubmit: $inspection->canExecute(),
            messages: $messages,
            checks: $checks,
        );
    }
}
