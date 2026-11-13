<?php

declare(strict_types=1);

namespace App\Service\FicheMatiere;

use App\DTO\Comparison\FicheMatiereChangeItem;
use App\DTO\Comparison\FicheMatiereChangeReport;
use App\Entity\ButApprentissageCritique;
use App\Entity\Competence;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereVersioning;
use App\Entity\Mccc;
use App\Referentiel\ReferentielManager;
use App\Service\VersioningFicheMatiere;
use App\Service\VersioningParcours;
use Jfcherng\Diff\DiffHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FicheMatiereDiffAnalyzer
{
    private string $versioningDir;

    public function __construct(
        private readonly VersioningFicheMatiere $versioningFicheMatiere,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        private readonly ?ReferentielManager $referentiels = null,
    ) {
        $this->versioningDir = rtrim($projectDir, '/') . '/versioning_json/fiche-matiere';
    }

    /**
     * @param 'auto'|'snapshot'|'origine_n1' $preferredSource
     */
    public function analyze(FicheMatiere $fiche, string $preferredSource = 'auto'): FicheMatiereChangeReport
    {
        $snapshotRef = $this->findBestSnapshot($fiche);
        $origineN1 = $fiche->getFicheMatiereOrigineCopie();

        $hasSnapshot = $snapshotRef !== null;
        $hasOrigineN1 = $origineN1 instanceof FicheMatiere;

        if ($preferredSource === 'snapshot' && $hasSnapshot) {
            return $this->buildReportFromSnapshot($fiche, $snapshotRef, $hasSnapshot, $hasOrigineN1);
        }

        if ($preferredSource === 'origine_n1' && $hasOrigineN1) {
            return $this->buildReportFromOrigineN1($fiche, $origineN1, $hasSnapshot, $hasOrigineN1);
        }

        // Mode 'auto' : priorité au snapshot JSON, mais si le snapshot est identique à l'état courant
        // (ex: sauvegarde récente) et qu'une fiche N-1 existe avec des différences, basculer sur N-1.
        if ($hasSnapshot) {
            $snapshotReport = $this->buildReportFromSnapshot($fiche, $snapshotRef, $hasSnapshot, $hasOrigineN1);
            if ($snapshotReport->hasChanges() || !$hasOrigineN1) {
                return $snapshotReport;
            }

            $n1Report = $this->buildReportFromOrigineN1($fiche, $origineN1, $hasSnapshot, $hasOrigineN1);
            if ($n1Report->hasChanges()) {
                return $n1Report;
            }

            return $snapshotReport;
        }

        if ($hasOrigineN1) {
            return $this->buildReportFromOrigineN1($fiche, $origineN1, $hasSnapshot, $hasOrigineN1);
        }

        return new FicheMatiereChangeReport(
            ficheId: (int) $fiche->getId(),
            ficheLibelle: (string) $fiche->getLibelle(),
            ficheSlug: (string) $fiche->getSlug(),
            sourceType: FicheMatiereChangeReport::SOURCE_NONE,
            sourceLabel: 'Aucune version de référence disponible',
            hasSnapshotAvailable: false,
            hasOrigineN1Available: false,
            changes: [],
        );
    }

    /**
     * @return array{entity: ?FicheMatiere, raw: array<string, mixed>, label: string, type: string}|null
     */
    private function findBestSnapshot(FicheMatiere $fiche): ?array
    {
        $versionings = $fiche->getFicheMatiereVersionings()->toArray();
        if ($versionings === []) {
            return null;
        }

        usort(
            $versionings,
            static fn (FicheMatiereVersioning $a, FicheMatiereVersioning $b): int =>
                ($b->getVersionTimestamp()?->getTimestamp() ?? 0) <=> ($a->getVersionTimestamp()?->getTimestamp() ?? 0)
        );

        // Priorité aux versions marquées valides, sinon la plus récente existante sur disque
        $candidates = array_merge(
            array_filter($versionings, static fn (FicheMatiereVersioning $v): bool => (bool) $v->isVersionValide()),
            array_filter($versionings, static fn (FicheMatiereVersioning $v): bool => !$v->isVersionValide())
        );

        foreach ($candidates as $v) {
            $slug = $v->getSlug() ?: $fiche->getSlug();
            $filename = $v->getFilename();
            $fullPath = "{$this->versioningDir}/{$slug}/{$filename}.json";

            if (!is_file($fullPath)) {
                continue;
            }

            $jsonContent = file_get_contents($fullPath);
            if ($jsonContent === false || trim($jsonContent) === '') {
                continue;
            }

            $raw = json_decode($jsonContent, true);
            if (!is_array($raw)) {
                continue;
            }

            $deserializedEntity = null;
            try {
                $loaded = $this->versioningFicheMatiere->loadFicheMatiereVersion($v);
                if (isset($loaded['ficheMatiere']) && $loaded['ficheMatiere'] instanceof FicheMatiere) {
                    $deserializedEntity = $loaded['ficheMatiere'];
                }
            } catch (\Throwable) {
                // On conserve $raw comme source de vérité si la désérialisation échoue sur un ancien format
            }

            $dateStr = $v->getVersionTimestamp()?->format('d/m/Y à H:i') ?? 'date inconnue';
            $isValide = (bool) $v->isVersionValide();

            return [
                'entity' => $deserializedEntity,
                'raw' => $raw,
                'label' => $isValide
                    ? sprintf('Snapshot JSON validé du %s', $dateStr)
                    : sprintf('Snapshot JSON du %s', $dateStr),
                'type' => $isValide
                    ? FicheMatiereChangeReport::SOURCE_SNAPSHOT_VALIDE
                    : FicheMatiereChangeReport::SOURCE_SNAPSHOT_JSON,
            ];
        }

        return null;
    }

    /**
     * @param array{entity: ?FicheMatiere, raw: array<string, mixed>, label: string, type: string} $snapshot
     */
    private function buildReportFromSnapshot(
        FicheMatiere $current,
        array $snapshot,
        bool $hasSnapshot,
        bool $hasOrigineN1,
    ): FicheMatiereChangeReport {
        $refEntity = $snapshot['entity'];
        $raw = $snapshot['raw'];

        $changes = $refEntity instanceof FicheMatiere
            ? $this->compareEntities($refEntity, $current, isSnapshotSource: true, rawSnapshot: $raw)
            : $this->compareRawSnapshotToEntity($raw, $current);

        return new FicheMatiereChangeReport(
            ficheId: (int) $current->getId(),
            ficheLibelle: (string) $current->getLibelle(),
            ficheSlug: (string) $current->getSlug(),
            sourceType: $snapshot['type'],
            sourceLabel: $snapshot['label'],
            hasSnapshotAvailable: $hasSnapshot,
            hasOrigineN1Available: $hasOrigineN1,
            changes: $changes,
        );
    }

    private function buildReportFromOrigineN1(
        FicheMatiere $current,
        FicheMatiere $origineN1,
        bool $hasSnapshot,
        bool $hasOrigineN1,
    ): FicheMatiereChangeReport {
        $campagneLabel = $origineN1->getCampagneCollecte()?->getLibelle();
        $label = $campagneLabel !== null && $campagneLabel !== ''
            ? sprintf('Fiche d\'origine N-1 (%s)', $campagneLabel)
            : sprintf('Fiche d\'origine N-1 (#%d)', (int) $origineN1->getId());

        $changes = $this->compareEntities($origineN1, $current, isSnapshotSource: false);

        return new FicheMatiereChangeReport(
            ficheId: (int) $current->getId(),
            ficheLibelle: (string) $current->getLibelle(),
            ficheSlug: (string) $current->getSlug(),
            sourceType: FicheMatiereChangeReport::SOURCE_ORIGINE_N1,
            sourceLabel: $label,
            hasSnapshotAvailable: $hasSnapshot,
            hasOrigineN1Available: $hasOrigineN1,
            changes: $changes,
        );
    }

    /**
     * @param array<string, mixed> $rawSnapshot
     * @return list<FicheMatiereChangeItem>
     */
    private function compareEntities(
        FicheMatiere $ref,
        FicheMatiere $current,
        bool $isSnapshotSource,
        array $rawSnapshot = [],
    ): array {
        /** @var list<FicheMatiereChangeItem> $changes */
        $changes = [];

        // 1. Identité & organisation
        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'libelle',
            'Intitulé',
            $ref->getLibelle(),
            $current->getLibelle()
        );

        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'libelleAnglais',
            'Intitulé (anglais)',
            $ref->getLibelleAnglais(),
            $current->getLibelleAnglais()
        );

        if (!$isSnapshotSource || array_key_exists('sigle', $rawSnapshot)) {
            $this->addScalarChangeIfDiff(
                $changes,
                FicheMatiereChangeItem::CATEGORY_IDENTITE,
                'sigle',
                'Sigle / Code',
                $ref->getSigle(),
                $current->getSigle()
            );
        }

        if (!$isSnapshotSource) {
            $this->addScalarChangeIfDiff(
                $changes,
                FicheMatiereChangeItem::CATEGORY_IDENTITE,
                'typeMatiere',
                'Type de matière',
                $ref->getTypeMatiere(),
                $current->getTypeMatiere()
            );
        }

        $refResp = $this->formatResponsable($ref);
        $curResp = $this->formatResponsable($current);
        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'responsable',
            'Responsable de la fiche',
            $refResp,
            $curResp
        );

        $this->addBoolChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'enseignementMutualise',
            'Enseignement mutualisé',
            (bool) $ref->isEnseignementMutualise(),
            (bool) $current->isEnseignementMutualise()
        );

        $refModalite = $this->formatModalite($ref);
        $curModalite = $this->formatModalite($current);
        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'modaliteEnseignement',
            'Modalité d\'enseignement',
            $refModalite,
            $curModalite
        );

        $refLangDisp = implode(', ', $ref->getLanguesDispenseArray());
        $curLangDisp = implode(', ', $current->getLanguesDispenseArray());
        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'langueDispense',
            'Langues de dispense',
            $refLangDisp,
            $curLangDisp
        );

        $refLangSup = implode(', ', $ref->getLanguesSupportsArray());
        $curLangSup = implode(', ', $current->getLanguesSupportsArray());
        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'langueSupport',
            'Langues des supports',
            $refLangSup,
            $curLangSup
        );

        // 2. Descriptifs pédagogiques
        $this->addRichTextChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_DESCRIPTIFS,
            'description',
            'Description de l\'enseignement',
            $ref->getDescription(),
            $current->getDescription()
        );

        $this->addRichTextChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_DESCRIPTIFS,
            'objectifs',
            'Objectifs pédagogiques',
            $ref->getObjectifs(),
            $current->getObjectifs()
        );

        // 3. Volumes horaires
        $volumesMap = [
            'volumeCmPresentiel' => ['label' => 'CM Présentiel', 'ref' => (float) $ref->getVolumeCmPresentiel(), 'cur' => (float) $current->getVolumeCmPresentiel()],
            'volumeTdPresentiel' => ['label' => 'TD Présentiel', 'ref' => (float) $ref->getVolumeTdPresentiel(), 'cur' => (float) $current->getVolumeTdPresentiel()],
            'volumeTpPresentiel' => ['label' => 'TP Présentiel', 'ref' => (float) $ref->getVolumeTpPresentiel(), 'cur' => (float) $current->getVolumeTpPresentiel()],
            'volumeTe' => ['label' => 'Travail encadré (TE)', 'ref' => (float) $ref->getVolumeTe(), 'cur' => (float) $current->getVolumeTe()],
            'volumeCmDistanciel' => ['label' => 'CM Distanciel', 'ref' => (float) $ref->getVolumeCmDistanciel(), 'cur' => (float) $current->getVolumeCmDistanciel()],
            'volumeTdDistanciel' => ['label' => 'TD Distanciel', 'ref' => (float) $ref->getVolumeTdDistanciel(), 'cur' => (float) $current->getVolumeTdDistanciel()],
            'volumeTpDistanciel' => ['label' => 'TP Distanciel', 'ref' => (float) $ref->getVolumeTpDistanciel(), 'cur' => (float) $current->getVolumeTpDistanciel()],
            'totalHeures' => ['label' => 'Volume horaire total (prés. + dist.)', 'ref' => $this->calcTotalHeures($ref), 'cur' => $this->calcTotalHeures($current)],
        ];

        foreach ($volumesMap as $field => $info) {
            $this->addFloatChangeIfDiff(
                $changes,
                FicheMatiereChangeItem::CATEGORY_VOLUMES,
                $field,
                $info['label'],
                $info['ref'],
                $info['cur'],
                'h'
            );
        }

        if (!$isSnapshotSource) {
            $this->addBoolChangeIfDiff(
                $changes,
                FicheMatiereChangeItem::CATEGORY_VOLUMES,
                'sansHeures',
                'Matière sans heures',
                (bool) $ref->isSansHeures(),
                (bool) $current->isSansHeures()
            );
        }

        // 4. ECTS & MCCC
        $this->addFloatChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_MCCC,
            'ects',
            'Crédits ECTS',
            (float) ($ref->getEcts() ?? 0.0),
            (float) ($current->getEcts() ?? 0.0),
            'ECTS'
        );

        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_MCCC,
            'typeMccc',
            'Type de MCCC',
            $this->formatTypeMccc($ref->getTypeMccc()),
            $this->formatTypeMccc($current->getTypeMccc())
        );

        if (!$isSnapshotSource) {
            $this->addBoolChangeIfDiff(
                $changes,
                FicheMatiereChangeItem::CATEGORY_MCCC,
                'sansNote',
                'Matière sans note',
                (bool) $ref->isSansNote(),
                (bool) $current->isSansNote()
            );
            $this->addBoolChangeIfDiff(
                $changes,
                FicheMatiereChangeItem::CATEGORY_MCCC,
                'quitus',
                'Quitus',
                (bool) $ref->isQuitus(),
                (bool) $current->isQuitus()
            );
        }

        $refMcccSummary = $this->formatMcccCollection($ref->getMcccs()->toArray());
        $curMcccSummary = $this->formatMcccCollection($current->getMcccs()->toArray());
        if ($refMcccSummary !== $curMcccSummary) {
            $changes[] = new FicheMatiereChangeItem(
                category: FicheMatiereChangeItem::CATEGORY_MCCC,
                field: 'mcccs',
                label: 'Détail des épreuves MCCC',
                before: $refMcccSummary !== '' ? $refMcccSummary : 'Aucune épreuve définie',
                after: $curMcccSummary !== '' ? $curMcccSummary : 'Aucune épreuve définie',
            );
        }

        // 5. Compétences & Apprentissages critiques
        $refCompSummary = $this->formatCompetences($ref);
        $curCompSummary = $this->formatCompetences($current);
        if ($refCompSummary !== $curCompSummary) {
            $changes[] = new FicheMatiereChangeItem(
                category: FicheMatiereChangeItem::CATEGORY_COMPETENCES,
                field: 'competences',
                label: 'Compétences associées',
                before: $refCompSummary !== '' ? $refCompSummary : 'Aucune compétence associée',
                after: $curCompSummary !== '' ? $curCompSummary : 'Aucune compétence associée',
            );
        }

        if (!$isSnapshotSource) {
            $refAcSummary = $this->formatApprentissagesCritiques($ref);
            $curAcSummary = $this->formatApprentissagesCritiques($current);
            if ($refAcSummary !== $curAcSummary) {
                $changes[] = new FicheMatiereChangeItem(
                    category: FicheMatiereChangeItem::CATEGORY_COMPETENCES,
                    field: 'apprentissagesCritiques',
                    label: 'Apprentissages critiques (B.U.T.)',
                    before: $refAcSummary !== '' ? $refAcSummary : 'Aucun AC associé',
                    after: $curAcSummary !== '' ? $curAcSummary : 'Aucun AC associé',
                );
            }
        }

        return $changes;
    }

    /**
     * Fallback au cas où la désérialisation Symfony d'un ancien JSON lèverait une erreur.
     *
     * @param array<string, mixed> $raw
     * @return list<FicheMatiereChangeItem>
     */
    private function compareRawSnapshotToEntity(array $raw, FicheMatiere $current): array
    {
        /** @var list<FicheMatiereChangeItem> $changes */
        $changes = [];

        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'libelle',
            'Intitulé',
            isset($raw['libelle']) ? (string) $raw['libelle'] : null,
            $current->getLibelle()
        );

        $this->addScalarChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_IDENTITE,
            'libelleAnglais',
            'Intitulé (anglais)',
            isset($raw['libelleAnglais']) ? (string) $raw['libelleAnglais'] : null,
            $current->getLibelleAnglais()
        );

        $this->addRichTextChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_DESCRIPTIFS,
            'description',
            'Description de l\'enseignement',
            isset($raw['description']) ? (string) $raw['description'] : null,
            $current->getDescription()
        );

        $this->addRichTextChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_DESCRIPTIFS,
            'objectifs',
            'Objectifs pédagogiques',
            isset($raw['objectifs']) ? (string) $raw['objectifs'] : null,
            $current->getObjectifs()
        );

        $this->addFloatChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_VOLUMES,
            'volumeCmPresentiel',
            'CM Présentiel',
            (float) ($raw['volumeCmPresentiel'] ?? 0.0),
            (float) $current->getVolumeCmPresentiel(),
            'h'
        );

        $this->addFloatChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_VOLUMES,
            'volumeTdPresentiel',
            'TD Présentiel',
            (float) ($raw['volumeTdPresentiel'] ?? 0.0),
            (float) $current->getVolumeTdPresentiel(),
            'h'
        );

        $this->addFloatChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_VOLUMES,
            'volumeTpPresentiel',
            'TP Présentiel',
            (float) ($raw['volumeTpPresentiel'] ?? 0.0),
            (float) $current->getVolumeTpPresentiel(),
            'h'
        );

        $this->addFloatChangeIfDiff(
            $changes,
            FicheMatiereChangeItem::CATEGORY_MCCC,
            'ects',
            'Crédits ECTS',
            (float) ($raw['ects'] ?? 0.0),
            (float) ($current->getEcts() ?? 0.0),
            'ECTS'
        );

        return $changes;
    }

    /**
     * @param list<FicheMatiereChangeItem> $changes
     */
    private function addScalarChangeIfDiff(
        array &$changes,
        string $category,
        string $field,
        string $label,
        ?string $before,
        ?string $after,
    ): void {
        $normBefore = trim((string) $before);
        $normAfter = trim((string) $after);

        if ($normBefore === $normAfter) {
            return;
        }

        $changes[] = new FicheMatiereChangeItem(
            category: $category,
            field: $field,
            label: $label,
            before: $normBefore !== '' ? $normBefore : 'Non renseigné',
            after: $normAfter !== '' ? $normAfter : 'Non renseigné',
        );
    }

    /**
     * @param list<FicheMatiereChangeItem> $changes
     */
    private function addBoolChangeIfDiff(
        array &$changes,
        string $category,
        string $field,
        string $label,
        bool $before,
        bool $after,
    ): void {
        if ($before === $after) {
            return;
        }

        $changes[] = new FicheMatiereChangeItem(
            category: $category,
            field: $field,
            label: $label,
            before: $before ? 'Oui' : 'Non',
            after: $after ? 'Oui' : 'Non',
        );
    }

    /**
     * @param list<FicheMatiereChangeItem> $changes
     */
    private function addFloatChangeIfDiff(
        array &$changes,
        string $category,
        string $field,
        string $label,
        float $before,
        float $after,
        string $unit = '',
    ): void {
        if (abs($before - $after) < 0.001) {
            return;
        }

        $suffix = $unit !== '' ? ' ' . $unit : '';
        $delta = $after - $before;
        $deltaStr = sprintf(' (%s%s%s)', $delta > 0 ? '+' : '', $this->formatNumber($delta), $suffix);

        $changes[] = new FicheMatiereChangeItem(
            category: $category,
            field: $field,
            label: $label,
            before: $this->formatNumber($before) . $suffix,
            after: $this->formatNumber($after) . $suffix . $deltaStr,
        );
    }

    /**
     * @param list<FicheMatiereChangeItem> $changes
     */
    private function addRichTextChangeIfDiff(
        array &$changes,
        string $category,
        string $field,
        string $label,
        ?string $beforeHtml,
        ?string $afterHtml,
    ): void {
        $cleanBefore = $this->cleanHtmlToPlainText($beforeHtml);
        $cleanAfter = $this->cleanHtmlToPlainText($afterHtml);

        if ($cleanBefore === $cleanAfter) {
            return;
        }

        $htmlDiff = VersioningParcours::cleanUpComparison(
            html_entity_decode(
                DiffHelper::calculate(
                    VersioningParcours::cleanUpHtmlTextForComparison($beforeHtml ?? ''),
                    VersioningParcours::cleanUpHtmlTextForComparison($afterHtml ?? ''),
                    'Combined',
                    [
                        'context' => 2,
                        'ignoreWhitespace' => true,
                        'ignoreLineEnding' => true,
                    ],
                    [
                        'detailLevel' => 'word',
                        'lineNumbers' => false,
                        'showHeader' => false,
                        'separateBlock' => false,
                    ]
                )
            )
        );

        $changes[] = new FicheMatiereChangeItem(
            category: $category,
            field: $field,
            label: $label,
            before: $cleanBefore !== '' ? $cleanBefore : 'Non renseigné',
            after: $cleanAfter !== '' ? $cleanAfter : 'Non renseigné',
            htmlDiff: $htmlDiff !== '' ? $htmlDiff : null,
        );
    }

    private function cleanHtmlToPlainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $withBreaks = preg_replace('/<(br|\/p|\/div|\/li)[^>]*>/i', "\n", $html) ?? $html;
        $decoded = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        $lines = array_map(
            static fn (string $line): string => trim((string) preg_replace('/[ \t]+/', ' ', $line)),
            explode("\n", $decoded)
        );

        return trim(implode("\n", array_filter($lines, static fn (string $line): bool => $line !== '')));
    }

    private function formatResponsable(FicheMatiere $fiche): string
    {
        $resp = $fiche->getResponsableFicheMatiere();
        if ($resp === null) {
            return 'Non défini';
        }

        $nomPrenom = trim(($resp->getPrenom() ?? '') . ' ' . ($resp->getNom() ?? ''));

        return $nomPrenom !== '' ? $nomPrenom : ($resp->getEmail() ?? 'Non défini');
    }

    private function formatModalite(FicheMatiere $fiche): string
    {
        $value = $fiche->getModaliteEnseignementValue();
        if ($value === null || $value === '' || $value === '99') {
            return 'Non défini';
        }

        return $this->referentiels?->label('modalite_enseignement', $value)
            ?? $fiche->getModaliteEnseignement()?->libelle()
            ?? $value;
    }

    private function formatTypeMccc(?string $typeMccc): string
    {
        return match ($typeMccc) {
            'cc' => 'Contrôle continu (CC)',
            'cci' => 'Contrôle continu intégral (CCI)',
            'ct' => 'Contrôle terminal (CT)',
            'cc_ct' => 'Mixte (CC + CT)',
            null, '' => 'Non défini',
            default => strtoupper($typeMccc),
        };
    }

    /**
     * @param array<int, Mccc> $mcccs
     */
    private function formatMcccCollection(array $mcccs): string
    {
        if ($mcccs === []) {
            return '';
        }

        $items = [];
        foreach ($mcccs as $mccc) {
            if (!$mccc instanceof Mccc) {
                continue;
            }
            $session = $mccc->getNumeroSession() ?? 1;
            $num = $mccc->getNumeroEpreuve() ?? 1;
            $pct = $this->formatNumber((float) ($mccc->getPourcentage() ?? 0.0));
            $mode = $mccc->isControleContinu() ? 'CC' : ($mccc->isExamenTerminal() ? 'ET' : '');
            $duree = $mccc->getDuree()?->format('H\hi') ?? '';
            $secondeChance = $mccc->isSecondeChance() ? ' (2e chance)' : '';

            $parts = array_filter([
                sprintf('S%d-Ép.%d', $session, $num),
                trim((string) $mccc->getLibelle()),
                $mode !== '' ? "[{$mode}]" : '',
                "{$pct}%",
                $duree !== '' && $duree !== '00h00' ? "durée {$duree}" : '',
            ]);

            $items[] = implode(' ', $parts) . $secondeChance;
        }

        sort($items);

        return implode(' ; ', $items);
    }

    private function formatCompetences(FicheMatiere $fiche): string
    {
        $items = [];
        foreach ($fiche->getCompetences() as $comp) {
            if (!$comp instanceof Competence) {
                continue;
            }
            $code = trim((string) $comp->getCode());
            $libelle = trim((string) $comp->getLibelle());
            $items[] = $code !== '' ? "{$code} : {$libelle}" : $libelle;
        }

        sort($items);

        return implode(' | ', array_filter($items));
    }

    private function formatApprentissagesCritiques(FicheMatiere $fiche): string
    {
        $items = [];
        foreach ($fiche->getApprentissagesCritiques() as $ac) {
            if (!$ac instanceof ButApprentissageCritique) {
                continue;
            }
            $code = trim((string) $ac->getCode());
            $libelle = trim((string) $ac->getLibelle());
            $items[] = $code !== '' ? "{$code} : {$libelle}" : $libelle;
        }

        sort($items);

        return implode(' | ', array_filter($items));
    }

    private function calcTotalHeures(FicheMatiere $fiche): float
    {
        return (float) $fiche->getVolumeCmPresentiel()
            + (float) $fiche->getVolumeTdPresentiel()
            + (float) $fiche->getVolumeTpPresentiel()
            + (float) $fiche->getVolumeTe()
            + (float) $fiche->getVolumeCmDistanciel()
            + (float) $fiche->getVolumeTdDistanciel()
            + (float) $fiche->getVolumeTpDistanciel();
    }

    private function formatNumber(float $val): string
    {
        return rtrim(rtrim(number_format($val, 2, ',', ''), '0'), ',');
    }
}

