<?php

declare(strict_types=1);

namespace App\Service\Offre;

use App\DTO\DiffObject;
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
use App\Entity\Ville;
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
            'eef' => $this->buildEefRows($allDpeParcours, $anneesByParcours, $campagne, $typeDiplome, $dpeN1ByParcours),
            default => $this->buildDefaultPlatformRows($allDpeParcours, $anneesByParcours, $tpaByTypeDiplome, $paramsByAnnee, $plateforme, $campagne, $typeDiplome, $hasAnyTpasForPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee),
        };

        $dateGeneration = new \DateTimeImmutable();
        $titreAnnexe = $this->buildExportTitle($plateforme, $campagne, $composante, $typeDiplome, $rows);
        $nomOnglet = $this->buildSheetName($plateforme, $typeDiplome, $dateGeneration);

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
            'titre_annexe' => $titreAnnexe,
            'date_generation' => $dateGeneration,
            'nom_onglet' => $nomOnglet,
        ];
    }

    /**
     * Construit le titre officiel de l'annexe d'admission / CA.
     * Exemple : "Annexe – eCandidat – formations proposées (M2, DNO, DE, DAEU) pour 2027-2028"
     * ou avec composante : "Annexe – eCandidat – IUT – formations proposées (BUT) pour 2027-2028".
     *
     * @param list<array<string, mixed>> $rows
     */
    public function buildExportTitle(
        PlateformeAdmission $plateforme,
        CampagneCollecte $campagne,
        ?Composante $composante = null,
        ?TypeDiplome $typeDiplome = null,
        array $rows = [],
    ): string {
        $annee = $campagne->getAnnee() ?? (int)date('Y');
        $anneeUniversitaire = sprintf('%d-%d', $annee, $annee + 1);

        $platformName = $plateforme->getLibelle() ?? $plateforme->getCode() ?? 'Plateforme';

        $diplomes = [];
        if ($typeDiplome !== null) {
            $diplomes[] = $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle();
        } else {
            foreach ($rows as $row) {
                $dipl = $row['diplome'] ?? null;
                if ($dipl && !in_array($dipl, $diplomes, true)) {
                    $diplomes[] = (string)$dipl;
                }
            }
        }

        $diplomesPart = !empty($diplomes) ? sprintf(' (%s)', implode(', ', $diplomes)) : '';

        if ($composante !== null) {
            $compName = $composante->getSigle() ?: $composante->getLibelle();
            return sprintf('Annexe – %s – %s – formations proposées%s pour %s', $platformName, $compName, $diplomesPart, $anneeUniversitaire);
        }

        return sprintf('Annexe – %s – formations proposées%s pour %s', $platformName, $diplomesPart, $anneeUniversitaire);
    }

    /**
     * Nettoie une chaîne pour en faire un nom de fichier valide sans caractères interdits.
     */
    public function sanitizeFilename(string $filename): string
    {
        $sanitized = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $filename);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        return trim((string) $sanitized);
    }

    /**
     * Génère un nom d'onglet Excel (≤ 31 caractères) harmonisé avec la date et l'heure.
     * Exemple : "eCandidat (04-11 17h30)" ou "M2 (04-11 17h30)".
     */
    public function buildSheetName(
        PlateformeAdmission $plateforme,
        ?TypeDiplome $typeDiplome = null,
        ?\DateTimeInterface $dateGeneration = null,
    ): string {
        $date = ($dateGeneration ?? new \DateTimeImmutable())->format('d-m H\hi');
        $prefix = $typeDiplome
            ? ($typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle())
            : ($plateforme->getLibelle() ?? $plateforme->getCode() ?? 'Offre');

        $sheetName = sprintf('%s (%s)', $prefix, $date);
        if (mb_strlen($sheetName) > 31) {
            $allowedPrefixLength = 31 - (mb_strlen($date) + 3);
            $prefix = mb_substr((string)$prefix, 0, max(3, $allowedPrefixLength));
            $sheetName = sprintf('%s (%s)', $prefix, $date);
        }

        return $sheetName;
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

        $filename = sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe']));

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

            $excelFilename = sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe']));

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
            $zip->addFile($tempXlsx, sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe'])));
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

            $excelFilename = sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe']));

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
            $zip->addFile($tempXlsx, sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe'])));
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

                    $excelFilename = sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe']));

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
                    $excelFilename = sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe']));

                    $spreadsheet = $this->spreadsheetRenderer->createFromTemplate($template, $context);
                    $tempXlsx = tempnam(sys_get_temp_dir(), 'annexe_') . '.xlsx';
                    $writer = new Xlsx($spreadsheet);
                    $writer->save($tempXlsx);

                    $zip->addFile($tempXlsx, $excelFilename);
                    $tempExcelFiles[] = $tempXlsx;
                }
            } else {
                $context = $this->getDataForPlatform($platform, $campagne, $composante);
                $excelFilename = sprintf('%s.xlsx', $this->sanitizeFilename($context['titre_annexe']));

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
                'parcoursN1' => null,
                'dpeN1' => null,
                'isOuvertN1' => false,
                'anneeN1' => null,
                'paramN1' => null,
            ];
        }

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

        $isOuvertN1 = $dpeN1->isOuvert() && ($anneeN1 === null || $anneeN1->isOuvert() !== false);

        return [
            'hasN1' => true,
            'parcoursN1' => $dpeN1->getParcours() ?? $parcoursN1,
            'dpeN1' => $dpeN1,
            'isOuvertN1' => $isOuvertN1,
            'anneeN1' => $anneeN1,
            'paramN1' => $paramN1,
        ];
    }

    /**
     * Résout le régime d'inscription affiché pour un parcours (et optionnellement une année).
     */
    public function resolveRegimeInscription(Parcours $parcours, ?Annee $annee = null): string
    {
        $values = $annee?->getRegimeInscriptionValues() ?? [];
        if (empty($values)) {
            $values = $parcours->getRegimeInscriptionValues();
        }

        if (!empty($values)) {
            return implode(', ', $values);
        }

        $display = $parcours->displayRegimeInscription();
        return $display ?: '';
    }

    /**
     * Helper to compute diff for mention and parcours between N-1 and N.
     * Si parcours est le parcours par défaut, la cellule est vide.
     * Si nouvelle création en N, pas mis en diff.
     *
     * @return array{mention: DiffObject, parcours: DiffObject}
     */
    public function resolveEntityDiff(Parcours $parcours, ?DpeParcours $dpeN1): array
    {
        $parcoursN1 = $dpeN1?->getParcours() ?? $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();
        $formation = $parcours->getFormation();
        $formationN1 = $parcoursN1?->getFormation() ?? $formation?->getFormationOrigineCopie();

        $isNouveauParcours = ($dpeN1 === null && $parcours->getParcoursOrigine() === null && $parcours->getParcoursOrigineCopie() === null);
        $isNouvelleFormation = ($formationN1 === null);

        // Si parcours est le parcours par défaut, laisser la cellule vide
        $parcoursN = $parcours->isParcoursDefaut() ? '' : ($parcours->getLibelle() ?: '');
        $parcoursN1Libelle = ($parcoursN1 !== null && !$parcoursN1->isParcoursDefaut()) ? ($parcoursN1->getLibelle() ?: '') : '';

        $mentionN = $formation?->getDisplay() ?: '-';
        $mentionN1Display = $formationN1?->getDisplay() ?: null;

        $originalParcours = $isNouveauParcours ? null : ($parcoursN1Libelle !== '' ? $parcoursN1Libelle : null);
        $originalMention = $isNouvelleFormation ? null : $mentionN1Display;

        return [
            'parcours' => new DiffObject($originalParcours, $parcoursN),
            'mention' => new DiffObject($originalMention, $mentionN),
        ];
    }

    /**
     * Calcule le statut d'ouverture, d'activation plateforme et de régime d'inscription avec DiffObject.
     * Si nouvelle création en N, l'original est null pour ne pas mettre en diff.
     *
     * @param array{
     *     hasN1: bool,
     *     parcoursN1?: Parcours|null,
     *     dpeN1: DpeParcours|null,
     *     isOuvertN1: bool,
     *     anneeN1: Annee|null,
     *     paramN1: PlateformeAdmissionParametre|null
     * } $n1
     * @return array{
     *     diff_annee_ouverte: DiffObject,
     *     diff_plateforme_proposee: DiffObject,
     *     diff_type_parcours: DiffObject,
     *     annee_ouverte_n: string,
     *     annee_ouverte_n_1: string,
     *     plateforme_proposee_n: string,
     *     plateforme_proposee_n_1: string,
     *     is_ouvert_n: bool,
     *     is_plateforme_active_n: bool
     * }
     */
    public function resolveRowStatusAndDiff(
        Parcours $parcours,
        ?Annee $annee,
        bool $dpeOuvertN,
        ?PlateformeAdmissionParametre $paramN,
        array $n1,
    ): array {
        // Statut N
        $isAnneeOuverteN = $dpeOuvertN && ($annee === null || $annee->isOuvert() !== false);
        $anneeOuverteNStr = $isAnneeOuverteN ? 'OUI' : 'NON';

        $isPlateformeActiveN = $paramN !== null ? $paramN->isActive() : $isAnneeOuverteN;
        $plateformeActiveNStr = $isPlateformeActiveN ? 'Active' : 'Non active';

        $regimeN = $this->resolveRegimeInscription($parcours, $annee);

        // Statut N-1 & calcul du diff
        $hasN1 = $n1['hasN1'];
        $anneeOuverteN1Str = '';
        $plateformeActiveN1Str = '';
        $regimeN1 = '';

        if ($hasN1) {
            $isAnneeOuverteN1 = $n1['isOuvertN1'];
            $anneeOuverteN1Str = $isAnneeOuverteN1 ? 'OUI' : 'NON';

            $isPlateformeActiveN1 = $n1['paramN1'] !== null ? $n1['paramN1']->isActive() : $isAnneeOuverteN1;
            $plateformeActiveN1Str = $isPlateformeActiveN1 ? 'Active' : 'Non active';

            $parcoursN1 = $n1['parcoursN1'] ?? $n1['dpeN1']?->getParcours();
            if ($parcoursN1 !== null) {
                $regimeN1 = $this->resolveRegimeInscription($parcoursN1, $n1['anneeN1']);
            }

            $diffAnneeOuverte = new DiffObject($anneeOuverteN1Str, $anneeOuverteNStr);
            $diffPlateformeProposee = new DiffObject($plateformeActiveN1Str, $plateformeActiveNStr);
            $diffTypeParcours = new DiffObject($regimeN1 !== '' ? $regimeN1 : null, $regimeN);
        } else {
            // Nouvelle création en N : pas mis en diff (original null)
            $diffAnneeOuverte = new DiffObject(null, $anneeOuverteNStr);
            $diffPlateformeProposee = new DiffObject(null, $plateformeActiveNStr);
            $diffTypeParcours = new DiffObject(null, $regimeN);
        }

        return [
            'diff_annee_ouverte' => $diffAnneeOuverte,
            'diff_plateforme_proposee' => $diffPlateformeProposee,
            'diff_type_parcours' => $diffTypeParcours,
            'annee_ouverte_n' => $anneeOuverteNStr,
            'annee_ouverte_n_1' => $anneeOuverteN1Str,
            'plateforme_proposee_n' => $plateformeActiveNStr,
            'plateforme_proposee_n_1' => $plateformeActiveN1Str,
            'is_ouvert_n' => $isAnneeOuverteN,
            'is_plateforme_active_n' => $isPlateformeActiveN,
        ];
    }

    /**
     * Lieu affiché dans les exports : localisation saisie sur le parcours, puis ancien champ ville,
     * puis première localisation de la mention.
     */
    public function resolveLieu(Parcours $parcours): string
    {
        $ville = $parcours->getLocalisation()
            ?? $parcours->getVille()
            ?? $parcours->getFormation()?->getLocalisationMention()->first();

        return $ville instanceof Ville ? (string) $ville->getLibelle() : 'Reims';
    }

    /**
     * Intégration à une plateforme : la case cochée fait foi quand le paramètre existe ;
     * sans paramètre (année jamais configurée), on suit l'ouverture.
     */
    public function isIntegrationPlateforme(?PlateformeAdmissionParametre $param, bool $isOuvert): bool
    {
        if ($param === null) {
            return $isOuvert;
        }

        return $isOuvert && $param->isActive();
    }

    /**
     * Résout les informations de mutualisation (Tronc Commun ou Portail) pour un parcours et une année donnés.
     * Renvoie null si le parcours n'est pas mutualisé sur cette année.
     *
     * @return array{key: string, nom: string, id: string, type: 'tc'|'portail'}|null
     */
    public function resolveMutualisationGroup(Formation $formation, Parcours $parcours, Annee $annee): ?array
    {
        $ordre = $annee->getOrdre();
        if ($ordre === null) {
            return null;
        }

        // 1. Portail inter-formations ou intra-formation
        $portail = $annee->getPortail();
        if ($portail !== null && $portail->getId() !== null) {
            return [
                'key' => sprintf('portail-%d-%d-%d', (int) $formation->getId(), (int) $portail->getId(), $ordre),
                'nom' => $portail->getLibelle() ?: sprintf('Portail #%d', $portail->getId()),
                'id' => (string) $portail->getId(),
                'type' => 'portail',
            ];
        }

        // 2. Tronc commun intra-formation
        if ($formation->getParcours()->count() > 1) {
            $tc = $formation->getTroncCommunForParcours($parcours, $ordre);
            if ($tc !== null) {
                return [
                    'key' => sprintf('tc-%d-%s-%d', (int) $formation->getId(), (string) $tc['id'], $ordre),
                    'nom' => !empty($tc['nom']) ? trim((string) $tc['nom']) : 'Tronc commun',
                    'id' => (string) $tc['id'],
                    'type' => 'tc',
                ];
            }

            // Fallback rétrocompatible pour les formations sans liste troncs_communs explicite
            if ($formation->isAnneeTroncCommun($ordre, $parcours)) {
                return [
                    'key' => sprintf('tc-%d-legacy-%d', (int) $formation->getId(), $ordre),
                    'nom' => 'Tronc commun',
                    'id' => 'legacy',
                    'type' => 'tc',
                ];
            }
        }

        return null;
    }

    /**
     * Formate le libellé d'un groupe mutualisé (Tronc commun ou Portail) avec la liste des parcours membres.
     *
     * @param list<string> $parcoursLibelles
     */
    public function formatGroupParcoursLabel(string $groupNom, array $parcoursLibelles, string $groupType = 'tc'): string
    {
        $uniqueLibelles = array_values(array_unique(array_filter(
            $parcoursLibelles,
            static fn(?string $l) => $l !== null && trim($l) !== '' && $l !== Parcours::PARCOURS_DEFAUT && $l !== '-'
        )));
        $parcoursListStr = implode(' / ', $uniqueLibelles);
        $cleanNom = trim($groupNom);

        if ($groupType === 'portail') {
            if (stripos($cleanNom, 'portail') !== false) {
                return count($uniqueLibelles) > 0 ? sprintf('%s : %s', $cleanNom, $parcoursListStr) : $cleanNom;
            }

            return count($uniqueLibelles) > 0 ? sprintf('Portail %s : %s', $cleanNom, $parcoursListStr) : sprintf('Portail %s', $cleanNom);
        }

        if ($cleanNom === '') {
            $cleanNom = 'Tronc commun';
        }

        if (count($uniqueLibelles) > 1) {
            if (stripos($cleanNom, 'tronc commun') !== false) {
                return sprintf('%s : %s', $cleanNom, $parcoursListStr);
            }

            return sprintf('Tronc commun %s : %s', $cleanNom, $parcoursListStr);
        }

        // Un seul parcours présent dans l'export pour ce tronc commun
        if (stripos($cleanNom, 'tronc commun') !== false) {
            return count($uniqueLibelles) === 1 ? sprintf('%s : %s', $cleanNom, $uniqueLibelles[0]) : $cleanNom;
        }

        return count($uniqueLibelles) === 1 ? sprintf('Tronc commun %s : %s', $cleanNom, $uniqueLibelles[0]) : sprintf('Tronc commun %s', $cleanNom);
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
        /** @var array<string, list<string>> $tcParcours */
        $tcParcours = [];
        /** @var array<string, list<string>> $tcLieux */
        $tcLieux = [];
        /** @var array<string, string> $tcNoms */
        $tcNoms = [];
        /** @var array<string, string> $tcTypes */
        $tcTypes = [];
        /** @var array<string, list<string>> $tcRemarques */
        $tcRemarques = [];

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
            $ville = $this->resolveLieu($parcours);

            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            
            if (empty($parAnnees)) {
                // If parcours has no separate annee entities
                $capaciteN = $formation->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, 1, $ecPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $status = $this->resolveRowStatusAndDiff($parcours, null, $dpePar->isOuvert(), null, $n1);
                $integrationEcN1 = $n1['hasN1'] ? ($n1['isOuvertN1'] ? 'OUI' : 'NON') : '';
                $capaciteN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '') : '';
                $diffs = $this->resolveEntityDiff($parcours, $n1['dpeN1']);

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $diplCode ?: $typeDiplome->getLibelle(),
                    'mention' => $diffs['mention'],
                    'parcours' => $diffs['parcours'],
                    'type_parcours' => $status['diff_type_parcours'],
                    'regime_inscription' => $status['diff_type_parcours'],
                    'lieu' => $ville,
                    'niveau' => 1,
                    'annee_ouverte' => $status['diff_annee_ouverte'],
                    'plateforme_proposee' => $status['diff_plateforme_proposee'],
                    'ouverture_n_1' => $status['annee_ouverte_n_1'],
                    'integration_ecandidat_n_1' => $integrationEcN1,
                    'capacite_n_1' => $capaciteN1,
                    'ouverture_n' => $status['annee_ouverte_n'],
                    'integration_ecandidat_n' => $status['is_ouvert_n'] ? 'OUI' : 'NON',
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

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $ordre, $ecPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $status = $this->resolveRowStatusAndDiff($parcours, $annee, $dpePar->isOuvert(), $param, $n1);

                // N data
                $isECN = $this->isIntegrationPlateforme($param, $status['is_ouvert_n']);
                $capaciteN = $param?->getCapaciteGlobale() ?? $annee->getCapaciteAccueil();

                $integrationEcN1 = '';
                $capaciteN1 = '';
                if ($n1['hasN1']) {
                    $integrationEcN1 = $this->isIntegrationPlateforme($n1['paramN1'], $n1['isOuvertN1']) ? 'OUI' : 'NON';
                    $capaciteN1 = $n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '';
                }
                $mutualisation = $this->resolveMutualisationGroup($formation, $parcours, $annee);
                $groupKey = $mutualisation['key'] ?? null;
                if ($groupKey !== null) {
                    if (isset($rows[$groupKey])) {
                        if (!$parcours->isParcoursDefaut()) {
                            $tcParcours[$groupKey][] = (string) $parcours->getLibelle();
                        }
                        if ($ville !== '') {
                            $tcLieux[$groupKey][] = $ville;
                        }
                        if ($param?->getRemarques()) {
                            $tcRemarques[$groupKey][] = $param->getRemarques();
                        }
                        continue;
                    }
                    $tcParcours[$groupKey] = !$parcours->isParcoursDefaut() ? [(string) $parcours->getLibelle()] : [];
                    $tcLieux[$groupKey] = $ville !== '' ? [$ville] : [];
                    $tcNoms[$groupKey] = $mutualisation['nom'];
                    $tcTypes[$groupKey] = $mutualisation['type'];
                    $tcRemarques[$groupKey] = $param?->getRemarques() ? [$param->getRemarques()] : [];
                }

                $diffs = $this->resolveEntityDiff($parcours, $n1['dpeN1']);

                $row = [
                    'composante' => $compLibelle,
                    'diplome' => $diplCode ?: $typeDiplome->getLibelle(),
                    'mention' => $diffs['mention'],
                    'parcours' => $diffs['parcours'],
                    'type_parcours' => $status['diff_type_parcours'],
                    'regime_inscription' => $status['diff_type_parcours'],
                    'lieu' => $ville,
                    'niveau' => $ordre,
                    'annee_ouverte' => $status['diff_annee_ouverte'],
                    'plateforme_proposee' => $status['diff_plateforme_proposee'],
                    'ouverture_n_1' => $status['annee_ouverte_n_1'],
                    'integration_ecandidat_n_1' => $integrationEcN1,
                    'capacite_n_1' => $capaciteN1,
                    'ouverture_n' => $status['annee_ouverte_n'],
                    'integration_ecandidat_n' => $isECN ? 'OUI' : 'NON',
                    'capacite_n' => $capaciteN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];

                if ($groupKey !== null) {
                    $rows[$groupKey] = $row;
                } else {
                    $rows[] = $row;
                }
            }
        }

        foreach ($tcParcours as $gKey => $libelles) {
            $nom = $tcNoms[$gKey] ?? 'Tronc commun';
            $gType = $tcTypes[$gKey] ?? 'tc';
            $rows[$gKey]['parcours'] = $this->formatGroupParcoursLabel($nom, $libelles, $gType);
            if (!empty($tcLieux[$gKey])) {
                $rows[$gKey]['lieu'] = implode(' / ', array_unique($tcLieux[$gKey]));
            }
            if (!empty($tcRemarques[$gKey])) {
                $rows[$gKey]['remarques'] = implode(' | ', array_unique($tcRemarques[$gKey]));
            }
        }
        $rows = array_values($rows);

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ($a['diplome'] ?? '') . ((string) ($a['mention'] ?? '')) . ($a['niveau'] ?? '') . ((string) ($a['parcours'] ?? '')),
            ($b['composante']) . ($b['diplome'] ?? '') . ((string) ($b['mention'] ?? '')) . ($b['niveau'] ?? '') . ((string) ($b['parcours'] ?? ''))
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
        /** @var array<string, list<string>> $tcParcours */
        $tcParcours = [];
        /** @var array<string, list<string>> $tcLieux */
        $tcLieux = [];
        /** @var array<string, string> $tcNoms */
        $tcNoms = [];
        /** @var array<string, string> $tcTypes */
        $tcTypes = [];
        /** @var array<string, list<string>> $tcRemarques */
        $tcRemarques = [];
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
            $ville = $this->resolveLieu($parcours);
            $typeRecrutement = in_array($diplCode, ['BUT', 'DEUST', 'CPI'], true) ? 'Sélective' : 'Non sélective'; //todo: comment rendre dynamique ?

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

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $ordre, $psupPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $status = $this->resolveRowStatusAndDiff($parcours, $annee, $dpePar->isOuvert(), $param, $n1);

                // N data : un parcours/une année fermé(e) ou non intégré(e) à Parcoursup n'a pas de capacité N
                $isPsupN = $status['is_ouvert_n'] && $status['is_plateforme_active_n'];
                $capaciteN = 0;
                if ($isPsupN) {
                    $capaciteN = ($param && $param->getCapaciteGlobale() !== null)
                        ? $param->getCapaciteGlobale()
                        : $annee->getCapaciteAccueil();
                }

                $capaciteN1 = '';
                if ($n1['hasN1']) {
                    $isPsupN1 = $this->isIntegrationPlateforme($n1['paramN1'], $n1['isOuvertN1']);
                    $capaciteN1 = ($isPsupN1 && $n1['paramN1'] && $n1['paramN1']->isActive() && $n1['paramN1']->getCapaciteGlobale() !== null)
                        ? $n1['paramN1']->getCapaciteGlobale()
                        : ($isPsupN1 ? ($n1['anneeN1']?->getCapaciteAccueil() ?? '') : '');
                }

                // Mutualisation (Tronc commun ou Portail) : regroupement uniquement des parcours membres
                $mutualisation = $this->resolveMutualisationGroup($formation, $parcours, $annee);
                $groupKey = $mutualisation['key'] ?? null;
                if ($groupKey !== null) {
                    if (isset($rows[$groupKey])) {
                        if (!$parcours->isParcoursDefaut()) {
                            $tcParcours[$groupKey][] = (string) $parcours->getLibelle();
                        }
                        if ($ville !== '') {
                            $tcLieux[$groupKey][] = $ville;
                        }
                        if ($param?->getRemarques()) {
                            $tcRemarques[$groupKey][] = $param->getRemarques();
                        }
                        continue;
                    }
                    $tcParcours[$groupKey] = !$parcours->isParcoursDefaut() ? [(string) $parcours->getLibelle()] : [];
                    $tcLieux[$groupKey] = $ville !== '' ? [$ville] : [];
                    $tcNoms[$groupKey] = $mutualisation['nom'];
                    $tcTypes[$groupKey] = $mutualisation['type'];
                    $tcRemarques[$groupKey] = $param?->getRemarques() ? [$param->getRemarques()] : [];
                }

                $diffs = $this->resolveEntityDiff($parcours, $n1['dpeN1']);

                $row = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'niveau' => $annee->getOrdre(),
                    'mention' => $diffs['mention'],
                    'parcours' => $diffs['parcours'],
                    'type_parcours' => $status['diff_type_parcours'],
                    'regime_inscription' => $status['diff_type_parcours'],
                    'lieu' => $ville,
                    'annee_ouverte' => $status['diff_annee_ouverte'],
                    'plateforme_proposee' => $status['diff_plateforme_proposee'],
                    'annee_ouverte_n' => $status['annee_ouverte_n'],
                    'annee_ouverte_n_1' => $status['annee_ouverte_n_1'],
                    'plateforme_proposee_n' => $status['plateforme_proposee_n'],
                    'plateforme_proposee_n_1' => $status['plateforme_proposee_n_1'],
                    'ouverture_n' => $status['annee_ouverte_n'],
                    'ouverture_n_1' => $status['annee_ouverte_n_1'],
                    'statut' => 'Public',
                    'type_recrutement' => $typeRecrutement,
                    'capacite_n_1' => $capaciteN1,
                    'capacite_n' => $capaciteN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];

                if ($groupKey !== null) {
                    $rows[$groupKey] = $row;
                } else {
                    $rows[] = $row;
                }
            }
        }

        foreach ($tcParcours as $gKey => $libelles) {
            $nom = $tcNoms[$gKey] ?? 'Tronc commun';
            $gType = $tcTypes[$gKey] ?? 'tc';
            $rows[$gKey]['parcours'] = $this->formatGroupParcoursLabel($nom, $libelles, $gType);
            if (!empty($tcLieux[$gKey])) {
                $rows[$gKey]['lieu'] = implode(' / ', array_unique($tcLieux[$gKey]));
            }
            if (!empty($tcRemarques[$gKey])) {
                $rows[$gKey]['remarques'] = implode(' | ', array_unique($tcRemarques[$gKey]));
            }
        }
        $rows = array_values($rows);

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ($a['diplome']) . ($a['niveau'] ?? '') . ((string) $a['mention']) . ((string) $a['parcours']),
            ($b['composante']) . ($b['diplome']) . ($b['niveau'] ?? '') . ((string) $b['mention']) . ((string) $b['parcours'])
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
        /** @var array<string, list<string>> $tcParcours */
        $tcParcours = [];
        /** @var array<string, list<string>> $tcLieux */
        $tcLieux = [];
        /** @var array<string, string> $tcNoms */
        $tcNoms = [];
        /** @var array<string, string> $tcTypes */
        $tcTypes = [];
        /** @var array<string, list<string>> $tcRemarques */
        $tcRemarques = [];
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

            $ville = $this->resolveLieu($parcours);
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

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $ordre, $mmPlatform, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $status = $this->resolveRowStatusAndDiff($parcours, $annee, $dpePar->isOuvert(), $param, $n1);

                // N data
                $capGlobaleN = $param?->getCapaciteGlobale() ?? $annee->getCapaciteAccueil();
                $capFiN = $param?->getCapaciteFi() ?? $capGlobaleN;
                $capAltN = $param?->getCapaciteAlternance() ?? 0;
                $capSpeN = $param?->getCapaciteSpecifique() ?? 0;

                $capGlobaleN1 = '';
                $capFiN1 = '';
                $capAltN1 = '';
                $capSpeN1 = '';

                if ($n1['hasN1']) {
                    $capGlobaleN1 = $n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '';
                    $capFiN1 = $n1['paramN1']?->getCapaciteFi() ?? $capGlobaleN1;
                    $capAltN1 = $n1['paramN1']?->getCapaciteAlternance() ?? 0;
                    $capSpeN1 = $n1['paramN1']?->getCapaciteSpecifique() ?? 0;
                }

                $mutualisation = $this->resolveMutualisationGroup($formation, $parcours, $annee);
                $groupKey = $mutualisation['key'] ?? null;
                if ($groupKey !== null) {
                    if (isset($rows[$groupKey])) {
                        if (!$parcours->isParcoursDefaut()) {
                            $tcParcours[$groupKey][] = (string) $parcours->getLibelle();
                        }
                        if ($ville !== '') {
                            $tcLieux[$groupKey][] = $ville;
                        }
                        if ($param?->getRemarques()) {
                            $tcRemarques[$groupKey][] = $param->getRemarques();
                        }
                        continue;
                    }
                    $tcParcours[$groupKey] = !$parcours->isParcoursDefaut() ? [(string) $parcours->getLibelle()] : [];
                    $tcLieux[$groupKey] = $ville !== '' ? [$ville] : [];
                    $tcNoms[$groupKey] = $mutualisation['nom'];
                    $tcTypes[$groupKey] = $mutualisation['type'];
                    $tcRemarques[$groupKey] = $param?->getRemarques() ? [$param->getRemarques()] : [];
                }

                $diffs = $this->resolveEntityDiff($parcours, $n1['dpeN1']);

                $row = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'niveau' => $annee->getOrdre(),
                    'mention' => $diffs['mention'],
                    'parcours' => $diffs['parcours'],
                    'type_parcours' => $status['diff_type_parcours'],
                    'regime_inscription' => $status['diff_type_parcours'],
                    'lieu' => $ville,
                    'annee_ouverte' => $status['diff_annee_ouverte'],
                    'plateforme_proposee' => $status['diff_plateforme_proposee'],
                    'annee_ouverte_n' => $status['annee_ouverte_n'],
                    'annee_ouverte_n_1' => $status['annee_ouverte_n_1'],
                    'plateforme_proposee_n' => $status['plateforme_proposee_n'],
                    'plateforme_proposee_n_1' => $status['plateforme_proposee_n_1'],
                    'ouverture_n_1' => $status['annee_ouverte_n_1'],
                    'capacite_globale_n_1' => $capGlobaleN1,
                    'capacite_fi_n_1' => $capFiN1,
                    'capacite_alt_n_1' => $capAltN1,
                    'capacite_spe_n_1' => $capSpeN1,
                    'ouverture_n' => $status['annee_ouverte_n'],
                    'capacite_globale_n' => $capGlobaleN,
                    'capacite_fi_n' => $capFiN,
                    'capacite_alt_n' => $capAltN,
                    'capacite_spe_n' => $capSpeN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];

                if ($groupKey !== null) {
                    $rows[$groupKey] = $row;
                } else {
                    $rows[] = $row;
                }
            }
        }

        foreach ($tcParcours as $gKey => $libelles) {
            $nom = $tcNoms[$gKey] ?? 'Tronc commun';
            $gType = $tcTypes[$gKey] ?? 'tc';
            $rows[$gKey]['parcours'] = $this->formatGroupParcoursLabel($nom, $libelles, $gType);
            if (!empty($tcLieux[$gKey])) {
                $rows[$gKey]['lieu'] = implode(' / ', array_unique($tcLieux[$gKey]));
            }
            if (!empty($tcRemarques[$gKey])) {
                $rows[$gKey]['remarques'] = implode(' | ', array_unique($tcRemarques[$gKey]));
            }
        }
        $rows = array_values($rows);

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ((string) $a['mention']) . ((string) $a['parcours']),
            ($b['composante']) . ((string) $b['mention']) . ((string) $b['parcours'])
        ));
        return $rows;
    }

    /**
     * @param array<int, DpeParcours> $allDpeParcours
     * @param array<int, list<Annee>> $anneesByParcours
     * @param array<int, DpeParcours> $dpeN1ByParcours
     */
    private function buildEefRows(
        array $allDpeParcours,
        array $anneesByParcours,
        CampagneCollecte $campagne,
        ?TypeDiplome $filterTypeDiplome = null,
        array $dpeN1ByParcours = [],
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

            $ville = $this->resolveLieu($parcours);
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';

            $isOuvert = $dpePar->isOuvert();

            $niveauxOuverts = [];
            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            foreach ($parAnnees as $annee) {
                if ($annee->isOuvert()) {
                    $prefix = in_array($diplCode, ['M', 'MASTER', 'MA'], true) ? 'M' : 'L';
                    $niveauxOuverts[] = $prefix . $annee->getOrdre();
                }
            }
            $niveauxStr = !empty($niveauxOuverts) ? implode('/', $niveauxOuverts) : '-';

            $parcoursN1 = $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();
            $dpeN1 = null;
            if ($parcoursN1 !== null && isset($dpeN1ByParcours[$parcoursN1->getId()])) {
                $dpeN1 = $dpeN1ByParcours[$parcoursN1->getId()];
            } elseif (isset($dpeN1ByParcours[$parcours->getId()])) {
                $dpeN1 = $dpeN1ByParcours[$parcours->getId()];
            }
            $diffs = $this->resolveEntityDiff($parcours, $dpeN1);

            $regimeN = $this->resolveRegimeInscription($parcours);
            $regimeN1 = $parcoursN1 !== null ? $this->resolveRegimeInscription($parcoursN1) : '';
            $diffTypeParcours = $dpeN1 !== null ? new DiffObject($regimeN1 !== '' ? $regimeN1 : null, $regimeN) : new DiffObject(null, $regimeN);

            $rows[] = [
                'composante' => $compLibelle,
                'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                'niveau' => $niveauxStr,
                'mention' => $diffs['mention'],
                'parcours' => $diffs['parcours'],
                'type_parcours' => $diffTypeParcours,
                'regime_inscription' => $diffTypeParcours,
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

        usort($rows, static fn($a, $b) => strcmp($a['composante'] . $a['diplome'] . $a['niveau'] . ((string) $a['mention']), $b['composante'] . $b['diplome'] . $b['niveau'] . ((string) $b['mention'])));
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
        /** @var array<string, list<string>> $tcParcours */
        $tcParcours = [];
        /** @var array<string, list<string>> $tcLieux */
        $tcLieux = [];
        /** @var array<string, string> $tcNoms */
        $tcNoms = [];
        /** @var array<string, string> $tcTypes */
        $tcTypes = [];
        /** @var array<string, list<string>> $tcRemarques */
        $tcRemarques = [];
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

            $ville = $this->resolveLieu($parcours);
            $compLibelle = $formation->getComposantePorteuse()?->getSigle() ?: $formation->getComposantePorteuse()?->getLibelle() ?: '';
            $typeParcours = $parcours->getTypeParcours()?->libelle() ?? 'Classique';

            $parAnnees = $anneesByParcours[$parcours->getId()] ?? [];
            if (empty($parAnnees)) {
                $capaciteGlobaleN = $formation->getCapaciteAccueil();

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, 1, $plateforme, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $status = $this->resolveRowStatusAndDiff($parcours, null, $dpePar->isOuvert(), null, $n1);
                $capGlobaleN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '') : '';
                $capFiN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteFi() ?? $capGlobaleN1) : '';
                $capFcN1 = $n1['hasN1'] ? 0 : '';
                $capFpN1 = $n1['hasN1'] ? 0 : '';
                $capAltN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteAlternance() ?? 0) : '';
                $capSpeN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteSpecifique() ?? 0) : '';

                $diffs = $this->resolveEntityDiff($parcours, $n1['dpeN1']);

                $rows[] = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'niveau' => 1,
                    'mention' => $diffs['mention'],
                    'parcours' => $diffs['parcours'],
                    'type_parcours' => $status['diff_type_parcours'],
                    'regime_inscription' => $status['diff_type_parcours'],
                    'lieu' => $ville,
                    'annee_ouverte' => $status['diff_annee_ouverte'],
                    'plateforme_proposee' => $status['diff_plateforme_proposee'],
                    'annee_ouverte_n' => $status['annee_ouverte_n'],
                    'annee_ouverte_n_1' => $status['annee_ouverte_n_1'],
                    'plateforme_proposee_n' => $status['plateforme_proposee_n'],
                    'plateforme_proposee_n_1' => $status['plateforme_proposee_n_1'],
                    'ouvert_n_1' => $status['annee_ouverte_n_1'],
                    'capacite_fi_n_1' => $capFiN1,
                    'capacite_fc_n_1' => $capFcN1,
                    'capacite_fp_n_1' => $capFpN1,
                    'capacite_alt_n_1' => $capAltN1,
                    'capacite_spe_n_1' => $capSpeN1,
                    'capacite_globale_n_1' => $capGlobaleN1,
                    'ouvert_n' => $status['annee_ouverte_n'],
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

                // N-1 Data
                $n1 = $this->resolveN1Data($parcours, $annee->getOrdre(), $plateforme, $dpeN1ByParcours, $anneesN1ByParcours, $paramsN1ByAnnee);
                $status = $this->resolveRowStatusAndDiff($parcours, $annee, $dpePar->isOuvert(), $param, $n1);

                $capGlobaleN = $param?->getCapaciteGlobale() ?? $annee->getCapaciteAccueil();

                $capGlobaleN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteGlobale() ?? $n1['anneeN1']?->getCapaciteAccueil() ?? '') : '';
                $capFiN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteFi() ?? $capGlobaleN1) : '';
                $capFcN1 = $n1['hasN1'] ? 0 : '';
                $capFpN1 = $n1['hasN1'] ? 0 : '';
                $capAltN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteAlternance() ?? 0) : '';
                $capSpeN1 = $n1['hasN1'] ? ($n1['paramN1']?->getCapaciteSpecifique() ?? 0) : '';
                $mutualisation = $this->resolveMutualisationGroup($formation, $parcours, $annee);
                $groupKey = $mutualisation['key'] ?? null;
                if ($groupKey !== null) {
                    if (isset($rows[$groupKey])) {
                        if (!$parcours->isParcoursDefaut()) {
                            $tcParcours[$groupKey][] = (string) $parcours->getLibelle();
                        }
                        if ($ville !== '') {
                            $tcLieux[$groupKey][] = $ville;
                        }
                        if ($param?->getRemarques()) {
                            $tcRemarques[$groupKey][] = $param->getRemarques();
                        }
                        continue;
                    }
                    $tcParcours[$groupKey] = !$parcours->isParcoursDefaut() ? [(string) $parcours->getLibelle()] : [];
                    $tcLieux[$groupKey] = $ville !== '' ? [$ville] : [];
                    $tcNoms[$groupKey] = $mutualisation['nom'];
                    $tcTypes[$groupKey] = $mutualisation['type'];
                    $tcRemarques[$groupKey] = $param?->getRemarques() ? [$param->getRemarques()] : [];
                }

                $diffs = $this->resolveEntityDiff($parcours, $n1['dpeN1']);

                $row = [
                    'composante' => $compLibelle,
                    'diplome' => $typeDiplome->getLibelleCourt() ?: $typeDiplome->getLibelle(),
                    'niveau' => $annee->getOrdre(),
                    'mention' => $diffs['mention'],
                    'parcours' => $diffs['parcours'],
                    'type_parcours' => $status['diff_type_parcours'],
                    'regime_inscription' => $status['diff_type_parcours'],
                    'lieu' => $ville,
                    'annee_ouverte' => $status['diff_annee_ouverte'],
                    'plateforme_proposee' => $status['diff_plateforme_proposee'],
                    'annee_ouverte_n' => $status['annee_ouverte_n'],
                    'annee_ouverte_n_1' => $status['annee_ouverte_n_1'],
                    'plateforme_proposee_n' => $status['plateforme_proposee_n'],
                    'plateforme_proposee_n_1' => $status['plateforme_proposee_n_1'],
                    'ouvert_n_1' => $status['annee_ouverte_n_1'],
                    'capacite_fi_n_1' => $capFiN1,
                    'capacite_fc_n_1' => $capFcN1,
                    'capacite_fp_n_1' => $capFpN1,
                    'capacite_alt_n_1' => $capAltN1,
                    'capacite_spe_n_1' => $capSpeN1,
                    'capacite_globale_n_1' => $capGlobaleN1,
                    'ouvert_n' => $status['annee_ouverte_n'],
                    'capacite_fi_n' => $param?->getCapaciteFi() ?? $capGlobaleN,
                    'capacite_fc_n' => 0,
                    'capacite_fp_n' => 0,
                    'capacite_alt_n' => $param?->getCapaciteAlternance() ?? 0,
                    'capacite_spe_n' => $param?->getCapaciteSpecifique() ?? 0,
                    'capacite_globale_n' => $capGlobaleN,
                    'remarques' => $param?->getRemarques() ?? '',
                ];

                if ($groupKey !== null) {
                    $rows[$groupKey] = $row;
                } else {
                    $rows[] = $row;
                }
            }
        }

        foreach ($tcParcours as $gKey => $libelles) {
            $nom = $tcNoms[$gKey] ?? 'Tronc commun';
            $gType = $tcTypes[$gKey] ?? 'tc';
            $rows[$gKey]['parcours'] = $this->formatGroupParcoursLabel($nom, $libelles, $gType);
            if (!empty($tcLieux[$gKey])) {
                $rows[$gKey]['lieu'] = implode(' / ', array_unique($tcLieux[$gKey]));
            }
            if (!empty($tcRemarques[$gKey])) {
                $rows[$gKey]['remarques'] = implode(' | ', array_unique($tcRemarques[$gKey]));
            }
        }
        $rows = array_values($rows);

        usort($rows, static fn($a, $b) => strcmp(
            ($a['composante']) . ($a['diplome']) . ($a['niveau'] ?? '') . ((string) $a['mention']) . ((string) $a['parcours']),
            ($b['composante']) . ($b['diplome']) . ($b['niveau'] ?? '') . ((string) $b['mention']) . ((string) $b['parcours'])
        ));
        return $rows;
    }
}
