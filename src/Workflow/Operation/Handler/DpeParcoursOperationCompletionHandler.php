<?php

declare(strict_types=1);

namespace App\Workflow\Operation\Handler;

use App\Entity\DpeParcours;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\DpeDemandeRepository;
use Dannebicque\WorkflowOperationsBundle\Contract\OperationCompletionHandlerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;

final readonly class DpeParcoursOperationCompletionHandler implements OperationCompletionHandlerInterface
{
    public function __construct(private DpeDemandeRepository $dpeDemandeRepository)
    {
    }

    public function supports(object $subject, WorkflowOperation $operation): bool
    {
        return $subject instanceof DpeParcours && 'dpeParcours' === $operation->workflowName;
    }

    public function complete(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): void {
        if (!$subject instanceof DpeParcours) {
            throw new \InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                DpeParcours::class,
                get_debug_type($subject),
            ));
        }

        $previousPlace = $context->runtime['previous_place'] ?? null;
        if ('soumis_central_sans_cfvu' === $previousPlace) {
            $subject->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
        }

        $parcours = $subject->getParcours();
        if (null === $parcours) {
            return;
        }

        $demande = $this->dpeDemandeRepository->findLastUnclosedDemande($parcours);
        if (null === $demande) {
            return;
        }

        $targetPlace = $operation->primaryTargetPlace();
        if (is_string($targetPlace)) {
            $targetState = EtatDpeEnum::tryFrom($targetPlace);
            if (null !== $targetState) {
                $demande->setEtatDemande($targetState);
            }
        }

        if ('soumis_central' === $previousPlace) {
            $demande->setDateCloture(new \DateTime());
        }
    }
}
