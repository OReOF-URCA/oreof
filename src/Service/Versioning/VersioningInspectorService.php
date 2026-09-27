<?php

declare(strict_types=1);

namespace App\Service\Versioning;

use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereVersioning;
use App\Entity\Formation;
use App\Entity\FormationVersioning;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class VersioningInspectorService
{
    private string $versioningDir;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        $this->versioningDir = rtrim($this->projectDir, '/') . '/versioning_json';
    }

    public function getVersioningDir(): string
    {
        return $this->versioningDir;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobalStats(): array
    {
        $formationTotalDb = (int) $this->entityManager->createQuery(
            'SELECT COUNT(f.id) FROM App\Entity\Formation f'
        )->getSingleScalarResult();

        $formationWithVersionsDb = (int) $this->entityManager->createQuery(
            'SELECT COUNT(DISTINCT f.id) FROM App\Entity\Formation f JOIN f.formationVersionings fv'
        )->getSingleScalarResult();

        $parcoursTotalDb = (int) $this->entityManager->createQuery(
            'SELECT COUNT(p.id) FROM App\Entity\Parcours p'
        )->getSingleScalarResult();

        $parcoursWithVersionsDb = (int) $this->entityManager->createQuery(
            'SELECT COUNT(DISTINCT p.id) FROM App\Entity\Parcours p JOIN p.parcoursVersionings pv'
        )->getSingleScalarResult();

        $ficheTotalDb = (int) $this->entityManager->createQuery(
            'SELECT COUNT(fm.id) FROM App\Entity\FicheMatiere fm'
        )->getSingleScalarResult();

        $ficheWithVersionsDb = (int) $this->entityManager->createQuery(
            'SELECT COUNT(DISTINCT fm.id) FROM App\Entity\FicheMatiere fm JOIN fm.ficheMatiereVersionings fmv'
        )->getSingleScalarResult();

        // Disk stats
        $diskFilesFormation = $this->countFilesInDir($this->versioningDir . '/formation');
        $diskFilesParcours = $this->countFilesInDir($this->versioningDir . '/parcours');
        $diskFilesFiche = $this->countFilesInDir($this->versioningDir . '/fiche-matiere');
        $diskTotalBytes = $this->calculateDirSize($this->versioningDir);

        return [
            'formation' => [
                'total_db' => $formationTotalDb,
                'with_versions_db' => $formationWithVersionsDb,
                'without_versions_db' => max(0, $formationTotalDb - $formationWithVersionsDb),
                'disk_files_count' => $diskFilesFormation,
            ],
            'parcours' => [
                'total_db' => $parcoursTotalDb,
                'with_versions_db' => $parcoursWithVersionsDb,
                'without_versions_db' => max(0, $parcoursTotalDb - $parcoursWithVersionsDb),
                'disk_files_count' => $diskFilesParcours,
            ],
            'fiche_matiere' => [
                'total_db' => $ficheTotalDb,
                'with_versions_db' => $ficheWithVersionsDb,
                'without_versions_db' => max(0, $ficheTotalDb - $ficheWithVersionsDb),
                'disk_files_count' => $diskFilesFiche,
            ],
            'disk' => [
                'total_bytes' => $diskTotalBytes,
                'total_formatted' => $this->formatBytes($diskTotalBytes),
                'total_files' => $diskFilesFormation + $diskFilesParcours + $diskFilesFiche,
                'exists' => is_dir($this->versioningDir),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormationVersioningInfo(Formation $formation): array
    {
        $versionings = $formation->getFormationVersionings();
        $versionsList = [];
        $missingFilesCount = 0;
        $existingFilesCount = 0;
        $latestTimestamp = null;

        foreach ($versionings as $v) {
            $slug = $v->getSlug() ?: $formation->getSlug();
            $filename = $v->getFilename();
            $relativePath = "formation/{$slug}/{$filename}.json";
            $fullPath = "{$this->versioningDir}/{$relativePath}";
            $exists = is_file($fullPath);
            $fileSize = $exists ? (filesize($fullPath) ?: 0) : 0;
            $fileMtime = $exists ? (new DateTimeImmutable())->setTimestamp(filemtime($fullPath) ?: 0) : null;

            if ($exists) {
                ++$existingFilesCount;
            } else {
                ++$missingFilesCount;
            }

            $ts = $v->getVersionTimestamp();
            if ($latestTimestamp === null || ($ts && $ts > $latestTimestamp)) {
                $latestTimestamp = $ts;
            }

            $versionsList[] = [
                'id' => $v->getId(),
                'filename' => $filename,
                'slug' => $slug,
                'timestamp' => $ts,
                'cfvu' => $v->isCfvuFlag(),
                'relative_path' => $relativePath,
                'full_path' => $fullPath,
                'exists' => $exists,
                'size' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'mtime' => $fileMtime,
            ];
        }

        $computedStatus = 'ok';
        if (count($versionsList) === 0) {
            $computedStatus = 'no_version';
        } elseif ($missingFilesCount > 0) {
            $computedStatus = 'missing_file';
        }

        $lastDpe = $formation->getDpeFormations()->last();
        $campagne = ($lastDpe instanceof \App\Entity\DpeFormation ? $lastDpe->getCampagneCollecte()?->getLibelle() : null) ?? $formation->getDpe()?->getLibelle();

        return [
            'id' => $formation->getId(),
            'display' => $formation->getDisplayLong() ?: $formation->getDisplay(),
            'slug' => $formation->getSlug(),
            'sigle' => $formation->getSigle(),
            'campagne' => $campagne,
            'composante' => $formation->getComposantePorteuse()?->getLibelle(),
            'type_diplome' => $formation->getTypeDiplome()?->getLibelleCourt() ?? $formation->getTypeDiplome()?->getLibelle(),
            'versions' => $versionsList,
            'versions_count' => count($versionsList),
            'existing_files_count' => $existingFilesCount,
            'missing_files_count' => $missingFilesCount,
            'last_version_date' => $latestTimestamp,
            'status' => $computedStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getParcoursVersioningInfo(Parcours $parcours): array
    {
        $versionings = $parcours->getParcoursVersionings();
        $versionsList = [];
        $missingFilesCount = 0;
        $existingFilesCount = 0;
        $latestTimestamp = null;

        foreach ($versionings as $v) {
            $parcoursFile = $v->getParcoursFileName();
            $dtoFile = $v->getDtoFileName();
            $parcoursRelative = "parcours/{$parcours->getId()}/{$parcoursFile}.json";
            $parcoursFull = "{$this->versioningDir}/{$parcoursRelative}";
            $parcoursExists = is_file($parcoursFull);
            $parcoursSize = $parcoursExists ? (filesize($parcoursFull) ?: 0) : 0;

            $dtoRelative = $dtoFile ? "parcours/{$parcours->getId()}/{$dtoFile}.json" : null;
            $dtoFull = $dtoRelative ? "{$this->versioningDir}/{$dtoRelative}" : null;
            $dtoExists = $dtoFull && is_file($dtoFull);
            $dtoSize = $dtoExists ? (filesize($dtoFull) ?: 0) : 0;

            if ($parcoursExists) {
                ++$existingFilesCount;
            } else {
                ++$missingFilesCount;
            }

            if ($dtoFile) {
                if ($dtoExists) {
                    ++$existingFilesCount;
                } else {
                    ++$missingFilesCount;
                }
            }

            $ts = $v->getVersionTimestamp();
            if ($latestTimestamp === null || ($ts && $ts > $latestTimestamp)) {
                $latestTimestamp = $ts;
            }

            $versionsList[] = [
                'id' => $v->getId(),
                'parcours_file' => $parcoursFile,
                'parcours_relative' => $parcoursRelative,
                'parcours_exists' => $parcoursExists,
                'parcours_size' => $parcoursSize,
                'parcours_size_formatted' => $this->formatBytes($parcoursSize),
                'dto_file' => $dtoFile,
                'dto_relative' => $dtoRelative,
                'dto_exists' => $dtoExists,
                'dto_size' => $dtoSize,
                'dto_size_formatted' => $this->formatBytes($dtoSize),
                'timestamp' => $ts,
                'cfvu' => $v->isCvfuFlag(),
            ];
        }

        $computedStatus = 'ok';
        if (count($versionsList) === 0) {
            $computedStatus = 'no_version';
        } elseif ($missingFilesCount > 0) {
            $computedStatus = 'missing_file';
        }

        $lastDpe = $parcours->getDpeParcours()->last();
        $campagne = ($lastDpe instanceof \App\Entity\DpeParcours ? $lastDpe->getCampagneCollecte()?->getLibelle() : null) ?? $parcours->getFormation()?->getDpe()?->getLibelle();

        return [
            'id' => $parcours->getId(),
            'display' => $parcours->getDisplay(),
            'formation_display' => $parcours->getFormation()?->getDisplayLong() ?: $parcours->getFormation()?->getDisplay(),
            'formation_id' => $parcours->getFormation()?->getId(),
            'campagne' => $campagne,
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle(),
            'type_diplome' => $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt(),
            'versions' => $versionsList,
            'versions_count' => count($versionsList),
            'existing_files_count' => $existingFilesCount,
            'missing_files_count' => $missingFilesCount,
            'last_version_date' => $latestTimestamp,
            'status' => $computedStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFicheMatiereVersioningInfo(FicheMatiere $fiche): array
    {
        $versionings = $fiche->getFicheMatiereVersionings();
        $versionsList = [];
        $missingFilesCount = 0;
        $existingFilesCount = 0;
        $latestTimestamp = null;

        foreach ($versionings as $v) {
            $slug = $v->getSlug() ?: $fiche->getSlug();
            $filename = $v->getFilename();
            $relativePath = "fiche-matiere/{$slug}/{$filename}.json";
            $fullPath = "{$this->versioningDir}/{$relativePath}";
            $exists = is_file($fullPath);
            $fileSize = $exists ? (filesize($fullPath) ?: 0) : 0;
            $fileMtime = $exists ? (new DateTimeImmutable())->setTimestamp(filemtime($fullPath) ?: 0) : null;

            if ($exists) {
                ++$existingFilesCount;
            } else {
                ++$missingFilesCount;
            }

            $ts = $v->getVersionTimestamp();
            if ($latestTimestamp === null || ($ts && $ts > $latestTimestamp)) {
                $latestTimestamp = $ts;
            }

            $versionsList[] = [
                'id' => $v->getId(),
                'filename' => $filename,
                'slug' => $slug,
                'timestamp' => $ts,
                'version_valide' => $v->isVersionValide(),
                'relative_path' => $relativePath,
                'full_path' => $fullPath,
                'exists' => $exists,
                'size' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'mtime' => $fileMtime,
            ];
        }

        $computedStatus = 'ok';
        if (count($versionsList) === 0) {
            $computedStatus = 'no_version';
        } elseif ($missingFilesCount > 0) {
            $computedStatus = 'missing_file';
        }

        $composanteLabels = $fiche->getComposante()->map(
            static fn (Composante $c): string => $c->getLibelle() ?? ''
        )->filter(static fn (string $libelle): bool => $libelle !== '')->toArray();

        return [
            'id' => $fiche->getId(),
            'code' => $fiche->getSigle() ?: $fiche->getCodeApogee(),
            'libelle' => $fiche->getLibelle(),
            'slug' => $fiche->getSlug(),
            'campagne' => $fiche->getCampagneCollecte()?->getLibelle(),
            'composante' => implode(', ', $composanteLabels),
            'versions' => $versionsList,
            'versions_count' => count($versionsList),
            'existing_files_count' => $existingFilesCount,
            'missing_files_count' => $missingFilesCount,
            'last_version_date' => $latestTimestamp,
            'status' => $computedStatus,
        ];
    }

    /**
     * @return array{items: array<int, mixed>, total: int, page: int, limit: int, totalPages: int}
     */
    public function getFormationsList(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $status = null,
        ?int $composanteId = null
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('f', 'fv', 'c', 'td', 'm')
            ->from(Formation::class, 'f')
            ->leftJoin('f.formationVersionings', 'fv')
            ->leftJoin('f.composantePorteuse', 'c')
            ->leftJoin('f.typeDiplome', 'td')
            ->leftJoin('f.mention', 'm')
            ->orderBy('f.id', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('f.id = :searchId OR f.sigle LIKE :searchLike OR f.slug LIKE :searchLike OR m.libelle LIKE :searchLike')
                ->setParameter('searchId', is_numeric($search) ? (int) $search : 0)
                ->setParameter('searchLike', '%' . trim($search) . '%');
        }

        if ($composanteId !== null && $composanteId > 0) {
            $qb->andWhere('c.id = :composanteId')
                ->setParameter('composanteId', $composanteId);
        }

        if ($status === 'no_version') {
            $qb->andWhere('fv.id IS NULL');
        } elseif ($status === 'has_version') {
            $qb->andWhere('fv.id IS NOT NULL');
        }

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);
        $page = max(1, min($page, max(1, $totalPages)));

        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = [];
        foreach ($paginator as $formation) {
            if (!$formation instanceof Formation) {
                continue;
            }

            $versionings = $formation->getFormationVersionings();
            $versionsList = [];
            $missingFilesCount = 0;
            $existingFilesCount = 0;
            $latestTimestamp = null;

            foreach ($versionings as $v) {
                $slug = $v->getSlug() ?: $formation->getSlug();
                $filename = $v->getFilename();
                $relativePath = "formation/{$slug}/{$filename}.json";
                $fullPath = "{$this->versioningDir}/{$relativePath}";
                $exists = is_file($fullPath);
                $fileSize = $exists ? (filesize($fullPath) ?: 0) : 0;
                $fileMtime = $exists ? (new DateTimeImmutable())->setTimestamp(filemtime($fullPath) ?: 0) : null;

                if ($exists) {
                    ++$existingFilesCount;
                } else {
                    ++$missingFilesCount;
                }

                $ts = $v->getVersionTimestamp();
                if ($latestTimestamp === null || ($ts && $ts > $latestTimestamp)) {
                    $latestTimestamp = $ts;
                }

                $versionsList[] = [
                    'id' => $v->getId(),
                    'filename' => $filename,
                    'slug' => $slug,
                    'timestamp' => $ts,
                    'cfvu' => $v->isCfvuFlag(),
                    'relative_path' => $relativePath,
                    'full_path' => $fullPath,
                    'exists' => $exists,
                    'size' => $fileSize,
                    'size_formatted' => $this->formatBytes($fileSize),
                    'mtime' => $fileMtime,
                ];
            }

            $computedStatus = 'ok';
            if (count($versionsList) === 0) {
                $computedStatus = 'no_version';
            } elseif ($missingFilesCount > 0) {
                $computedStatus = 'missing_file';
            }

            // Filter if missing_file was specifically requested
            if ($status === 'missing_file' && $computedStatus !== 'missing_file') {
                continue;
            }

            $items[] = [
                'id' => $formation->getId(),
                'slug' => $formation->getSlug(),
                'display' => $formation->getDisplayLong() ?: $formation->getDisplay(),
                'sigle' => $formation->getSigle(),
                'composante' => $formation->getComposantePorteuse()?->getLibelle(),
                'type_diplome' => $formation->getTypeDiplome()?->getLibelleCourt() ?? $formation->getTypeDiplome()?->getLibelle(),
                'versions' => $versionsList,
                'versions_count' => count($versionsList),
                'existing_files_count' => $existingFilesCount,
                'missing_files_count' => $missingFilesCount,
                'last_version_date' => $latestTimestamp,
                'status' => $computedStatus,
            ];
        }

        return [
            'items' => $items,
            'total' => $totalItems,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * @return array{items: array<int, mixed>, total: int, page: int, limit: int, totalPages: int}
     */
    public function getParcoursList(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $status = null,
        ?int $composanteId = null
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p', 'pv', 'f', 'c', 'td')
            ->from(Parcours::class, 'p')
            ->leftJoin('p.parcoursVersionings', 'pv')
            ->leftJoin('p.formation', 'f')
            ->leftJoin('f.composantePorteuse', 'c')
            ->leftJoin('f.typeDiplome', 'td')
            ->orderBy('p.id', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('p.id = :searchId OR p.libelle LIKE :searchLike OR f.sigle LIKE :searchLike OR f.slug LIKE :searchLike')
                ->setParameter('searchId', is_numeric($search) ? (int) $search : 0)
                ->setParameter('searchLike', '%' . trim($search) . '%');
        }

        if ($composanteId !== null && $composanteId > 0) {
            $qb->andWhere('c.id = :composanteId')
                ->setParameter('composanteId', $composanteId);
        }

        if ($status === 'no_version') {
            $qb->andWhere('pv.id IS NULL');
        } elseif ($status === 'has_version') {
            $qb->andWhere('pv.id IS NOT NULL');
        }

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);
        $page = max(1, min($page, max(1, $totalPages)));

        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = [];
        foreach ($paginator as $parcours) {
            if (!$parcours instanceof Parcours) {
                continue;
            }

            $versionings = $parcours->getParcoursVersionings();
            $versionsList = [];
            $missingFilesCount = 0;
            $existingFilesCount = 0;
            $latestTimestamp = null;

            foreach ($versionings as $v) {
                $parcoursFile = $v->getParcoursFileName();
                $dtoFile = $v->getDtoFileName();
                $parcoursRelative = "parcours/{$parcours->getId()}/{$parcoursFile}.json";
                $parcoursFull = "{$this->versioningDir}/{$parcoursRelative}";
                $parcoursExists = is_file($parcoursFull);
                $parcoursSize = $parcoursExists ? (filesize($parcoursFull) ?: 0) : 0;

                $dtoRelative = $dtoFile ? "parcours/{$parcours->getId()}/{$dtoFile}.json" : null;
                $dtoFull = $dtoRelative ? "{$this->versioningDir}/{$dtoRelative}" : null;
                $dtoExists = $dtoFull && is_file($dtoFull);
                $dtoSize = $dtoExists ? (filesize($dtoFull) ?: 0) : 0;

                if ($parcoursExists) {
                    ++$existingFilesCount;
                } else {
                    ++$missingFilesCount;
                }

                if ($dtoFile) {
                    if ($dtoExists) {
                        ++$existingFilesCount;
                    } else {
                        ++$missingFilesCount;
                    }
                }

                $ts = $v->getVersionTimestamp();
                if ($latestTimestamp === null || ($ts && $ts > $latestTimestamp)) {
                    $latestTimestamp = $ts;
                }

                $versionsList[] = [
                    'id' => $v->getId(),
                    'parcours_file' => $parcoursFile,
                    'parcours_relative' => $parcoursRelative,
                    'parcours_exists' => $parcoursExists,
                    'parcours_size' => $parcoursSize,
                    'parcours_size_formatted' => $this->formatBytes($parcoursSize),
                    'dto_file' => $dtoFile,
                    'dto_relative' => $dtoRelative,
                    'dto_exists' => $dtoExists,
                    'dto_size' => $dtoSize,
                    'dto_size_formatted' => $this->formatBytes($dtoSize),
                    'timestamp' => $ts,
                    'cfvu' => $v->isCvfuFlag(),
                ];
            }

            $computedStatus = 'ok';
            if (count($versionsList) === 0) {
                $computedStatus = 'no_version';
            } elseif ($missingFilesCount > 0) {
                $computedStatus = 'missing_file';
            }

            if ($status === 'missing_file' && $computedStatus !== 'missing_file') {
                continue;
            }

            $items[] = [
                'id' => $parcours->getId(),
                'display' => $parcours->getDisplay(),
                'formation_display' => $parcours->getFormation()?->getDisplayLong() ?: $parcours->getFormation()?->getDisplay(),
                'formation_id' => $parcours->getFormation()?->getId(),
                'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle(),
                'type_diplome' => $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt(),
                'versions' => $versionsList,
                'versions_count' => count($versionsList),
                'existing_files_count' => $existingFilesCount,
                'missing_files_count' => $missingFilesCount,
                'last_version_date' => $latestTimestamp,
                'status' => $computedStatus,
            ];
        }

        return [
            'items' => $items,
            'total' => $totalItems,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * @return array{items: array<int, mixed>, total: int, page: int, limit: int, totalPages: int}
     */
    public function getFichesMatiereList(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $status = null,
        ?int $composanteId = null
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('fm', 'fmv', 'c')
            ->from(FicheMatiere::class, 'fm')
            ->leftJoin('fm.ficheMatiereVersionings', 'fmv')
            ->leftJoin('fm.composante', 'c')
            ->orderBy('fm.id', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('fm.id = :searchId OR fm.sigle LIKE :searchLike OR fm.codeApogee LIKE :searchLike OR fm.libelle LIKE :searchLike OR fm.slug LIKE :searchLike')
                ->setParameter('searchId', is_numeric($search) ? (int) $search : 0)
                ->setParameter('searchLike', '%' . trim($search) . '%');
        }

        if ($composanteId !== null && $composanteId > 0) {
            $qb->andWhere(':composanteId MEMBER OF fm.composante')
                ->setParameter('composanteId', $composanteId);
        }

        if ($status === 'no_version') {
            $qb->andWhere('fmv.id IS NULL');
        } elseif ($status === 'has_version') {
            $qb->andWhere('fmv.id IS NOT NULL');
        }

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);
        $page = max(1, min($page, max(1, $totalPages)));

        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = [];
        foreach ($paginator as $fiche) {
            if (!$fiche instanceof FicheMatiere) {
                continue;
            }

            $versionings = $fiche->getFicheMatiereVersionings();
            $versionsList = [];
            $missingFilesCount = 0;
            $existingFilesCount = 0;
            $latestTimestamp = null;

            foreach ($versionings as $v) {
                $slug = $v->getSlug() ?: $fiche->getSlug();
                $filename = $v->getFilename();
                $relativePath = "fiche-matiere/{$slug}/{$filename}.json";
                $fullPath = "{$this->versioningDir}/{$relativePath}";
                $exists = is_file($fullPath);
                $fileSize = $exists ? (filesize($fullPath) ?: 0) : 0;
                $fileMtime = $exists ? (new DateTimeImmutable())->setTimestamp(filemtime($fullPath) ?: 0) : null;

                if ($exists) {
                    ++$existingFilesCount;
                } else {
                    ++$missingFilesCount;
                }

                $ts = $v->getVersionTimestamp();
                if ($latestTimestamp === null || ($ts && $ts > $latestTimestamp)) {
                    $latestTimestamp = $ts;
                }

                $versionsList[] = [
                    'id' => $v->getId(),
                    'filename' => $filename,
                    'slug' => $slug,
                    'timestamp' => $ts,
                    'version_valide' => $v->isVersionValide(),
                    'relative_path' => $relativePath,
                    'full_path' => $fullPath,
                    'exists' => $exists,
                    'size' => $fileSize,
                    'size_formatted' => $this->formatBytes($fileSize),
                    'mtime' => $fileMtime,
                ];
            }

            $computedStatus = 'ok';
            if (count($versionsList) === 0) {
                $computedStatus = 'no_version';
            } elseif ($missingFilesCount > 0) {
                $computedStatus = 'missing_file';
            }

            if ($status === 'missing_file' && $computedStatus !== 'missing_file') {
                continue;
            }

            $composanteLabels = $fiche->getComposante()->map(
                static fn (Composante $c): string => $c->getLibelle() ?? ''
            )->filter(static fn (string $libelle): bool => $libelle !== '')->toArray();

            $items[] = [
                'id' => $fiche->getId(),
                'code' => $fiche->getSigle() ?: $fiche->getCodeApogee(),
                'libelle' => $fiche->getLibelle(),
                'slug' => $fiche->getSlug(),
                'composante' => implode(', ', $composanteLabels),
                'versions' => $versionsList,
                'versions_count' => count($versionsList),
                'existing_files_count' => $existingFilesCount,
                'missing_files_count' => $missingFilesCount,
                'last_version_date' => $latestTimestamp,
                'status' => $computedStatus,
            ];
        }

        return [
            'items' => $items,
            'total' => $totalItems,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Diagnostic logs and orphan detection
     *
     * @return array<string, mixed>
     */
    public function getOrphansAndLogs(): array
    {
        $logs = [];
        $logDirs = [$this->versioningDir . '/success_log', $this->versioningDir . '/error_log'];

        foreach ($logDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $dirName = basename($dir);
            foreach (scandir($dir) ?: [] as $file) {
                if ($file === '.' || $file === '..' || $file === '.DS_Store' || $file === '.gitignore') {
                    continue;
                }

                $filePath = $dir . '/' . $file;
                if (is_file($filePath)) {
                    $content = file_get_contents($filePath) ?: '';
                    $lines = array_values(array_filter(explode("\n", $content)));
                    $lastLines = array_slice($lines, -15);

                    $logs[] = [
                        'type' => $dirName === 'error_log' ? 'error' : 'success',
                        'filename' => $file,
                        'relative_path' => "{$dirName}/{$file}",
                        'size' => filesize($filePath) ?: 0,
                        'size_formatted' => $this->formatBytes(filesize($filePath) ?: 0),
                        'mtime' => (new DateTimeImmutable())->setTimestamp(filemtime($filePath) ?: 0),
                        'total_lines' => count($lines),
                        'last_lines' => $lastLines,
                    ];
                }
            }
        }

        return [
            'logs' => $logs,
        ];
    }

    /**
     * Safely reads and formats JSON file for inspection
     *
     * @return array<string, mixed>|null
     */
    public function getFileDetails(string $relativePath): ?array
    {
        // Prevent path traversal
        $relativePath = ltrim(str_replace(['../', '..\\'], '', $relativePath), '/');
        $fullPath = $this->versioningDir . '/' . $relativePath;

        // Ensure path remains inside versioning_json directory
        $realPath = realpath($fullPath);
        $realBase = realpath($this->versioningDir);

        if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase) || !is_file($realPath)) {
            return null;
        }

        $rawContent = file_get_contents($realPath);
        if ($rawContent === false) {
            return null;
        }

        $decoded = json_decode($rawContent, true);
        $formattedJson = $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $rawContent;
        $size = filesize($realPath) ?: 0;
        $mtime = (new DateTimeImmutable())->setTimestamp(filemtime($realPath) ?: 0);

        return [
            'filename' => basename($realPath),
            'relative_path' => $relativePath,
            'full_path' => $realPath,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'mtime' => $mtime,
            'is_valid_json' => $decoded !== null,
            'json_keys_count' => is_array($decoded) ? count($decoded) : 0,
            'content' => $formattedJson,
        ];
    }

    private function countFilesInDir(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                ++$count;
            }
        }

        return $count;
    }

    private function calculateDirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $bytes = max($bytes, 0);
        $pow = (int) floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $pow = max(0, min($pow, count($units) - 1));
        $formatted = $bytes / (1024 ** $pow);

        return round($formatted, $precision) . ' ' . $units[$pow];
    }
}
