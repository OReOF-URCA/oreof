<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Controller/ConseilDocumentsController.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 29/05/2026 13:35
 */

declare(strict_types=1);

namespace App\Controller;

use App\Entity\HistoriqueParcours;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\Repository\HistoriqueParcoursRepository;
use App\Repository\ParcoursRepository;
use App\Service\DataTableBuilder;
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
    #[Route('/conseils/documents', name: 'app_conseils_documents_index', methods: ['GET'])]
    public function index(
        Request              $request,
        DataTableBuilder     $builder,
        ComposanteRepository $composanteRepository,
        FormationRepository  $formationRepository,
        ParcoursRepository   $parcoursRepository,
    ): Response
    {
        $this->denyConseilDocumentsAccess();

        [$composanteId, $formationId, $parcoursId, $hasPv, $hasJustification] = $this->extractFilters($request);

        $tableBuilder = $builder
            ->setEntity(HistoriqueParcours::class)
            ->setPerPage(20)
            ->setDefaultSort('created', 'desc');

        $this->applyTableFilters(
            $tableBuilder,
            $composanteId,
            $formationId,
            $parcoursId,
            $hasPv,
            $hasJustification,
        );

        $tableBuilder
            ->addColumn('parcours.formation.composantePorteuse.libelle', [
                'label' => 'Composante',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
            ])
            ->addColumn('parcours.formation.id', [
                'label' => 'Formation',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'conseils/documents/_datatable_formation.html.twig',
            ])
            ->addColumn('parcours.id', [
                'label' => 'Parcours',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'conseils/documents/_datatable_parcours.html.twig',
            ])
            ->addColumn('etape', [
                'label' => 'Étape',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
            ])
            ->addColumn('created', [
                'label' => 'Date de création',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'type' => 'date',
                'format' => 'datetime',
            ])
            ->addColumn('id', [
                'id' => 'hasPv',
                'label' => 'PV',
                'sortable' => false,
                'filterable' => true,
                'searchable' => false,
                'type' => 'select',
                'choices' => ['1' => 'Avec PV', '0' => 'Sans PV'],
                'filter_expression' => "CASE WHEN e.complements LIKE '%\\\"fichier\\\"%' THEN '1' ELSE '0' END",
                'template' => 'conseils/documents/_datatable_pv.html.twig',
            ])
            ->addColumn('id', [
                'id' => 'hasJustification',
                'label' => 'Justificatif',
                'sortable' => false,
                'filterable' => true,
                'searchable' => false,
                'type' => 'select',
                'choices' => ['1' => 'Avec justificatif', '0' => 'Sans justificatif'],
                'filter_expression' => "CASE WHEN e.complements LIKE '%\\\"fichier_note\\\"%' THEN '1' ELSE '0' END",
                'template' => 'conseils/documents/_datatable_justification.html.twig',
            ]);

        $composantes = $composanteRepository->findBy([], ['libelle' => 'ASC']);
        $formations = $formationRepository->findBy(
            $composanteId !== null ? ['composantePorteuse' => $composanteId] : [],
            ['sigle' => 'ASC'],
        );
        $parcours = $parcoursRepository->findBy(
            $formationId !== null ? ['formation' => $formationId] : [],
            ['libelle' => 'ASC'],
        );

        return $this->render('conseils/documents/index.html.twig', [
            'table' => $tableBuilder->build(),
            'composantes' => $composantes,
            'formations' => $formations,
            'parcoursList' => $parcours,
            'selectedComposanteId' => $composanteId,
            'selectedFormationId' => $formationId,
            'selectedParcoursId' => $parcoursId,
            'selectedHasPv' => $hasPv,
            'selectedHasJustification' => $hasJustification,
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
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: ?string, 4: ?string}
     */
    private function extractFilters(Request $request): array
    {
        $composanteId = $request->query->getInt('composante') ?: null;
        $formationId = $request->query->getInt('formation') ?: null;
        $parcoursId = $request->query->getInt('parcours') ?: null;
        $hasPv = $request->query->get('hasPv');
        $hasJustification = $request->query->get('hasJustification');

        $hasPv = in_array($hasPv, ['0', '1'], true) ? $hasPv : null;
        $hasJustification = in_array($hasJustification, ['0', '1'], true) ? $hasJustification : null;

        return [$composanteId, $formationId, $parcoursId, $hasPv, $hasJustification];
    }

    /**
     * Applique les filtres au DataTableBuilder.
     */
    private function applyTableFilters(
        DataTableBuilder $tableBuilder,
        ?int             $composanteId,
        ?int             $formationId,
        ?int             $parcoursId,
        ?string          $hasPv,
        ?string          $hasJustification,
    ): void
    {
        $tableBuilder->addBaseWhere('e.parcours IS NOT NULL');

        // Toujours faire les joins sur parcours et formation si des filtres les nécessitent
        $needsFormationJoin = $this->getCampagneCollecte() !== null
            || $composanteId !== null
            || $formationId !== null;
        $needsParcoursJoin = $needsFormationJoin || $parcoursId !== null;

        if ($needsParcoursJoin) {
            $tableBuilder->addBaseJoin('inner', 'e.parcours', 'parcours');
        }

        if ($needsFormationJoin) {
            $tableBuilder->addBaseJoin('inner', 'parcours.formation', 'formation');
        }

        if ($this->getCampagneCollecte() !== null) {
            $tableBuilder
                ->addBaseWhere('formation.dpe = :campagneCollecte')
                ->addBaseParameter('campagneCollecte', $this->getCampagneCollecte());
        }

        if ($composanteId !== null) {
            $tableBuilder
                ->addBaseJoin('left', 'formation.composantePorteuse', 'composante')
                ->addBaseWhere('composante.id = :composanteId')
                ->addBaseParameter('composanteId', $composanteId);
        }

        if ($formationId !== null) {
            $tableBuilder
                ->addBaseWhere('formation.id = :formationId')
                ->addBaseParameter('formationId', $formationId);
        }

        if ($parcoursId !== null) {
            $tableBuilder
                ->addBaseWhere('parcours.id = :parcoursId')
                ->addBaseParameter('parcoursId', $parcoursId);
        }

        if ($hasPv === '1') {
            $tableBuilder
                ->addBaseWhere("e.complements LIKE :hasPvKey")
                ->addBaseParameter('hasPvKey', '%"fichier"%');
        } elseif ($hasPv === '0') {
            $tableBuilder
                ->addBaseWhere("(e.complements IS NULL OR e.complements NOT LIKE :hasPvKey)")
                ->addBaseParameter('hasPvKey', '%"fichier"%');
        }

        if ($hasJustification === '1') {
            $tableBuilder
                ->addBaseWhere("e.complements LIKE :hasJustificationKey")
                ->addBaseParameter('hasJustificationKey', '%"fichier_note"%');
        } elseif ($hasJustification === '0') {
            $tableBuilder
                ->addBaseWhere("(e.complements IS NULL OR e.complements NOT LIKE :hasJustificationKey)")
                ->addBaseParameter('hasJustificationKey', '%"fichier_note"%');
        }
    }

    #[Route('/conseils/documents/download', name: 'app_conseils_documents_download', methods: ['GET'])]
    public function download(
        Request                      $request,
        HistoriqueParcoursRepository $historiqueParcoursRepository,
        SecureUploadService          $secureUploadService,
    ): BinaryFileResponse
    {
        $this->denyConseilDocumentsAccess();

        [$composanteId, $formationId, $parcoursId, $hasPv, $hasJustification] = $this->extractFilters($request);

        $historiques = $this->getFilteredHistoriques(
            $historiqueParcoursRepository,
            $composanteId,
            $formationId,
            $parcoursId,
            $hasPv,
            $hasJustification,
        );

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
        $downloadName = 'conseils-documents-' . $timestamp . '.zip';

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
     * Récupère les historiques filtrés selon les critères fournis.
     *
     * @return list<HistoriqueParcours>
     */
    private function getFilteredHistoriques(
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

        return array_filter($historiques, function (HistoriqueParcours $historique) use ($hasPv, $hasJustification): bool {
            $complements = $historique->getComplements() ?? [];
            if (!is_array($complements)) {
                $complements = [];
            }

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
        });
    }

    /**
     * @param list<HistoriqueParcours> $historiques
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $historiques): array
    {
        $rows = [];

        foreach ($historiques as $historique) {
            $complements = $historique->getComplements() ?? [];
            if (!is_array($complements)) {
                continue;
            }

            $hasPv = isset($complements['fichier']) && is_string($complements['fichier']) && $complements['fichier'] !== '';
            $hasJustification = isset($complements['fichier_note']) && is_string($complements['fichier_note']) && $complements['fichier_note'] !== '';

            $parcours = $historique->getParcours();
            $formation = $parcours?->getFormation();
            $composante = $formation?->getComposantePorteuse();

            $rows[] = [
                'historique' => $historique,
                'composante' => $composante?->getLibelle() ?? '-',
                'formation' => $formation?->getDisplay() ?? '-',
                'parcours' => $parcours?->getDisplay() ?? '-',
                'etape' => (string)($historique->getEtape() ?? '-'),
                'date' => $historique->getDate() ?? $historique->getCreated(),
                'fichier' => $hasPv ? $complements['fichier'] : null,
                'fichier_original' => $complements['fichier_original'] ?? null,
                'fichier_note' => $hasJustification ? $complements['fichier_note'] : null,
                'fichier_note_original' => $complements['fichier_note_original'] ?? null,
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
        $parcours = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$row['parcours']) ?: 'parcours';
        $entryName = $prefix . '/' . $date . '__' . $formation . '__' . $parcours . '__' . $safeOriginal;

        $uniqueName = $entryName;
        $i = 1;
        while ($zip->locateName($uniqueName) !== false) {
            $uniqueName = $prefix . '/' . $date . '__' . $formation . '__' . $parcours . '__' . $i . '__' . $safeOriginal;
            ++$i;
        }

        $zip->addFile($path, $uniqueName);

        return 1;
    }
}

