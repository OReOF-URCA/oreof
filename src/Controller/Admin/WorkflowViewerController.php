<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Workflow\Service\WorkflowExplorerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/workflows')]
#[IsGranted('ROLE_ADMIN')]
class WorkflowViewerController extends BaseController
{
    public function __construct(
        private readonly WorkflowExplorerService $workflowExplorerService,
    ) {
    }

    #[Route('', name: 'app_admin_workflow_index')]
    public function index(Request $request): Response
    {
        $selectedWorkflow = $request->query->get('workflow', 'dpeParcours');
        $availableWorkflows = $this->workflowExplorerService->getAvailableWorkflows();

        if (!array_key_exists($selectedWorkflow, $availableWorkflows)) {
            $selectedWorkflow = 'dpeParcours';
        }

        $workflowData = $this->workflowExplorerService->getWorkflowData($selectedWorkflow);

        return $this->render('admin/workflow/index.html.twig', [
            'availableWorkflows' => $availableWorkflows,
            'selectedWorkflow' => $selectedWorkflow,
            'currentWorkflow' => $availableWorkflows[$selectedWorkflow],
            'workflowData' => $workflowData,
        ]);
    }

    #[Route('/data/{workflow}', name: 'app_admin_workflow_data', methods: ['GET'])]
    public function data(string $workflow): JsonResponse
    {
        $workflowData = $this->workflowExplorerService->getWorkflowData($workflow);

        return new JsonResponse($workflowData);
    }
}
