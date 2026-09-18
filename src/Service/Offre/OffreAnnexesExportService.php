<?php

declare(strict_types=1);

namespace App\Service\Offre;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmission;
use App\Entity\PlateformeAdmissionParametre;
use App\Entity\TypeDiplome;
use App\Entity\TypeDiplomePlateformeAdmission;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\AnneeRepository;
use App\Repository\CampagneCollecteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;
use ZipArchive;

final class OffreAnnexesExportService
{
    public function __construct(
        private readonly SpreadsheetRenderer $spreadsheetRenderer,
        private readonly DpeParcoursRepository $dpeParcoursRepository,
        private readonly AnneeRepository $anneeRepository,
        private readonly PlateformeAdmissionParametreRepository $plateformeParamRepository,
        private readonly TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository,
        private readonly PlateformeAdmissionRepository $plateformeAdmissionRepository,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
        private readonly Environment $twig,
    ) {}

    /**
     * @return array<int, PlateformeAdmission>
     */
    public function getActivePlatforms(): array
    {
        /** @var array<int, PlateformeAdmission> $platforms */
        $platforms = $this->plateformeAdmissionRepository->findBy(['active' => true], ['libelle' => 'ASC']);
        return $platforms;
    }

    /**
     * Get platform template name based on platform code, with fallback.
     */
    public function getTemplateForPlatform(PlateformeAdmission $plateforme): string
    {
        $code = strtolower($plateforme->getCode() ?? '');
        $specificTemplate = sprintf('offre_v2/export/plateforme_%s.html.twig', $code);

        if ($this->twig->getLoader()->exists($specificTemplate)) {
            return $specificTemplate;
        }

        return 'offre_v2/export/plateforme_default.html.twig';
    }

    /**
     * Returns structured export metadata for all active platforms to render in the modal.
     *
     * @return array<int, array{
     *     plateforme: PlateformeAdmission,
     *     code: string,
     *     libelle: string,
     *     color: string,
     *     modeExport: string,
     *     isExportParDiplome: bool,
     *     template: string,
     *     typesDiplome: array<int, TypeDiplome>,
     *     composantes: array<int, Composante>,
     *     totalRows: int
     * }>
     */
    public function getPlatformsExportData(CampagneCollecte $campagne, ?Composante $composante = null): array
    {
        $platforms = $this->getActivePlatforms();
        $result = [];

        foreach ($platforms as $platform) {
            $code = strtolower($platform->getCode() ?? '');
            $typesDiplome = $this->getTypesDiplomeForPlatform($platform, $campagne, $composante);
            $composantes = $this->getComposantesForPlatform($platform, $campagne);
            $data = $this->getDataForPlatform($platform, $campagne, $composante);
            $rowsCount = count($data['rows'] ?? []);

            $color = $platform->getColor() ?: 'primary';
            if ($color === 'danger') {
                $color = 'rose';
            }

            $result[] = [
                'plateforme' => $platform,
                'code' => $code,
                'libelle' => $platform->getLibelle() ?? ucfirst($code),
                'color' => $color,
                'modeExport' => $platform->getModeExport(),
                'isExportParDiplome' => $platform->isExportParDiplome(),
                'template' => $this->getTemplateForPlatform($platform),
                'typesDiplome' => $typesDiplome,
                'composantes' => $composantes,
                'totalRows' => $rowsCount,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, TypeDiplome>
     */
    public function getTypesDiplomeForPlatform(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?Composante $composante = null,
    ): array {
        $allDpeParcours = $this->dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);
        $tpaByTypeDiplome = $this->typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne);

        $hasAnyTpasForPlatform = $this->hasAnyTpasForPlatform($tpaByTypeDiplome, $plateforme);

        $typesMap = [];
        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            $typeDiplome = $formation?->getTypeDiplome();

            if (!$parcours || !$formation || !$typeDiplome || isset($typesMap[$typeDiplome->getId()])) {
                continue;
            }

            $tpas = $tpaByTypeDiplome[$typeDiplome->getId()] ?? [];
            if ($this->isFormationRelevantForPlatform($formation, $parcours, $typeDiplome, $plateforme, $tpas, $hasAnyTpasForPlatform)) {
                $typesMap[$typeDiplome->getId()] = $typeDiplome;
            }
        }

        $list = array_values($typesMap);
        usort($list, static fn(TypeDiplome $a, TypeDiplome $b) => strcmp($a->getLibelle() ?? '', $b->getLibelle() ?? ''));
        return $list;
    }

    /**
     * @return array<int, Composante>
     */
    public function getComposantesForPlatform(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
    ): array {
        $allDpeParcours = $this->dpeParcoursRepository->findByCampagneCollecte($campagne);
        $tpaByTypeDiplome = $this->typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne);

        $hasAnyTpasForPlatform = $this->hasAnyTpasForPlatform($tpaByTypeDiplome, $plateforme);

        $composantesMap = [];
        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            $typeDiplome = $formation?->getTypeDiplome();
            $composante = $formation?->getComposantePorteuse();

            if (!$parcours || !$formation || !$composante || isset($composantesMap[$composante->getId()])) {
                continue;
            }

