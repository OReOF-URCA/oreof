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
use Dannebicque\WorkflowOperationsBundle\Model\BlockerSeverity;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationBlockerCollector;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationFactory;
use Symfony\Component\Workflow\WorkflowInterface;

final class TransitionModalViewBuilder
{
    public function __construct(
        private readonly WorkflowInterface $dpeParcoursWorkflow,
        private readonly WorkflowOperationFactory $operationFactory,
        private readonly OperationBlockerCollector $blockerCollector,
    )
    {
    }

    public function build(string $transition, DpeParcours $dpeParcours, array $rawMeta): ?TransitionModalView
    {
        $operation = $this->operationFactory->create($this->dpeParcoursWorkflow, $transition);
        $blockers = $this->blockerCollector->collect($dpeParcours, $operation);

        if (0 === count($blockers) && !isset($rawMeta['view'])) {
            return null;
        }

        $messages = [];
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

        return new TransitionModalView(
            mode: 'report',
            canSubmit: !$blockers->hasBlockingItems(),
            messages: $messages,
        );
    }
}
