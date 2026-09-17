<?php

namespace App\Workflow\Handler\Handlers;

use App\Classes\Process\ChangeRfProcess;
use App\Entity\ChangeRf;
use Dannebicque\WorkflowOperationsBundle\Contract\OperationCompletionHandlerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ChangeRfOperationCompletionHandler implements OperationCompletionHandlerInterface
{
    public function __construct(private ChangeRfProcess $changeRfProcess)
    {
    }

    public function supports(object $subject, WorkflowOperation $operation): bool
    {
        return $subject instanceof ChangeRf && 'changeRf' === $operation->workflowName;
    }

    public function complete(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): void {
        if (!$subject instanceof ChangeRf || !$context->actor instanceof UserInterface) {
            throw new \LogicException('A ChangeRf operation requires a ChangeRf subject and an authenticated actor.');
        }

        $request = $context->runtime['request'] ?? null;
        $previousPlace = $context->runtime['previous_place'] ?? null;
        if (!$request instanceof Request || !is_string($previousPlace) || '' === $previousPlace) {
            throw new \LogicException('The ChangeRf completion context is incomplete.');
        }

        if ('reserver' === ($operation->metadata['type'] ?? null)) {
            $this->changeRfProcess->completeReservedChangeRf(
                $subject,
                $context->actor,
                $previousPlace,
                $request,
            );

            return;
        }

        $this->changeRfProcess->completeValidatedChangeRf(
            $subject,
            $context->actor,
            $previousPlace,
            $request,
            $context->runtime['file_name'] ?? null,
            $context->runtime['original_file_name'] ?? null,
        );
    }
}
