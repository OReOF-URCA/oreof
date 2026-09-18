<?php

declare(strict_types=1);

namespace App\Workflow\Bulk;

use App\Entity\ChangeRf;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Events\HistoriqueFicheMatiereEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\ChangeRfRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FicheMatiereRepository;
use App\Service\SecureUploadService;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationContextNormalizer;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class BulkWorkflowManager
{
    public const DPE_PARCOURS = 'dpeParcours';
    public const FICHE = 'fiche';
    public const CHANGE_RF = 'changeRf';

    public function __construct(
        private DpeParcoursRepository $dpeParcoursRepository,
        private FicheMatiereRepository $ficheMatiereRepository,
        private ChangeRfRepository $changeRfRepository,
        private WorkflowOperationExecutor $operationExecutor,
        private OperationContextNormalizer $contextNormalizer,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private SecureUploadService $secureUploadService,
        #[Target('dpeParcours')]
        private WorkflowInterface $dpeParcoursWorkflow,
        #[Target('fiche')]
        private WorkflowInterface $ficheWorkflow,
        #[Target('changeRf')]
        private WorkflowInterface $changeRfWorkflow,
    ) {
    }

    public function supports(string $workflowName): bool
    {
        return in_array($workflowName, [self::DPE_PARCOURS, self::FICHE, self::CHANGE_RF], true);
    }

    public function label(string $workflowName): string
    {
        return match ($workflowName) {
            self::DPE_PARCOURS => 'parcours',
            self::FICHE => 'fiche matière',
            self::CHANGE_RF => 'demande de changement de responsable',
            default => throw new \InvalidArgumentException('Workflow non pris en charge.'),
        };
    }

    /** @return array<string, mixed> */
    public function transitionMetadata(string $workflowName, string $transitionName): array
    {
        $workflow = $this->workflow($workflowName);

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            if ($transitionName === $transition->getName()) {
                return $workflow->getMetadataStore()->getTransitionMetadata($transition);
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'La transition "%s" n’existe pas dans le workflow "%s".',
            $transitionName,
            $workflowName,
        ));
    }

    /** @return array<string, mixed> */
    public function formOptions(string $workflowName, string $transitionName, array $metadata): array
    {
        if (self::CHANGE_RF !== $workflowName) {
            return [];
        }

        return [
            'meta' => $metadata,
            'transition' => $transitionName,
        ];
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $formData
     */
    public function execute(
        string $workflowName,
        string $transitionName,
        array $ids,
        array $formData,
        UserInterface $actor,
        Request $request,
    ): BulkWorkflowResult {
        $workflow = $this->workflow($workflowName);
        $metadata = $this->transitionMetadata($workflowName, $transitionName);
        [$input, $sharedRuntime] = $this->prepareInput($workflowName, $formData);
        $processed = [];
        $rejected = [];

        foreach ($ids as $id) {
            $subject = $this->findSubject($workflowName, $id);
            if (null === $subject) {
                $rejected[] = [
                    'id' => $id,
                    'label' => sprintf('%s #%d', ucfirst($this->label($workflowName)), $id),
                    'message' => 'Élément introuvable.',
                ];
                continue;
            }

            $label = $this->subjectLabel($subject);
            $previousPlace = array_key_first($workflow->getMarking($subject)->getPlaces()) ?? 'inconnue';

            try {
                $runtime = array_merge($sharedRuntime, ['previous_place' => $previousPlace]);
                $context = $this->contextNormalizer->normalize(
                    workflow: $workflow,
                    transitionName: $transitionName,
                    actor: $actor,
                    input: $input,
                )->withRuntime($runtime);

                $this->operationExecutor->execute($workflow, $subject, $transitionName, $context);
                $this->dispatchHistory(
                    $subject,
                    $actor,
                    $previousPlace,
                    (string) ($metadata['type'] ?? 'valide'),
                    $request,
                    $input,
                    $runtime,
                );
                $this->entityManager->flush();

                $processed[] = ['id' => $id, 'label' => $label];
            } catch (\Throwable $exception) {
                $rejected[] = [
                    'id' => $id,
                    'label' => $label,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return new BulkWorkflowResult($processed, $rejected);
    }

    private function workflow(string $workflowName): WorkflowInterface
    {
        return match ($workflowName) {
            self::DPE_PARCOURS => $this->dpeParcoursWorkflow,
            self::FICHE => $this->ficheWorkflow,
            self::CHANGE_RF => $this->changeRfWorkflow,
            default => throw new \InvalidArgumentException('Workflow non pris en charge.'),
        };
    }

    private function findSubject(string $workflowName, int $id): DpeParcours|FicheMatiere|ChangeRf|null
    {
        return match ($workflowName) {
            self::DPE_PARCOURS => $this->dpeParcoursRepository->find($id),
            self::FICHE => $this->ficheMatiereRepository->find($id),
            self::CHANGE_RF => $this->changeRfRepository->find($id),
            default => null,
        };
    }

    private function subjectLabel(DpeParcours|FicheMatiere|ChangeRf $subject): string
    {
        return match (true) {
            $subject instanceof DpeParcours => $subject->getParcours()?->getDisplay() ?? sprintf('Parcours #%d', $subject->getId()),
            $subject instanceof FicheMatiere => $subject->getLibelle(),
            $subject instanceof ChangeRf => $subject->getFormation()?->getDisplay() ?? sprintf('Demande #%d', $subject->getId()),
        };
    }

    /**
     * @param array<string, mixed> $formData
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function prepareInput(string $workflowName, array $formData): array
    {
        $runtime = [];

        if (self::DPE_PARCOURS === $workflowName) {
            $pv = $this->upload($formData['uploadPv'] ?? null);
            $note = $this->upload($formData['uploadArgumentaire'] ?? null);
            unset($formData['uploadPv'], $formData['uploadArgumentaire']);
            $runtime = [
                'pv_file_name' => $pv['stored'] ?? null,
                'pv_original_file_name' => $pv['original'] ?? null,
                'note_file_name' => $note['stored'] ?? null,
                'note_original_file_name' => $note['original'] ?? null,
            ];
        }

        if (self::CHANGE_RF === $workflowName) {
            $file = $this->upload($formData['file'] ?? null);
            unset($formData['file']);
            $runtime = [
                'file_name' => $file['stored'] ?? '',
                'original_file_name' => $file['original'] ?? null,
            ];
        }

        return [$formData, $runtime];
    }

    /** @return array{stored: string, original: string}|null */
    private function upload(mixed $file): ?array
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        $uploaded = $this->secureUploadService->upload($file, 'conseils');

        return [
            'stored' => $uploaded->getStoredFilename(),
            'original' => $uploaded->getOriginalFilename(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $runtime
     */
    private function dispatchHistory(
        DpeParcours|FicheMatiere|ChangeRf $subject,
        UserInterface $actor,
        string $previousPlace,
        string $operationType,
        Request $request,
        array $input,
        array $runtime,
    ): void {
        if ($subject instanceof DpeParcours && null !== $subject->getParcours()) {
            $event = new HistoriqueParcoursEvent(
                $subject->getParcours(),
                $actor,
                $previousPlace,
                $operationType,
                $request,
                $runtime['pv_file_name'] ?? null,
                $runtime['note_file_name'] ?? null,
                $runtime['pv_original_file_name'] ?? null,
                $runtime['note_original_file_name'] ?? null,
                $input,
            );
            $this->eventDispatcher->dispatch($event, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
        }

        if ($subject instanceof FicheMatiere) {
            $event = new HistoriqueFicheMatiereEvent(
                $subject,
                $actor,
                $previousPlace,
                $operationType,
                $request,
            );
            $this->eventDispatcher->dispatch($event, HistoriqueFicheMatiereEvent::ADD_HISTORIQUE_FICHE_MATIERE);
        }
    }
}
