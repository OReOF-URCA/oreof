<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Controller/ConseilDocumentsController.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 30/09/2026 14:30
 */

declare(strict_types=1);

namespace App\Controller;

use App\DataTable\ConseilDocumentsChangeRfDataTable;
use App\DataTable\ConseilDocumentsDpeFormationDataTable;
use App\DataTable\ConseilDocumentsDpeParcoursDataTable;
use App\Entity\HistoriqueFormation;
use App\Entity\HistoriqueParcours;
use App\Repository\HistoriqueFormationRepository;
use App\Repository\HistoriqueParcoursRepository;
use App\Service\SecureUploadService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ConseilDocumentsController extends BaseController
{
    #[Route('/conseils/documents', name: 'app_conseils_documents_index', methods: ['GET', 'POST'])]
    public function index(
        Request                               $request,
        ConseilDocumentsDpeParcoursDataTable  $parcoursTable,
        ConseilDocumentsDpeFormationDataTable $formationTable,
        ConseilDocumentsChangeRfDataTable     $changeRfTable,
    ): Response
    {
        $this->denyConseilDocumentsAccess();

        $process = (string) $request->query->get('process', 'dpe_parcours');
        if (!in_array($process, ['dpe_parcours', 'dpe_formation', 'change_rf'], true)) {
            $process = 'dpe_parcours';
        }

        $table = match ($process) {
            'dpe_formation' => $formationTable,
            'change_rf' => $changeRfTable,
            default => $parcoursTable,
        };

        return $this->render('conseils/documents/index.html.twig', [
            'table' => $table,
            'selectedProcess' => $process,
        ]);
    }

    private function denyConseilDocumentsAccess(): void
    {
        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('EDIT', ['route' => 'app_etablissement', 'subject' => 'etablissement'])
        ) {
            throw new AccessDeniedException('Vous n\'avez pas les droits pour accéder à cette page.');
        }
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?int, 3: ?int, 4: ?string, 5: ?string}
     */
    private function extractFilters(Request $request): array
    {
        $process = (string) $request->query->get('process', 'dpe_parcours');
        if (!in_array($process, ['dpe_parcours', 'dpe_formation', 'change_rf'], true)) {
            $process = 'dpe_parcours';
        }

        $composanteId = $request->query->getInt('composante') ?: null;
        $formationId = $request->query->getInt('formation') ?: null;
        $parcoursId = $request->query->getInt('parcours') ?: null;
        $hasPv = $request->query->get('hasPv');
        $hasJustification = $request->query->get('hasJustification');

        $hasPv = in_array($hasPv, ['0', '1'], true) ? $hasPv : null;
        $hasJustification = in_array($hasJustification, ['0', '1'], true) ? $hasJustification : null;

        return [$process, $composanteId, $formationId, $parcoursId, $hasPv, $hasJustification];
    }

    #[Route('/conseils/documents/download', name: 'app_conseils_documents_download', methods: ['GET'])]
    public function download(
        Request                       $request,
        HistoriqueParcoursRepository  $historiqueParcoursRepository,
        HistoriqueFormationRepository $historiqueFormationRepository,
        SecureUploadService           $secureUploadService,
    ): BinaryFileResponse
    {
        $this->denyConseilDocumentsAccess();

        [$process, $composanteId, $formationId, $parcoursId, $hasPv, $hasJustification] = $this->extractFilters($request);

        if ($process === 'dpe_parcours') {
            $historiques = $this->getFilteredParcoursHistoriques(
                $historiqueParcoursRepository,
                $composanteId,
                $formationId,
                $parcoursId,
                $hasPv,
                $hasJustification,
            );
        } else {
            $historiques = $this->getFilteredFormationHistoriques(
                $historiqueFormationRepository,
                $composanteId,
                $formationId,
                $process,
                $hasPv,
                $hasJustification,
            );
        }

        $rows = $this->buildRows($historiques);

        $zipPath = tempnam(sys_get_temp_dir(), 'oreof_conseils_');
        if ($zipPath === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive ZIP.');
        }

        $added = 0;
        foreach ($rows as $row) {
            $added += $this->addDocumentToZip($zip, $secureUploadService, $row, 'fichier', 'fichier_original', 'pv');
            $added += $this->addDocumentToZip($zip, $secureUploadService, $row, 'fichier_note', 'fichier_note_original', 'justification');
        }

        $zip->close();

        $timestamp = (new DateTimeImmutable())->format('Ymd_His');
        $downloadName = 'conseils-documents-' . $process . '-' . $timestamp . '.zip';

        if ($added === 0) {
            unlink($zipPath);
            throw $this->createNotFoundException('Aucun document disponible pour les filtres sélectionnés.');
        }

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * @return list<HistoriqueParcours>
     */
    private function getFilteredParcoursHistoriques(
        HistoriqueParcoursRepository $historiqueParcoursRepository,
        ?int                         $composanteId,
        ?int                         $formationId,
        ?int                         $parcoursId,
        ?string                      $hasPv,
        ?string                      $hasJustification,
    ): array
    {
        $historiques = $historiqueParcoursRepository->findForConseilDocuments(
            $this->getCampagneCollecte(),
            $composanteId,
            $formationId,
            $parcoursId,
        );

        return array_values(array_filter($historiques, function (HistoriqueParcours $historique) use ($hasPv, $hasJustification): bool {
            $complements = $historique->getComplements() ?? [];

            $currentHasPv = isset($complements['fichier']) && is_string($complements['fichier']) && $complements['fichier'] !== '';
            $currentHasJustification = isset($complements['fichier_note']) && is_string($complements['fichier_note']) && $complements['fichier_note'] !== '';

            if ($hasPv === '1' && !$currentHasPv) {
                return false;
            }
            if ($hasPv === '0' && $currentHasPv) {
                return false;
            }
            if ($hasJustification === '1' && !$currentHasJustification) {
                return false;
            }
            if ($hasJustification === '0' && $currentHasJustification) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return list<HistoriqueFormation>
     */
    private function getFilteredFormationHistoriques(
        HistoriqueFormationRepository $historiqueFormationRepository,
        ?int                          $composanteId,
        ?int                          $formationId,
        string                        $processType,
        ?string                       $hasPv,
        ?string                       $hasJustification,
    ): array
    {
        $historiques = $historiqueFormationRepository->findForConseilDocuments(
            $this->getCampagneCollecte(),
            $composanteId,
            $formationId,
            $processType,
        );

        return array_values(array_filter($historiques, function (HistoriqueFormation $historique) use ($hasPv, $hasJustification): bool {
            $complements = $historique->getComplements() ?? [];

            $currentFichier = $complements['fichier'] ?? $historique->getDocumentPv()?->getFilename();
            $currentFichierNote = $complements['fichier_note'] ?? $historique->getDocumentNote()?->getFilename();

            $currentHasPv = is_string($currentFichier) && $currentFichier !== '';
            $currentHasJustification = is_string($currentFichierNote) && $currentFichierNote !== '';

            if ($hasPv === '1' && !$currentHasPv) {
                return false;
            }
            if ($hasPv === '0' && $currentHasPv) {
                return false;
            }
            if ($hasJustification === '1' && !$currentHasJustification) {
                return false;
            }
            if ($hasJustification === '0' && $currentHasJustification) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<HistoriqueParcours|HistoriqueFormation> $historiques
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $historiques): array
    {
        $rows = [];

        foreach ($historiques as $historique) {
            $complements = $historique->getComplements() ?? [];

            if ($historique instanceof HistoriqueParcours) {
                $parcours = $historique->getParcours();
                $formation = $parcours?->getFormation();
                $composante = $formation?->getComposantePorteuse();
                $parcoursDisplay = $parcours?->getDisplay() ?? '-';

                $fichier = $complements['fichier'] ?? null;
                $fichierOriginal = $complements['fichier_original'] ?? null;
                $fichierNote = $complements['fichier_note'] ?? null;
                $fichierNoteOriginal = $complements['fichier_note_original'] ?? null;
            } else {
                $parcoursDisplay = null;
                $formation = $historique->getFormation();
                $composante = $formation?->getComposantePorteuse();

                $fichier = $complements['fichier'] ?? $historique->getDocumentPv()?->getFilename();
                $fichierOriginal = $complements['fichier_original'] ?? $historique->getDocumentPv()?->getOriginalFilename() ?? $fichier;
                $fichierNote = $complements['fichier_note'] ?? $historique->getDocumentNote()?->getFilename();
                $fichierNoteOriginal = $complements['fichier_note_original'] ?? $historique->getDocumentNote()?->getOriginalFilename() ?? $fichierNote;
            }

            $hasPv = is_string($fichier) && $fichier !== '';
            $hasJustification = is_string($fichierNote) && $fichierNote !== '';

            $rows[] = [
                'historique' => $historique,
                'composante' => $composante?->getLibelle() ?? '-',
                'formation' => $formation?->getDisplay() ?? '-',
                'parcours' => $parcoursDisplay,
                'etape' => (string)($historique->getEtape() ?? '-'),
                'date' => $historique->getDate() ?? $historique->getCreated(),
                'fichier' => $hasPv ? $fichier : null,
                'fichier_original' => $fichierOriginal,
                'fichier_note' => $hasJustification ? $fichierNote : null,
                'fichier_note_original' => $fichierNoteOriginal,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function addDocumentToZip(
        \ZipArchive         $zip,
        SecureUploadService $secureUploadService,
        array               $row,
        string              $storedKey,
        string              $originalKey,
        string              $prefix,
    ): int
    {
        $stored = $row[$storedKey] ?? null;
        if (!is_string($stored) || $stored === '') {
            return 0;
        }

        try {
            $path = $secureUploadService->resolveStoredFilePath('conseils', $stored);
        } catch (\Throwable) {
            return 0;
        }

        if (!is_file($path)) {
            return 0;
        }

        $safeOriginal = $secureUploadService->getDownloadFilename(
            is_string($row[$originalKey] ?? null) ? $row[$originalKey] : null,
            $stored,
        );

        $date = $row['date'] instanceof \DateTimeInterface ? $row['date']->format('Y-m-d') : 'date-inconnue';
        $formation = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$row['formation']) ?: 'formation';
        $parcours = !empty($row['parcours']) && $row['parcours'] !== '-' ? preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$row['parcours']) : null;

        if ($parcours !== null) {
            $entryName = $prefix . '/' . $date . '__' . $formation . '__' . $parcours . '__' . $safeOriginal;
        } else {
            $entryName = $prefix . '/' . $date . '__' . $formation . '__' . $safeOriginal;
        }

        $uniqueName = $entryName;
        $i = 1;
        while ($zip->locateName($uniqueName) !== false) {
            if ($parcours !== null) {
                $uniqueName = $prefix . '/' . $date . '__' . $formation . '__' . $parcours . '__' . $i . '__' . $safeOriginal;
            } else {
                $uniqueName = $prefix . '/' . $date . '__' . $formation . '__' . $i . '__' . $safeOriginal;
            }
            ++$i;
        }

        $zip->addFile($path, $uniqueName);

        return 1;
    }
}
