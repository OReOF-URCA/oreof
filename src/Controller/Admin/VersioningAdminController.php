<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DataTable\VersioningFicheMatiereDataTable;
use App\DataTable\VersioningFormationDataTable;
use App\DataTable\VersioningParcoursDataTable;
use App\DTO\TranslatableKey;
use App\Service\Versioning\VersioningInspectorService;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/versioning', name: 'app_admin_versioning_')]
#[IsGranted('ROLE_ADMIN')]
class VersioningAdminController extends BaseController
{
    public function __construct(
        private readonly VersioningInspectorService $inspectorService,
        private readonly TurboStreamResponseFactory $turboStream,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        VersioningFormationDataTable $formationsTable,
        VersioningParcoursDataTable $parcoursTable,
        VersioningFicheMatiereDataTable $fichesTable,
    ): Response {
        $tab = $request->query->get('tab', 'formations');
        $stats = $this->inspectorService->getGlobalStats();

        $table = match ($tab) {
            'parcours' => $parcoursTable,
            'fiches_matiere' => $fichesTable,
            default => $formationsTable,
        };

        $logsData = $tab === 'logs' ? $this->inspectorService->getOrphansAndLogs() : null;

        return $this->render('admin/versioning/index.html.twig', [
            'stats' => $stats,
            'tab' => $tab,
            'table' => $table,
            'logsData' => $logsData,
        ]);
    }

    #[Route('/inspect-file', name: 'inspect_file', methods: ['GET'])]
    public function inspectFile(Request $request): Response
    {
        $path = (string) $request->query->get('path', '');
        $fileDetails = $this->inspectorService->getFileDetails($path);

        if ($fileDetails === null) {
            if ($request->getPreferredFormat() === 'turbo-stream' || $request->headers->get('Turbo-Frame') !== null) {
                return $this->turboStream->streamToastError('Fichier introuvable ou inaccessible.');
            }

            $this->addFlash('danger', 'Fichier introuvable ou inaccessible.');
            return $this->redirectToRoute('app_admin_versioning_index');
        }

        return $this->turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('admin.versioning.modal_inspect_title', ['%filename%' => $fileDetails['filename']]),
            new TranslatableKey('admin.versioning.modal_inspect_subtitle', ['%path%' => $fileDetails['relative_path']]),
            'admin/versioning/_modal_inspect_file.html.twig',
            ['file' => $fileDetails],
            'admin/versioning/_modal_inspect_footer.html.twig',
            ['file' => $fileDetails]
        );
    }

    #[Route('/download-file', name: 'download_file', methods: ['GET'])]
    public function downloadFile(Request $request): Response
    {
        $path = (string) $request->query->get('path', '');
        $fileDetails = $this->inspectorService->getFileDetails($path);

        if ($fileDetails === null || !is_file($fileDetails['full_path'])) {
            $this->addFlash('danger', 'Fichier introuvable pour le téléchargement.');
            return $this->redirectToRoute('app_admin_versioning_index');
        }

        $response = new BinaryFileResponse($fileDetails['full_path']);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileDetails['filename']
        );
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
