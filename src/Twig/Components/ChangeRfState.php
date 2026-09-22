<?php

namespace App\Twig\Components;

use App\Classes\ValidationProcessChangeRf;
use App\Entity\ChangeRf;
use App\Entity\Formation;
use App\Repository\ChangeRfRepository;
use App\Repository\HistoriqueFormationRepository;
use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationStatus;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationInspector;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent]
final class ChangeRfState
{
    use DefaultActionTrait;

    public Formation $formation;
    public array $demandes = [];
    public array $process;
    public string $place = '';
    public array $historiques = [];

    public function __construct(
        private readonly HistoriqueFormationRepository $historiqueFormationRepository,
        private readonly ChangeRfRepository $changeRfRepository,
        private readonly WorkflowInterface $changeRfWorkflow,
        private readonly ValidationProcessChangeRf $validationProcessChangeRf,
        private readonly WorkflowOperationInspector $operationInspector,
    ) {
        $this->process = $this->validationProcessChangeRf->getProcess();
    }

    #[PostMount]
    public function getDemandes(): void
    {
        $this->demandes = [];

        foreach ($this->changeRfRepository->findBy(['formation' => $this->formation], ['dateDemande' => 'DESC']) as $changeRf) {
            if (!$this->changeRfWorkflow->getMarking($changeRf)->has('effectuee')) {
                $this->demandes[] = $changeRf;
            }
        }
    }

    public function getPlace(ChangeRf $changeRf): string
    {
        return array_keys($this->changeRfWorkflow->getMarking($changeRf)->getPlaces())[0];
    }

    /**
     * Returns the currently relevant operations, already enriched with their
     * authorization, workflow availability and business-blocker status.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getOperations(ChangeRf $changeRf): array
    {
        $operations = [];
        foreach ($this->changeRfWorkflow->getDefinition()->getTransitions() as $transition) {
            $inspection = $this->operationInspector->inspect(
                $this->changeRfWorkflow,
                $changeRf,
                $transition->getName(),
            );

            if (in_array($inspection->status, [OperationStatus::Forbidden, OperationStatus::Unavailable], true)) {
                continue;
            }

            $metadata = $inspection->operation->metadata;
            if (($metadata['display'] ?? true) === false || !isset($metadata['type'])) {
                continue;
            }

            $operations[$metadata['type']][$transition->getName()] = [
                'meta' => $metadata,
                'status' => $inspection->status->value,
                'can_execute' => $inspection->canExecute(),
                'blockers' => array_map(
                    static fn (OperationBlocker $blocker): array => [
                        'code' => $blocker->code,
                        'message' => $blocker->message,
                        'path' => $blocker->path,
                        'blocking' => $blocker->isBlocking(),
                    ],
                    $inspection->blockers->all(),
                ),
            ];
        }

        return $operations;
    }

    public function getHistoriques(ChangeRf $changeRf): array
    {
        $this->historiques = [];
        $currentPlace = $this->getPlace($changeRf);
        $orderedPlaces = array_keys($this->process);
        $currentIndex = array_search($currentPlace, $orderedPlaces, true);

        foreach ($this->historiqueFormationRepository->findBy(['changeRf' => $changeRf], ['created' => 'ASC']) as $historique) {
            if (!str_starts_with($historique->getEtape(), 'changeRf.')) {
                continue;
            }

            $place = substr($historique->getEtape(), strlen('changeRf.'));
            $placeIndex = array_search($place, $orderedPlaces, true);
            if (false !== $placeIndex && (false === $currentIndex || $placeIndex < $currentIndex)) {
                $this->historiques[$historique->getEtape()] = $historique;
            }
        }

        return $this->historiques;
    }
}