            $tpas = $typeDiplome ? ($tpaByTypeDiplome[$typeDiplome->getId()] ?? []) : [];
            if ($this->isFormationRelevantForPlatform($formation, $parcours, $typeDiplome, $plateforme, $tpas, $hasAnyTpasForPlatform)) {
                $composantesMap[$composante->getId()] = $composante;
            }
        }

        $list = array_values($composantesMap);
        usort($list, static fn(Composante $a, Composante $b) => strcmp($a->getSigle() ?: $a->getLibelle() ?: '', $b->getSigle() ?: $b->getLibelle() ?: ''));
        return $list;
    }

    /**
     * @param array<int, list<TypeDiplomePlateformeAdmission>> $tpaByTypeDiplome
     */
    private function hasAnyTpasForPlatform(array $tpaByTypeDiplome, PlateformeAdmission $plateforme): bool
    {
        foreach ($tpaByTypeDiplome as $tpaList) {
            foreach ($tpaList as $tpa) {
                if ($tpa->getPlateforme()?->getId() === $plateforme->getId()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if a formation is relevant for a given platform based on TypeDiplomePlateformeAdmission.
     *
     * @param list<TypeDiplomePlateformeAdmission> $tpas
     */
    private function isFormationRelevantForPlatform(
        Formation $formation,
        Parcours $parcours,
        ?TypeDiplome $typeDiplome,
        PlateformeAdmission $plateforme,
        array $tpas,
        bool $hasAnyTpasForPlatform = true,
    ): bool {
        if ($typeDiplome === null) {
            return false;
        }

        // 1. Direct association in database via TypeDiplomePlateformeAdmission
        foreach ($tpas as $tpa) {
            if ($tpa->getPlateforme()?->getId() === $plateforme->getId()) {
                return true;
            }
        }

        // 2. Fallback only if no associations exist at all in database for this platform
        if (!$hasAnyTpasForPlatform) {
            $code = strtolower($plateforme->getCode() ?? '');
            $diplCode = strtoupper($typeDiplome->getLibelleCourt() ?? '');
            return match ($code) {
                'psup', 'parcoursup' => in_array($diplCode, ['L', 'LICENCE', 'BUT', 'DEUST', 'DU', 'CUPGE', 'LAS'], true),
                'mm', 'monmaster' => in_array($diplCode, ['M', 'MASTER', 'MA'], true),
                'ec', 'ecandidat' => true,
                'eef' => in_array($diplCode, ['M', 'MASTER', 'MA', 'L', 'LICENCE', 'LP', 'BUT'], true),
                default => true,
            };
        }

        return false;
    }

    /**
     * Retrieve the previous campaign (N-1).
     */
    public function getPreviousCampagne(CampagneCollecte $campagne): ?CampagneCollecte
    {
        $annee = $campagne->getAnnee();
        if ($annee !== null) {
            $campagneN1 = $this->campagneCollecteRepository->findOneBy(['annee' => $annee - 1]);
            if ($campagneN1 instanceof CampagneCollecte) {
                return $campagneN1;
            }

            /** @var CampagneCollecte|null $previous */
            $previous = $this->campagneCollecteRepository->createQueryBuilder('c')
                ->where('c.annee < :annee')
                ->setParameter('annee', $annee)
                ->orderBy('c.annee', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $previous;
        }

        return null;
    }

    /**
     * Prepares data context for the platform export with real N and N-1 data.
     *
     * @return array<string, mixed>
     */
    public function getDataForPlatform(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?Composante $composante = null,
        ?TypeDiplome $typeDiplome = null,
    ): array {
        $annee = $campagne->getAnnee() ?? (int)date('Y');
        $anneeN = sprintf('%d-%d', $annee, $annee + 1);
        $anneeN1 = sprintf('%d-%d', $annee - 1, $annee);

        // Pre-load Campaign N data
        $allDpeParcours = $this->dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);
        $anneesByParcours = $this->anneeRepository->findByCampagneIndexedByParcours($campagne);
        $tpaByTypeDiplome = $this->typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne);
        $paramsByAnnee = $this->plateformeParamRepository->findByCampagneIndexedByAnnee($campagne);

        // Pre-load Campaign N-1 data
        $campagneN1 = $this->getPreviousCampagne($campagne);
        $dpeN1ByParcours = [];
        $anneesN1ByParcours = [];
        $paramsN1ByAnnee = [];

        if ($campagneN1 !== null) {
            $allDpeN1 = $this->dpeParcoursRepository->findByCampagneCollecte($campagneN1);
            foreach ($allDpeN1 as $dp) {
                $pId = $dp->getParcours()?->getId();
                if ($pId !== null) {
                    $dpeN1ByParcours[$pId] = $dp;
                }
            }
            $anneesN1ByParcours = $this->anneeRepository->findByCampagneIndexedByParcours($campagneN1);
            $paramsN1ByAnnee = $this->plateformeParamRepository->findByCampagneIndexedByAnnee($campagneN1);
        }

        $hasAnyTpasForPlatform = $this->hasAnyTpasForPlatform($tpaByTypeDiplome, $plateforme);
        $code = strtolower($plateforme->getCode() ?? '');

        $rows = match ($code) {
            'psup', 'parcoursup' => $this->buildParcoursupRows($allDpeParcours, $anneesByParcours, $tpaByTypeDiplome, $paramsByAnnee, $plateforme, $campagne, $typeDiplome, $hasAnyTpasForPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee),
            'mm', 'monmaster' => $this->buildMonMasterRows($allDpeParcours, $anneesByParcours, $tpaByTypeDiplome, $paramsByAnnee, $plateforme, $campagne, $typeDiplome, $hasAnyTpasForPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee),
            'ec', 'ecandidat' => $this->buildECandidatRows($allDpeParcours, $anneesByParcours, $tpaByTypeDiplome, $paramsByAnnee, $plateforme, $campagne, $typeDiplome, $hasAnyTpasForPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee),
            'eef' => $this->buildEefRows($allDpeParcours, $anneesByParcours, $campagne, $typeDiplome),
            default => $this->buildDefaultPlatformRows($allDpeParcours, $anneesByParcours, $tpaByTypeDiplome, $paramsByAnnee, $plateforme, $campagne, $typeDiplome, $hasAnyTpasForPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee),
        };

        return [
            'annee_universitaire' => $anneeN,
            'annee_n' => $anneeN,
            'annee_n_1' => $anneeN1,
            'campagne' => $campagne,
            'campagne_n_1' => $campagneN1,
            'composante' => $composante,
            'type_diplome' => $typeDiplome,
            'plateforme' => $plateforme,
            'rows' => $rows,
        ];
    }

    /**
     * Export single Excel file for a platform (optionally filtered by composante and/or type of diploma).
     */
    public function exportPlatformSingle(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?Composante $composante = null,
        ?TypeDiplome $typeDiplome = null,
    ): Response {
        $context = $this->getDataForPlatform($plateforme, $campagne, $composante, $typeDiplome);
        $template = $this->getTemplateForPlatform($plateforme);

        $parts = [$plateforme->getLibelle() ?? $plateforme->getCode() ?? 'Export'];
        if ($composante !== null) {
            $parts[] = $composante->getSigle() ?: $composante->getLibelle();
        }
        if ($typeDiplome !== null) {
            $parts[] = $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle();
        }
        $parts[] = $context['annee_universitaire'];

        $filename = sprintf('%s.xlsx', implode(' - ', $parts));

        return $this->spreadsheetRenderer->renderFromTemplate(
            $template,
            $context,
            $filename
        );
    }

    /**
     * Export a ZIP containing one Excel file per Type de Diplôme for this platform.
     */
    public function exportPlatformZipByDiplomes(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?Composante $composante = null,
    ): Response {
        $annee = $campagne->getAnnee() ?? (int)date('Y');
        $anneeN = sprintf('%d-%d', $annee, $annee + 1);

        $typesDiplome = $this->getTypesDiplomeForPlatform($plateforme, $campagne, $composante);
        $template = $this->getTemplateForPlatform($plateforme);

        $tempZipFile = tempnam(sys_get_temp_dir(), 'annexes_dipl_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier archive ZIP.');
        }

        $tempExcelFiles = [];

        foreach ($typesDiplome as $typeDipl) {
            $context = $this->getDataForPlatform($plateforme, $campagne, $composante, $typeDipl);
            if (empty($context['rows'])) {
                continue;
            }

            $parts = [$plateforme->getLibelle() ?? $plateforme->getCode() ?? 'Export'];
            if ($composante !== null) {
                $parts[] = $composante->getSigle() ?: $composante->getLibelle();
            }
            $parts[] = $typeDipl->getLibelleCourt() ?: $typeDipl->getLibelle();
            $parts[] = $anneeN;

            $excelFilename = sprintf('%s.xlsx', implode(' - ', $parts));

            $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
            $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempXlsx);

            $zip->addFile($tempXlsx, $excelFilename);
            $tempExcelFiles[] = $tempXlsx;
        }

        if (empty($tempExcelFiles)) {
            $context = $this->getDataForPlatform($plateforme, $campagne, $composante);
            $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
            $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempXlsx);
            $zip->addFile($tempXlsx, sprintf('%s - %s.xlsx', $plateforme->getLibelle(), $anneeN));
            $tempExcelFiles[] = $tempXlsx;
        }

        $zip->close();

        foreach ($tempExcelFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        $zipNameParts = [$plateforme->getLibelle() ?? 'Export', 'par diplome'];
        if ($composante !== null) {
            $zipNameParts[] = $composante->getSigle() ?: $composante->getLibelle();
        }
        $zipNameParts[] = $anneeN;
        $zipDownloadName = sprintf('%s.zip', implode(' - ', $zipNameParts));

        return $this->createZipResponse($tempZipFile, $zipDownloadName);
    }

    /**
     * Export a ZIP containing one Excel file per Composante for this platform.
     */
    public function exportPlatformZipByComposantes(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?TypeDiplome $typeDiplome = null,
    ): Response {
        $annee = $campagne->getAnnee() ?? (int)date('Y');
        $anneeN = sprintf('%d-%d', $annee, $annee + 1);

        $composantes = $this->getComposantesForPlatform($plateforme, $campagne);
        $template = $this->getTemplateForPlatform($plateforme);

        $tempZipFile = tempnam(sys_get_temp_dir(), 'annexes_compo_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier archive ZIP.');
        }

        $tempExcelFiles = [];

        foreach ($composantes as $comp) {
            $context = $this->getDataForPlatform($plateforme, $campagne, $comp, $typeDiplome);
            if (empty($context['rows'])) {
                continue;
            }

            $parts = [$plateforme->getLibelle() ?? $plateforme->getCode() ?? 'Export'];
            $parts[] = $comp->getSigle() ?: $comp->getLibelle();
            if ($typeDiplome !== null) {
                $parts[] = $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle();
            }
            $parts[] = $anneeN;

            $excelFilename = sprintf('%s.xlsx', implode(' - ', $parts));

            $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
            $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempXlsx);

            $zip->addFile($tempXlsx, $excelFilename);
            $tempExcelFiles[] = $tempXlsx;
        }

        if (empty($tempExcelFiles)) {
            $context = $this->getDataForPlatform($plateforme, $campagne, null, $typeDiplome);
            $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
            $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempXlsx);
            $zip->addFile($tempXlsx, sprintf('%s - %s.xlsx', $plateforme->getLibelle(), $anneeN));
            $tempExcelFiles[] = $tempXlsx;
        }

        $zip->close();

        foreach ($tempExcelFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        $zipNameParts = [$plateforme->getLibelle() ?? 'Export', 'par composante'];
        if ($typeDiplome !== null) {
            $zipNameParts[] = $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle();
        }
        $zipNameParts[] = $anneeN;
        $zipDownloadName = sprintf('%s.zip', implode(' - ', $zipNameParts));

        return $this->createZipResponse($tempZipFile, $zipDownloadName);
    }

    /**
     * Export all platforms in a single ZIP file, taking each platform's modeExport into account.
     */
    public function exportAllPlatformsZip(CampagneCollecte $campagne, ?Composante $composante = null): Response
    {
        $annee = $campagne->getAnnee() ?? (int)date('Y');
        $anneeN = sprintf('%d-%d', $annee, $annee + 1);

        $platforms = $this->getActivePlatforms();

        $tempZipFile = tempnam(sys_get_temp_dir(), 'annexes_all_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier archive ZIP.');
        }

        $tempExcelFiles = [];

        foreach ($platforms as $platform) {
            $template = $this->getTemplateForPlatform($platform);

            if ($platform->isExportParDiplome()) {
                $typesDiplome = $this->getTypesDiplomeForPlatform($platform, $campagne, $composante);
                $addedForPlatform = 0;

                foreach ($typesDiplome as $typeDipl) {
                    $context = $this->getDataForPlatform($platform, $campagne, $composante, $typeDipl);
                    if (empty($context['rows'])) {
                        continue;
                    }

                    $parts = [$platform->getLibelle() ?? $platform->getCode() ?? 'Export'];
                    if ($composante !== null) {
                        $parts[] = $composante->getSigle() ?: $composante->getLibelle();
                    }
                    $parts[] = $typeDipl->getLibelleCourt() ?: $typeDipl->getLibelle();
                    $parts[] = $anneeN;

                    $excelFilename = sprintf('%s.xlsx', implode(' - ', $parts));

                    $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
                    $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
                    $writer = new Xlsx($spreadsheet);
                    $writer->save($tempXlsx);

                    $zip->addFile($tempXlsx, $excelFilename);
                    $tempExcelFiles[] = $tempXlsx;
                    $addedForPlatform++;
                }

                if ($addedForPlatform === 0) {
                    $context = $this->getDataForPlatform($platform, $campagne, $composante);
                    $parts = [$platform->getLibelle() ?? $platform->getCode() ?? 'Export'];
                    if ($composante !== null) {
                        $parts[] = $composante->getSigle() ?: $composante->getLibelle();
                    }
                    $parts[] = $anneeN;
                    $excelFilename = sprintf('%s.xlsx', implode(' - ', $parts));

                    $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
                    $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
                    $writer = new Xlsx($spreadsheet);
                    $writer->save($tempXlsx);

                    $zip->addFile($tempXlsx, $excelFilename);
                    $tempExcelFiles[] = $tempXlsx;
                }
            } else {
                $context = $this->getDataForPlatform($platform, $campagne, $composante);

                $parts = [$platform->getLibelle() ?? $platform->getCode() ?? 'Export'];
                if ($composante !== null) {
                    $parts[] = $composante->getSigle() ?: $composante->getLibelle();
                }
                $parts[] = $anneeN;

                $excelFilename = sprintf('%s.xlsx', implode(' - ', $parts));

                $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
                $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
                $writer = new Xlsx($spreadsheet);
                $writer->save($tempXlsx);

                $zip->addFile($tempXlsx, $excelFilename);
                $tempExcelFiles[] = $tempXlsx;
            }
        }

        $zip->close();

        foreach ($tempExcelFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        $zipDownloadName = sprintf('Annexes CA - Toutes plateformes - %s.zip', $anneeN);
        if ($composante !== null) {
            $zipDownloadName = sprintf('Annexes CA - Toutes plateformes - %s - %s.zip', $composante->getSigle() ?: $composante->getLibelle(), $anneeN);
        }

        return $this->createZipResponse($tempZipFile, $zipDownloadName);
    }

    public function exportSingleAnnexe(string $type, CampagneCollecte $campagne, ?Composante $composante = null): Response
    {
        $code = match ($type) {
            'parcoursup', 'psup' => 'PSUP',
            'monmaster', 'mm' => 'MM',
            'ecandidat', 'ec', 'ecandidat_but', 'ecandidat_licence', 'ecandidat_di', 'ecandidat_lp', 'ecandidat_master_autres' => 'EC',
            'eef' => 'EEF',
            default => strtoupper($type),
        };

        $platform = $this->plateformeAdmissionRepository->findOneBy(['code' => $code])
            ?? $this->plateformeAdmissionRepository->findOneBy(['code' => strtolower($code)]);

        if ($platform instanceof PlateformeAdmission) {
            return $this->exportPlatformSingle($platform, $campagne, $composante);
        }

        throw new \InvalidArgumentException(sprintf('Plateforme ou type d\'annexe inconnu: %s', $type));
    }

    public function exportAllAsZip(CampagneCollecte $campagne, ?Composante $composante = null): Response
    {
        return $this->exportAllPlatformsZip($campagne, $composante);
    }

    private function createZipResponse(string $tempZipFile, string $downloadName): Response
    {
        $response = new StreamedResponse(function () use ($tempZipFile) {
            $handle = fopen($tempZipFile, 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
                @unlink($tempZipFile);
            }
        });

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $downloadName
        );

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * Helper to find N-1 DPE and Annee for a given parcours.
     *
     * @param array<int, DpeParcours> $dpeN1ByParcours
     * @param array<int, list<Annee>> $anneesN1ByParcours
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsN1ByAnnee
     * @return array{
     *     hasN1: bool,
     *     dpeN1: DpeParcours|null,
     *     isOuvertN1: bool,
     *     anneeN1: Annee|null,
     *     paramN1: PlateformeAdmissionParametre|null
     * }
     */
    private function resolveN1Data(
        Parcours $parcours,
        int $ordre,
        PlateformeAdmission $plateforme,
        array $dpeN1ByParcours,
        array $anneesN1ByParcours,
        array $paramsN1ByAnnee,
    ): array {
        $parcoursN1 = $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();

        $dpeN1 = null;
        if ($parcoursN1 !== null && isset($dpeN1ByParcours[$parcoursN1->getId()])) {
            $dpeN1 = $dpeN1ByParcours[$parcoursN1->getId()];
        } elseif (isset($dpeN1ByParcours[$parcours->getId()])) {
            $dpeN1 = $dpeN1ByParcours[$parcours->getId()];
        }

        if ($dpeN1 === null) {
            return [
                'hasN1' => false,
                'dpeN1' => null,
                'isOuvertN1' => false,
                'anneeN1' => null,
                'paramN1' => null,
            ];
        }

        $etat = $dpeN1->getEtatReconduction();
        $isOuvertN1 = in_array($etat, [
            TypeModificationDpeEnum::OUVERT,
            TypeModificationDpeEnum::CREATION,
            TypeModificationDpeEnum::MODIFICATION,
            TypeModificationDpeEnum::MODIFICATION_PARCOURS,
            TypeModificationDpeEnum::MODIFICATION_INTITULE,
            TypeModificationDpeEnum::MODIFICATION_MCCC,
            TypeModificationDpeEnum::MODIFICATION_TEXTE,
            TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE,
            TypeModificationDpeEnum::OUVERTURE_SES,
            TypeModificationDpeEnum::OUVERTURE_CFVU,
        ], true);

        $anneeN1 = null;
        $targetPId = $parcoursN1?->getId() ?? $parcours->getId();
        if (isset($anneesN1ByParcours[$targetPId])) {
            foreach ($anneesN1ByParcours[$targetPId] as $a) {
                if ($a->getOrdre() === $ordre) {
                    $anneeN1 = $a;
                    break;
                }
            }
        }

        $paramN1 = null;
        if ($anneeN1 !== null && isset($paramsN1ByAnnee[$anneeN1->getId()])) {
            foreach ($paramsN1ByAnnee[$anneeN1->getId()] as $p) {
                if ($p->getPlateforme()?->getId() === $plateforme->getId()) {
                    $paramN1 = $p;
                    break;
                }
            }
        }

        return [
            'hasN1' => true,
            'dpeN1' => $dpeN1,
            'isOuvertN1' => $isOuvertN1,
            'anneeN1' => $anneeN1,
            'paramN1' => $paramN1,
        ];
    }

    /**
     * Unified row builder for eCandidat.
     *
     * @param array<int, DpeParcours> $allDpeParcours
     * @param array<int, list<Annee>> $anneesByParcours
     * @param array<int, list<TypeDiplomePlateformeAdmission>> $tpaByTypeDiplome
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsByAnnee
     * @param array<int, DpeParcours> $dpeN1ByParcours
     * @param array<int, list<Annee>> $anneesN1ByParcours
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsN1ByAnnee
     */
    private function buildECandidatRows(
        array $allDpeParcours,
        array $anneesByParcours,
        array $tpaByTypeDiplome,
        array $paramsByAnnee,
        PlateformeAdmission $ecPlatform,
        CampagneCollecte $campagne,
        ?TypeDiplome $filterTypeDiplome,
        bool $hasAnyTpasForPlatform,
        array $dpeN1ByParcours,
        array $anneesN1ByParcours,
        array $paramsN1ByAnnee,
    ): array {
        $rows = [];

        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            if (!$parcours || !$formation) continue;

            $typeDiplome = $formation->getTypeDiplome();
            if (!$typeDiplome) continue;

            if ($filterTypeDiplome !== null && $typeDiplome->getId() !== $filterTypeDiplome->getId()) {
                continue;
            }

            $typeDiplId = $typeDiplome->getId();
            $tpas = ($typeDiplId && isset($tpaByTypeDiplome[$typeDiplId])) ? $tpaByTypeDiplome[$typeDiplId] : [];

            if (!$this->isFormationRelevantForPlatform($formation, $parcours, $typeDiplome, $ecPlatform, $tpas, $hasAnyTpasForPlatform)) {
                continue;
            }

            // Find TPA for this platform
            $configuredYears = null;
            foreach ($tpas as $tpa) {
                if ($tpa->getPlateforme()?->getId() === $ecPlatform->getId()) {
                    $configuredYears = $tpa->getAnnees();
                    break;
                }
            }

            $diplCode = strtoupper($typeDiplome->getLibelleCourt() ?? '');
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $loc = $formation->getLocalisationMention()->first();
            $locVille = $loc instanceof \App\Entity\Ville ? (string)$loc->getLibelle() : 'Reims';
            $ville = $parcours->getVille()?->getLibelle() ?? $locVille;
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';

            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            
            if (empty($parAnnees)) {
                // If parcours has no separate annee entities
                $isOuvertN = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
                $capaciteN = $formation->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, 1, $ecPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $ouvertureN1 = $n1['hasN1'] ? ($n1['isOuvertN1'] ? 'OUI' : 'NON') : '';
                $integrationEcN1 = $n1['hasN1'] ? ($n1['isOuvertN1'] ? 'OUI' : 'NON') : '';
                $capaciteN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '') : '';
                

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $diplCode ?: $typeDiplome->getLibelle(),
                    'mention' => $formation->getDisplay(),
                    'parcours' => $parcours->getLibelle() ?: '-',
                    'type_parcours' => $typeParcours,
                    'lieu' => $ville,
                    'niveau' => 1,
                    'ouverture_n_1' => $ouvertureN1,
                    'integration_ecandidat_n_1' => $integrationEcN1,
                    'capacite_n_1' => $capaciteN1,
                    'ouverture_n' => $isOuvertN ? 'OUI' : 'NON',
                    'integration_ecandidat_n' => $isOuvertN ? 'OUI' : 'NON',
                    'capacite_n' => $capaciteN,
                    'remarques' => '',
                ];
                continue;
            }

            foreach ($parAnnees as $annee) {
                $ordre = $annee->getOrdre();

                if ($configuredYears !== null && !empty($configuredYears)) {
                    if (!in_array($ordre, $configuredYears, true)) {
                        continue;
                    }
                } elseif (!$hasAnyTpasForPlatform) {
                    if (in_array($diplCode, ['BUT', 'L', 'LICENCE', 'CMI'], true) && !in_array($ordre, [2, 3], true)) {
                        continue;
                    }
                    if (in_array($diplCode, ['M', 'MASTER', 'MA'], true) && $ordre !== 2) {
                        continue;
                    }
                }

                $param = null;
                $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                foreach ($anneeParams as $p) {
                    if ($p->getPlateforme()?->getId() === $ecPlatform->getId() && $p->getCampagne() === $campagne) {
                        $param = $p;
                        break;
                    }
                }

                // N data
                $isOuvertN = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT) && $annee->isOuvert();
                $isECN = ($param && $param->isActive()) || $isOuvertN;
                $capaciteN = $param?->getCapaciteGlobale() ?? $annee->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $ordre, $ecPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $ouvertureN1 = '';
                $integrationEcN1 = '';
                $capaciteN1 = '';
                if ($n1['hasN1']) {
                    $ouvertureN1 = $n1['isOuvertN1'] ? 'OUI' : 'NON';
                    $integrationEcN1 = ($n1['paramN1'] && $n1['paramN1']->isActive()) ? 'OUI' : ($n1['isOuvertN1'] ? 'OUI' : 'NON');
                    $capaciteN1 = $n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '';
                }

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $diplCode ?: $typeDiplome->getLibelle(),
                    'mention' => $formation->getDisplay(),
                    'parcours' => $parcours->getLibelle() ?: '-',
                    'type_parcours' => $typeParcours,
                    'lieu' => $ville,
                    'niveau' => $ordre,
                    'ouverture_n_1' => $ouvertureN1,
                    'integration_ecandidat_n_1' => $integrationEcN1,
                    'capacite_n_1' => $capaciteN1,
                    'ouverture_n' => $isOuvertN ? 'OUI' : 'NON',
                    'integration_ecandidat_n' => $isECN ? 'OUI' : 'NON',
                    'capacite_n' => $capaciteN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];
            }
        }

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ($a['diplome'] ?? '') . ($a['mention'] ?? '') . ($a['niveau'] ?? ''),
            ($b['composante']) . ($b['diplome'] ?? '') . ($b['mention'] ?? '') . ($b['niveau'] ?? '')
        ));

        return $rows;
    }

    /**
     * @param array<int, DpeParcours> $allDpeParcours
     * @param array<int, list<Annee>> $anneesByParcours
     * @param array<int, list<TypeDiplomePlateformeAdmission>> $tpaByTypeDiplome
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsByAnnee
     * @param array<int, DpeParcours> $dpeN1ByParcours
     * @param array<int, list<Annee>> $anneesN1ByParcours
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsN1ByAnnee
     */
    private function buildParcoursupRows(
        array $allDpeParcours,
        array $anneesByParcours,
        array $tpaByTypeDiplome,
        array $paramsByAnnee,
        PlateformeAdmission $psupPlatform,
        CampagneCollecte $campagne,
        ?TypeDiplome $filterTypeDiplome,
        bool $hasAnyTpasForPlatform,
        array $dpeN1ByParcours,
        array $anneesN1ByParcours,
        array $paramsN1ByAnnee,
    ): array {
        $rows = [];
        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            if (!$parcours || !$formation) continue;

            $typeDiplome = $formation->getTypeDiplome();
            if (!$typeDiplome) continue;

            if ($filterTypeDiplome !== null && $typeDiplome->getId() !== $filterTypeDiplome->getId()) {
                continue;
            }

            $typeDiplId = $typeDiplome->getId();
            $tpas = ($typeDiplId && isset($tpaByTypeDiplome[$typeDiplId])) ? $tpaByTypeDiplome[$typeDiplId] : [];

            if (!$this->isFormationRelevantForPlatform($formation, $parcours, $typeDiplome, $psupPlatform, $tpas, $hasAnyTpasForPlatform)) {
                continue;
            }

            $configuredYears = null;
            foreach ($tpas as $tpa) {
                if ($tpa->getPlateforme()?->getId() === $psupPlatform->getId()) {
                    $configuredYears = $tpa->getAnnees();
                    break;
                }
            }

            $diplCode = strtoupper($typeDiplome->getLibelleCourt() ?? '');
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $loc = $formation->getLocalisationMention()->first();
            $locVille = $loc instanceof \App\Entity\Ville ? (string)$loc->getLibelle() : 'Reims';
            $ville = $parcours->getVille()?->getLibelle() ?? $locVille;
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';
            $typeRecrutement = in_array($diplCode, ['BUT', 'DEUST'], true) ? 'Sélective' : 'Non sélective';

            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            foreach ($parAnnees as $annee) {
                $ordre = $annee->getOrdre();
                if ($configuredYears !== null && !empty($configuredYears)) {
                    if (!in_array($ordre, $configuredYears, true)) {
                        continue;
                    }
                } elseif ($ordre !== 1) {
                    continue; // Default Parcoursup = 1ère année
                }

                $param = null;
                $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                foreach ($anneeParams as $p) {
                    if ($p->getPlateforme()?->getId() === $psupPlatform->getId() && $p->getCampagne() === $campagne) {
                        $param = $p;
                        break;
                    }
                }

                $capaciteN = ($param && $param->isActive() && $param->getCapaciteGlobale() !== null)
                    ? $param->getCapaciteGlobale()
                    : $annee->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $ordre, $psupPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $capaciteN1 = '';
                if ($n1['hasN1']) {
                    $capaciteN1 = ($n1['paramN1'] && $n1['paramN1']->isActive() && $n1['paramN1']->getCapaciteGlobale() !== null)
                        ? $n1['paramN1']->getCapaciteGlobale()
                        : ($n1['anneeN1']?->getCapaciteAccueil() ?? '');
                }

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'mention' => $formation->getDisplay(),
                    'parcours' => $parcours->getLibelle() ?: '-',
                    'type_parcours' => $typeParcours,
                    'lieu' => $ville,
                    'statut' => 'Public',
                    'type_recrutement' => $typeRecrutement,
                    'capacite_n_1' => $capaciteN1,
                    'capacite_n' => $capaciteN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];
            }
        }

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ($a['diplome']) . ($a['mention']) . ($a['parcours']),
            ($b['composante']) . ($b['diplome']) . ($b['mention']) . ($b['parcours'])
        ));
        return $rows;
    }

    /**
     * @param array<int, DpeParcours> $allDpeParcours
     * @param array<int, list<Annee>> $anneesByParcours
     * @param array<int, list<TypeDiplomePlateformeAdmission>> $tpaByTypeDiplome
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsByAnnee
     * @param array<int, DpeParcours> $dpeN1ByParcours
     * @param array<int, list<Annee>> $anneesN1ByParcours
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsN1ByAnnee
     */
    private function buildMonMasterRows(
        array $allDpeParcours,
        array $anneesByParcours,
        array $tpaByTypeDiplome,
        array $paramsByAnnee,
        PlateformeAdmission $mmPlatform,
        CampagneCollecte $campagne,
        ?TypeDiplome $filterTypeDiplome,
        bool $hasAnyTpasForPlatform,
        array $dpeN1ByParcours,
        array $anneesN1ByParcours,
        array $paramsN1ByAnnee,
    ): array {
        $rows = [];
        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            if (!$parcours || !$formation) continue;

            $typeDiplome = $formation->getTypeDiplome();
            if (!$typeDiplome) continue;

            if ($filterTypeDiplome !== null && $typeDiplome->getId() !== $filterTypeDiplome->getId()) {
                continue;
            }

            $typeDiplId = $typeDiplome->getId();
            $tpas = ($typeDiplId && isset($tpaByTypeDiplome[$typeDiplId])) ? $tpaByTypeDiplome[$typeDiplId] : [];

            if (!$this->isFormationRelevantForPlatform($formation, $parcours, $typeDiplome, $mmPlatform, $tpas, $hasAnyTpasForPlatform)) {
                continue;
            }

            $configuredYears = null;
            foreach ($tpas as $tpa) {
                if ($tpa->getPlateforme()?->getId() === $mmPlatform->getId()) {
                    $configuredYears = $tpa->getAnnees();
                    break;
                }
            }

            $loc = $formation->getLocalisationMention()->first();
            $locVille = $loc instanceof \App\Entity\Ville ? (string)$loc->getLibelle() : 'Reims';
            $ville = $parcours->getVille()?->getLibelle() ?? $locVille;
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';

            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            foreach ($parAnnees as $annee) {
                $ordre = $annee->getOrdre();
                if ($configuredYears !== null && !empty($configuredYears)) {
                    if (!in_array($ordre, $configuredYears, true)) {
                        continue;
                    }
                } elseif ($ordre !== 1) {
                    continue; // MonMaster = M1 (1ère année)
                }

                $param = null;
                $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                foreach ($anneeParams as $p) {
                    if ($p->getPlateforme()?->getId() === $mmPlatform->getId() && $p->getCampagne() === $campagne) {
                        $param = $p;
                        break;
                    }
                }

                // N data
                $isOuvertN = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT) && $annee->isOuvert();
                $capGlobaleN = $param?->getCapaciteGlobale() ?? $annee->getCapaciteAccueil();
                $capFiN = $param?->getCapaciteFi() ?? $capGlobaleN;
                $capAltN = $param?->getCapaciteAlternance() ?? 0;
                $capSpeN = $param?->getCapaciteSpecifique() ?? 0;

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $ordre, $mmPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $ouvertureN1 = '';
                $capGlobaleN1 = '';
                $capFiN1 = '';
                $capAltN1 = '';
                $capSpeN1 = '';

                if ($n1['hasN1']) {
                    $ouvertureN1 = $n1['isOuvertN1'] ? 'OUI' : 'NON';
                    $capGlobaleN1 = $n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '';
                    $capFiN1 = $n1['paramN1']?->getCapaciteFi() ?? $capGlobaleN1;
                    $capAltN1 = $n1['paramN1']?->getCapaciteAlternance() ?? 0;
                    $capSpeN1 = $n1['paramN1']?->getCapaciteSpecifique() ?? 0;
                }

                $mentionName = $formation->getDisplay();
                $parcoursName = $parcours->getLibelle() ?: '-';

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'mention' => $mentionName,
                    'parcours' => $parcoursName,
                    'type_parcours' => $typeParcours,
                    'lieu' => $ville,
                    'ouverture_n_1' => $ouvertureN1,
                    'capacite_globale_n_1' => $capGlobaleN1,
                    'capacite_fi_n_1' => $capFiN1,
                    'capacite_alt_n_1' => $capAltN1,
                    'capacite_spe_n_1' => $capSpeN1,
                    'ouverture_n' => $isOuvertN ? 'OUI' : 'NON',
                    'capacite_globale_n' => $capGlobaleN,
                    'capacite_fi_n' => $capFiN,
                    'capacite_alt_n' => $capAltN,
                    'capacite_spe_n' => $capSpeN,
                    'mentions_conseillees' => 'Licences recommandées pour la mention',
                    'remarques' => $param?->getRemarques() ?? '',
                ];
            }
        }

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ($a['mention']) . ($a['parcours']),
            ($b['composante']) . ($b['mention']) . ($b['parcours'])
        ));
        return $rows;
    }

    /**
     * @param array<int, DpeParcours> $allDpeParcours
     * @param array<int, list<Annee>> $anneesByParcours
     */
    private function buildEefRows(
        array $allDpeParcours,
        array $anneesByParcours,
        CampagneCollecte $campagne,
        ?TypeDiplome $filterTypeDiplome = null,
    ): array {
        $rows = [];
        $index = 1;
        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            if (!$parcours || !$formation) continue;

            $typeDiplome = $formation->getTypeDiplome();
            if (!$typeDiplome) continue;

            if ($filterTypeDiplome !== null && $typeDiplome->getId() !== $filterTypeDiplome->getId()) {
                continue;
            }

            $diplCode = strtoupper($typeDiplome->getLibelleCourt() ?? '');

            if (!in_array($diplCode, ['M', 'MASTER', 'MA', 'L', 'LICENCE', 'LP', 'BUT'], true)) {
                continue;
            }

            $loc = $formation->getLocalisationMention()->first();
            $locVille = $loc instanceof \App\Entity\Ville ? (string)$loc->getLibelle() : 'Reims';
            $ville = $parcours->getVille()?->getLibelle() ?? $locVille;
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';

            $isOuvert = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);

            $niveauxOuverts = [];
            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            foreach ($parAnnees as $annee) {
                if ($annee->isOuvert()) {
                    $prefix = in_array($diplCode, ['M', 'MASTER', 'MA'], true) ? 'M' : 'L';
                    $niveauxOuverts[] = $prefix . $annee->getOrdre();
                }
            }
            $niveauxStr = !empty($niveauxOuverts) ? implode('/', $niveauxOuverts) : '-';

            $rows[] = [
                'composante' => $compLibelle,
                'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                'mention' => $formation->getDisplay(),
                'parcours' => $parcours->getLibelle() ?: '-',
                'type_parcours' => $typeParcours,
                'lieu' => $ville,
                'saisie' => '',
                'numero' => $index++,
                'etat_compo' => 'Validé',
                'maj_tarif' => 'Non',
                'etat_pv' => 'Déposé',
                'niveaux_urca' => $niveauxStr,
                'ouverture' => $isOuvert ? 'oui' : 'non',
                'niveaux_eef' => $niveauxStr,
                'droits_differencies' => in_array($diplCode, ['M', 'MASTER', 'MA'], true) ? 3941 : 2850,
                'niveau_francais' => 'B2',
                'remarques' => '',
            ];
        }

        usort($rows, static fn($a, $b) => strcmp($a['composante'] . $a['mention'], $b['composante'] . $b['mention']));
        return $rows;
    }

    /**
     * Fallback row builder for any standard or custom platform.
     *
     * @param array<int, DpeParcours> $allDpeParcours
     * @param array<int, list<Annee>> $anneesByParcours
     * @param array<int, list<TypeDiplomePlateformeAdmission>> $tpaByTypeDiplome
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsByAnnee
     * @param array<int, DpeParcours> $dpeN1ByParcours
     * @param array<int, list<Annee>> $anneesN1ByParcours
     * @param array<int, list<PlateformeAdmissionParametre>> $paramsN1ByAnnee
     */
    private function buildDefaultPlatformRows(
        array $allDpeParcours,
        array $anneesByParcours,
        array $tpaByTypeDiplome,
        array $paramsByAnnee,
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?TypeDiplome $filterTypeDiplome,
        bool $hasAnyTpasForPlatform,
        array $dpeN1ByParcours,
        array $anneesN1ByParcours,
        array $paramsN1ByAnnee,
    ): array {
        $rows = [];
        foreach ($allDpeParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            if (!$parcours || !$formation) continue;

            $typeDiplome = $formation->getTypeDiplome();
            if (!$typeDiplome) continue;

            if ($filterTypeDiplome !== null && $typeDiplome->getId() !== $filterTypeDiplome->getId()) {
                continue;
            }

            $typeDiplId = $typeDiplome->getId();
            $tpas = ($typeDiplId && isset($tpaByTypeDiplome[$typeDiplId])) ? $tpaByTypeDiplome[$typeDiplId] : [];

            if (!$this->isFormationRelevantForPlatform($formation, $parcours, $typeDiplome, $plateforme, $tpas, $hasAnyTpasForPlatform)) {
                continue;
            }

            $loc = $formation->getLocalisationMention()->first();
            $locVille = $loc instanceof \App\Entity\Ville ? (string)$loc->getLibelle() : 'Reims';
            $ville = $parcours->getVille()?->getLibelle() ?? $locVille;
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';

            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            if (empty($parAnnees)) {
                $isOuvertN = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
                $capaciteGlobaleN = $formation->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, 1, $plateforme, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $ouvertN1 = $n1['hasN1'] ? ($n1['isOuvertN1'] ? 'OUI' : 'NON') : '';
                $capGlobaleN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '') : '';
                $capFiN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteFi() ?? $capGlobaleN1) : '';
                $capFcN1 = $n1['hasN1'] ? 0 : '';
                $capFpN1 = $n1['hasN1'] ? 0 : '';
                $capAltN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteAlternance() ?? 0) : '';
                $capSpeN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteSpecifique() ?? 0) : '';

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'mention' => $formation->getDisplay(),
                    'parcours' => $parcours->getLibelle() ?: '-',
                    'type_parcours' => $typeParcours,
                    'lieu' => $ville,
                    'ouvert_n_1' => $ouvertN1,
                    'capacite_fi_n_1' => $capFiN1,
                    'capacite_fc_n_1' => $capFcN1,
                    'capacite_fp_n_1' => $capFpN1,
                    'capacite_alt_n_1' => $capAltN1,
                    'capacite_spe_n_1' => $capSpeN1,
                    'capacite_globale_n_1' => $capGlobaleN1,
                    'ouvert_n' => $isOuvertN ? 'OUI' : 'NON',
                    'capacite_fi_n' => $capaciteGlobaleN,
                    'capacite_fc_n' => 0,
                    'capacite_fp_n' => 0,
                    'capacite_alt_n' => 0,
                    'capacite_spe_n' => 0,
                    'capacite_globale_n' => $capaciteGlobaleN,
                    'remarques' => '',
                ];
                continue;
            }

            foreach ($parAnnees as $annee) {
                $param = null;
                $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                foreach ($anneeParams as $p) {
                    if ($p->getPlateforme()?->getId() === $plateforme->getId() && $p->getCampagne() === $campagne) {
                        $param = $p;
                        break;
                    }
                }

                $isOuvertN = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT) && $annee->isOuvert();
                $capGlobaleN = $param?->getCapaciteGlobale() ?? $annee->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $annee->getOrdre(), $plateforme, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $ouvertN1 = $n1['hasN1'] ? ($n1['isOuvertN1'] ? 'OUI' : 'NON') : '';
                $capGlobaleN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '') : '';
                $capFiN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteFi() ?? $capGlobaleN1) : '';
                $capFcN1 = $n1['hasN1'] ? 0 : '';
                $capFpN1 = $n1['hasN1'] ? 0 : '';
                $capAltN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteAlternance() ?? 0) : '';
                $capSpeN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteSpecifique() ?? 0) : '';

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'mention' => $formation->getDisplay(),
                    'parcours' => $parcours->getLibelle() ?: '-',
                    'type_parcours' => $typeParcours,
                    'lieu' => $ville,
                    'ouvert_n_1' => $ouvertN1,
                    'capacite_fi_n_1' => $capFiN1,
                    'capacite_fc_n_1' => $capFcN1,
                    'capacite_fp_n_1' => $capFpN1,
                    'capacite_alt_n_1' => $capAltN1,
                    'capacite_spe_n_1' => $capSpeN1,
                    'capacite_globale_n_1' => $capGlobaleN1,
                    'ouvert_n' => $isOuvertN ? 'OUI' : 'NON',
                    'capacite_fi_n' => $param?->getCapaciteFi() ?? $capGlobaleN,
                    'capacite_fc_n' => 0,
                    'capacite_fp_n' => 0,
                    'capacite_alt_n' => $param?->getCapaciteAlternance() ?? 0,
                    'capacite_spe_n' => $param?->getCapaciteSpecifique() ?? 0,
                    'capacite_globale_n' => $capGlobaleN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];
            }
        }

        usort($rows, static fn($a, $b) => strcmp($a['composante'] . $a['diplome'] . $a['mention'], $b['composante'] . $b['diplome'] . $b['mention']));
        return $rows;
    }
}
