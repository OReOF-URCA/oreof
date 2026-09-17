<?php

namespace App\Twig\Components;

use App\Classes\ValidationProcessChangeRf;
use App\Entity\ChangeRf;
use App\Entity\Formation;
use App\Repository\HistoriqueFormationRepository;
use Dannebicque\WorkflowOperationsBundle\Model\OperationBlocker;
use Dannebicque\WorkflowOperationsBundle\Model\OperationStatus;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationInspector;
use Symfony\Bundle\SecurityBundle\Security;
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

    public const TAB_PROCESS = [
        'changeRf.soumis_conseil' => 0,
        'changeRf.soumis_ses' => 1,
        'changeRf.soumis_cfvu' => 2,
        'changeRf.attente_pv' => 2,
    ];

    public function __construct(
        private readonly HistoriqueFormationRepository $historiqueFormationRepository,
        private readonly WorkflowInterface $changeRfWorkflow,
        private readonly ValidationProcessChangeRf $validationProcessChangeRf,
        private readonly WorkflowOperationInspector $operationInspector,
        private readonly Security $security,
    ) {
        $this->process = $this->validationProcessChangeRf->getProcess();
    }

    #[PostMount]
    public function getDemandes(): void
    {
        foreach ($this->formation->getChangeRves() as $changeRf) {
            if (!$this->changeRfWorkflow->getMarkingStore()->getMarking($changeRf)->has('effectuee')) {
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
        if (!$this->canManage($changeRf)) {
            return [];
        }

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

    private function canManage(ChangeRf $changeRf): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $place = $this->getPlace($changeRf);
        if (in_array($place, ['soumis_ses', 'soumis_cfvu'], true)) {
            return false;
        }

        $formation = $changeRf->getFormation();
        if (null === $formation) {
            return false;
        }

        return $this->security->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $formation,
        ]) || $this->security->isGranted('MANAGE', [
            'route' => 'app_formation',
            'subject' => $formation,
        ]);
    }

    public function getHistoriques(ChangeRf $changeRf): array
    {
        $historiques = $this->historiqueFormationRepository->findBy(['changeRf' => $changeRf], ['created' => 'ASC']);

        foreach ($historiques as $historique) {
            if (str_starts_with($historique->getEtape(), 'changeRf.')) {
                if (self::TAB_PROCESS[$historique->getEtape()] < self::TAB_PROCESS['changeRf.'.$this->getPlace($changeRf)]) {
                    $this->historiques[$historique->getEtape()] = $historique;
                }
            }
        }
        return $this->historiques;
    }
}
