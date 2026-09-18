<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Workflow/ModalView/TransitionModalViewBuilder.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2026 18:37
 */


declare(strict_types=1);

namespace App\Workflow\ModalView;

use App\Entity\FicheMatiere;
use App\Workflow\Validator\FicheMatiereValidator;
use Dannebicque\WorkflowOperationsBundle\Model\BlockerSeverity;
use Dannebicque\WorkflowOperationsBundle\Model\OperationStatus;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationInspector;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

final class TransitionModalFicheMatiereViewBuilder
{
    public function __construct(
        #[Target('fiche')]
        private readonly WorkflowInterface $ficheWorkflow,
        private readonly WorkflowOperationInspector $operationInspector,
        private readonly FicheMatiereValidator $validator,
    ) {
    }

    public function build(string $transition, FicheMatiere $ficheMatiere, array $rawMeta): ?TransitionModalView
    {
        $inspection = $this->operationInspector->inspect($this->ficheWorkflow, $ficheMatiere, $transition);
        $validation = is_array($rawMeta['validation'] ?? null) ? $rawMeta['validation'] : [];
        $displayValidation = true === ($validation['display'] ?? false);

        if (OperationStatus::Ready === $inspection->status && !$displayValidation) {
            return null;
        }

        $messages = [];
        if (OperationStatus::Forbidden === $inspection->status) {
            $messages[] = $this->message('error', 'operation.forbidden', 'Vous n’êtes pas autorisé à effectuer cette action.');
        } elseif (OperationStatus::Unavailable === $inspection->status) {
            $messages[] = $this->message('error', 'operation.unavailable', 'Cette transition n’est pas disponible dans l’état actuel.');
        }

        foreach ($inspection->blockers as $blocker) {
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
        $isValid = true;
        if ($displayValidation) {
            $result = $this->validator->validate($ficheMatiere);
            $isValid = $result->isValid();
            $checks = array_map(static fn(\App\Workflow\Validator\ValidationCheck $check): array => $check->toArray(), $result->getChecks());

            foreach ($result->getErrors() as $error) {
                $messages[] = [
                    'level' => 'error',
                    'code' => $error->getCode(),
                    'message' => $error->getMessage(),
                    'path' => $error->getField(),
                    'parameters' => $error->getParameters(),
                ];
            }
        }

        return new TransitionModalView(
            mode: 'report',
            canSubmit: $inspection->canExecute() && $isValid,
            messages: $messages,
            checks: $checks,
        );
    }

    /** @return array{level: string, code: string, message: string, path: null, parameters: array{}} */
    private function message(string $level, string $code, string $message): array
    {
        return compact('level', 'code', 'message') + ['path' => null, 'parameters' => []];
    }
}
