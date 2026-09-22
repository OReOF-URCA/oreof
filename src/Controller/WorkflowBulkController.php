<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Utils\TurboStreamResponseFactory;
use App\Workflow\Bulk\BulkWorkflowManager;
use App\Workflow\Form\MetaDrivenFormFactory;
use App\Workflow\Metadata\WorkflowMetaMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/workflow/bulk', name: 'workflow_bulk_')]
final class WorkflowBulkController extends BaseController
{
    public function __construct(
        private readonly BulkWorkflowManager $bulkWorkflowManager,
        private readonly WorkflowMetaMapper $workflowMetaMapper,
        private readonly MetaDrivenFormFactory $formFactory,
    ) {
    }

    #[Route('/{workflowName}/{transition}', name: 'apply', methods: ['GET', 'POST'])]
    public function apply(
        string $workflowName,
        string $transition,
        Request $request,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        if (!$this->bulkWorkflowManager->supports($workflowName)) {
            throw $this->createNotFoundException('Workflow non pris en charge pour les traitements par lot.');
        }

        $selectedIds = $this->selectedIds($request);
        if ([] === $selectedIds) {
            return $turboStream->streamToastError('Aucun élément sélectionné.', true);
        }

        try {
            $rawMetadata = $this->bulkWorkflowManager->transitionMetadata($workflowName, $transition);
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $metadata = $this->workflowMetaMapper->fromArray($rawMetadata);
        $form = null === $metadata->form
            ? $this->formFactory->createEmpty()
            : $this->formFactory->create(
                $metadata->form,
                $transition,
                $this->bulkWorkflowManager->formOptions($workflowName, $transition, $rawMetadata),
            );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException('Utilisateur ORéOF requis.');
            }

            try {
                $result = $this->bulkWorkflowManager->execute(
                    $workflowName,
                    $transition,
                    $selectedIds,
                    $this->extractFormData($form->getData()),
                    $user,
                    $request,
                );
            } catch (\Throwable $exception) {
                return $turboStream->stream('workflow/bulk/error.stream.html.twig', [
                    'message' => $exception->getMessage(),
                ]);
            }

            return $turboStream->stream('workflow/bulk/result.stream.html.twig', [
                'transition' => $transition,
                'result' => $result,
                'subjectLabel' => $this->bulkWorkflowManager->label($workflowName),
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            'modal_title.'.$transition.'.'.$metadata->type,
            sprintf(
                '%d %s%s sélectionné%s',
                count($selectedIds),
                $this->bulkWorkflowManager->label($workflowName),
                count($selectedIds) > 1 ? 's' : '',
                count($selectedIds) > 1 ? 's' : '',
            ),
            'workflow/bulk/_form.html.twig',
            [
                'form' => $form->createView(),
                'workflowName' => $workflowName,
                'transition' => $transition,
                'metadata' => $metadata,
                'selectedIds' => implode(',', $selectedIds),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'modal_submit.'.$transition.'.'.$metadata->type,
            ],
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    /** @return list<int> */
    private function selectedIds(Request $request): array
    {
        $bag = $request->isMethod('POST') ? $request->request : $request->query;
        $ids = [];
        $parameters = $bag->all();

        // Les alias maintiennent la compatibilité avec les contrôleurs Stimulus
        // déjà compilés et avec les anciens écrans de validation.
        foreach (['ids', 'parcours', 'fiches', 'demandes'] as $parameter) {
            $value = $parameters[$parameter] ?? [];
            $raw = is_array($value) ? $value : explode(',', (string) $value);

            $ids = array_merge($ids, $raw);
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /** @return array<string, mixed> */
    private function extractFormData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        return is_object($data) ? get_object_vars($data) : [];
    }
}
