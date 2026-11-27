<?php

declare(strict_types=1);

namespace App\Service\Coherence;

use App\DTO\Coherence\CoherenceCheckResult;
use App\DTO\Coherence\CoherenceIssue;
use App\Entity\CoherenceAnalyse;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\CoherenceAnalyseRepository;
use App\Service\Ai\LlmClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class CoherenceAnalyzerService
{
    public function __construct(
        private readonly ParcoursCoherencePayloadBuilder $payloadBuilder,
        private readonly TextQualityDetector             $textQualityDetector,
        private readonly LlmClient                       $llmClient,
        private readonly CoherenceAnalyseRepository      $analyseRepository,
        private readonly EntityManagerInterface          $entityManager,
        private readonly ?LoggerInterface                $logger = null,
    )
    {
    }

    public function isAiConfigured(): bool
    {
        return $this->llmClient->isConfigured();
    }

    /**
     * Retourne l'indicateur instantané (0 appel LLM) pour affichage dans les listes, en-têtes et modales.
     * Utilise l'analyse persistée si disponible et signale si le contenu a évolué depuis.
     */
    public function getIndicatorForParcours(Parcours $parcours): CoherenceCheckResult
    {
        $payload = $this->payloadBuilder->buildParcoursPayload($parcours);
        $currentHash = $this->payloadBuilder->computeHash($payload);
        $persisted = $parcours->getCoherenceAnalyse() ?? $this->analyseRepository->findForParcours($parcours);

        if ($persisted !== null && $persisted->getContentHash() === $currentHash) {
            return $this->hydrateFromEntity($persisted, isStale: false);
        }

        $deterministic = $this->evaluateDeterministicParcoursFromPayload($parcours, $payload, $currentHash);

        if ($persisted !== null) {
            // Contenu modifié depuis la dernière analyse persistée : on marque isStale = true
            // mais si le pré-filtre déterministe détecte une alerte immédiate, on la remonte tout de suite.
            if ($deterministic->getAlertsCount() > 0) {
                return new CoherenceCheckResult(
                    status: CoherenceAnalyse::STATUS_ALERT,
                    score: $deterministic->score,
                    deterministicScore: $deterministic->deterministicScore,
                    summary: $deterministic->summary,
                    issues: $deterministic->issues,
                    contentHash: $currentHash,
                    isAiGenerated: false,
                    providerLabel: 'Pré-filtre temps réel (analyse IA à actualiser)',
                    isPersisted: true,
                    isStale: true,
                    analyzedAt: $persisted->getAnalyzedAt(),
                );
            }

            return $this->hydrateFromEntity($persisted, isStale: true);
        }

        return $deterministic;
    }

    public function getIndicatorForFicheMatiere(FicheMatiere $fiche): CoherenceCheckResult
    {
        $payload = $this->payloadBuilder->buildFicheMatierePayload($fiche);
        $currentHash = $this->payloadBuilder->computeHash($payload);
        $persisted = $fiche->getCoherenceAnalyse() ?? $this->analyseRepository->findForFicheMatiere($fiche);

        if ($persisted !== null && $persisted->getContentHash() === $currentHash) {
            return $this->hydrateFromEntity($persisted, isStale: false);
        }

        $deterministic = $this->evaluateDeterministicFicheFromPayload($fiche, $payload, $currentHash);

        if ($persisted !== null) {
            if ($deterministic->getAlertsCount() > 0) {
                return new CoherenceCheckResult(
                    status: CoherenceAnalyse::STATUS_ALERT,
                    score: $deterministic->score,
                    deterministicScore: $deterministic->deterministicScore,
                    summary: $deterministic->summary,
                    issues: $deterministic->issues,
                    contentHash: $currentHash,
                    isAiGenerated: false,
                    providerLabel: 'Pré-filtre temps réel (analyse IA à actualiser)',
                    isPersisted: true,
                    isStale: true,
                    analyzedAt: $persisted->getAnalyzedAt(),
                );
            }

            return $this->hydrateFromEntity($persisted, isStale: true);
        }

        return $deterministic;
    }

    public function analyzeParcours(
        Parcours $parcours,
        bool     $useAi = true,
        bool     $forceRefresh = false,
        bool     $persist = true,
    ): CoherenceCheckResult
    {
        $payload = $this->payloadBuilder->buildParcoursPayload($parcours);
        $currentHash = $this->payloadBuilder->computeHash($payload);
        $persisted = $parcours->getCoherenceAnalyse() ?? $this->analyseRepository->findForParcours($parcours);

        if (
            !$forceRefresh
            && $persisted !== null
            && $persisted->getContentHash() === $currentHash
            && (!$useAi || $persisted->isAiGenerated())
        ) {
            return $this->hydrateFromEntity($persisted, isStale: false);
        }

        $deterministicResult = $this->evaluateDeterministicParcoursFromPayload($parcours, $payload, $currentHash);

        $result = $deterministicResult;
        if ($useAi && $this->llmClient->isConfigured()) {
            try {
                $result = $this->runAiParcoursAnalysis($payload, $deterministicResult);
            } catch (\Throwable $e) {
                $this->logger?->warning('Échec de l\'analyse de cohérence IA pour le parcours #{id} : {message}', [
                    'id' => $parcours->getId(),
                    'message' => $e->getMessage(),
                ]);

                $result = new CoherenceCheckResult(
                    status: $deterministicResult->status,
                    score: $deterministicResult->score,
                    deterministicScore: $deterministicResult->deterministicScore,
                    summary: $deterministicResult->summary,
                    issues: $deterministicResult->issues,
                    contentHash: $currentHash,
                    isAiGenerated: false,
                    providerLabel: 'Pré-filtre déterministe (IA indisponible)',
                    isPersisted: $persist,
                    isStale: false,
                    analyzedAt: new \DateTimeImmutable(),
                    fallbackReason: $e->getMessage(),
                );
            }
        } elseif ($useAi && !$this->llmClient->isConfigured()) {
            $result = new CoherenceCheckResult(
                status: $deterministicResult->status,
                score: $deterministicResult->score,
                deterministicScore: $deterministicResult->deterministicScore,
                summary: $deterministicResult->summary,
                issues: $deterministicResult->issues,
                contentHash: $currentHash,
                isAiGenerated: false,
                providerLabel: 'Pré-filtre déterministe (clé IA non configurée)',
                isPersisted: $persist,
                isStale: false,
                analyzedAt: new \DateTimeImmutable(),
                fallbackReason: 'Aucune configuration IA active (établissement ou .env AI_API_KEY).',
            );
        }

        if ($persist && $parcours->getId() !== null) {
            $this->persistParcoursResult($parcours, $persisted, $result);
            $result = new CoherenceCheckResult(
                status: $result->status,
                score: $result->score,
                deterministicScore: $result->deterministicScore,
                summary: $result->summary,
                issues: $result->issues,
                contentHash: $result->contentHash,
                isAiGenerated: $result->isAiGenerated,
                providerLabel: $result->providerLabel,
                isPersisted: true,
                isStale: false,
                analyzedAt: new \DateTimeImmutable(),
                fallbackReason: $result->fallbackReason,
            );
        }

        return $result;
    }

    public function analyzeFicheMatiere(
        FicheMatiere $fiche,
        bool         $useAi = true,
        bool         $forceRefresh = false,
        bool         $persist = true,
    ): CoherenceCheckResult
    {
        $payload = $this->payloadBuilder->buildFicheMatierePayload($fiche);
        $currentHash = $this->payloadBuilder->computeHash($payload);
        $persisted = $fiche->getCoherenceAnalyse() ?? $this->analyseRepository->findForFicheMatiere($fiche);

        if (
            !$forceRefresh
            && $persisted !== null
            && $persisted->getContentHash() === $currentHash
            && (!$useAi || $persisted->isAiGenerated())
        ) {
            return $this->hydrateFromEntity($persisted, isStale: false);
        }

        $deterministicResult = $this->evaluateDeterministicFicheFromPayload($fiche, $payload, $currentHash);

        $result = $deterministicResult;
        if ($useAi && $this->llmClient->isConfigured()) {
            try {
                $result = $this->runAiFicheAnalysis($payload, $deterministicResult);
            } catch (\Throwable $e) {
                $this->logger?->warning('Échec de l\'analyse de cohérence IA pour la fiche matière #{id} : {message}', [
                    'id' => $fiche->getId(),
                    'message' => $e->getMessage(),
                ]);

                $result = new CoherenceCheckResult(
                    status: $deterministicResult->status,
                    score: $deterministicResult->score,
                    deterministicScore: $deterministicResult->deterministicScore,
                    summary: $deterministicResult->summary,
                    issues: $deterministicResult->issues,
                    contentHash: $currentHash,
                    isAiGenerated: false,
                    providerLabel: 'Pré-filtre déterministe (IA indisponible)',
                    isPersisted: $persist,
                    isStale: false,
                    analyzedAt: new \DateTimeImmutable(),
                    fallbackReason: $e->getMessage(),
                );
            }
        } elseif ($useAi && !$this->llmClient->isConfigured()) {
            $result = new CoherenceCheckResult(
                status: $deterministicResult->status,
                score: $deterministicResult->score,
                deterministicScore: $deterministicResult->deterministicScore,
                summary: $deterministicResult->summary,
                issues: $deterministicResult->issues,
                contentHash: $currentHash,
                isAiGenerated: false,
                providerLabel: 'Pré-filtre déterministe (clé IA non configurée)',
                isPersisted: $persist,
                isStale: false,
                analyzedAt: new \DateTimeImmutable(),
                fallbackReason: 'Aucune configuration IA active (établissement ou .env AI_API_KEY).',
            );
        }

        if ($persist && $fiche->getId() !== null) {
            $this->persistFicheResult($fiche, $persisted, $result);
            $result = new CoherenceCheckResult(
                status: $result->status,
                score: $result->score,
                deterministicScore: $result->deterministicScore,
                summary: $result->summary,
                issues: $result->issues,
                contentHash: $result->contentHash,
                isAiGenerated: $result->isAiGenerated,
                providerLabel: $result->providerLabel,
                isPersisted: true,
                isStale: false,
                analyzedAt: new \DateTimeImmutable(),
                fallbackReason: $result->fallbackReason,
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function evaluateDeterministicParcoursFromPayload(
        Parcours $parcours,
        array    $payload,
        string   $contentHash,
    ): CoherenceCheckResult
    {
        $issues = [];
        $referenceLabels = array_values(array_filter([
            (string)($payload['identity']['parcoursLibelle'] ?? ''),
            (string)($payload['identity']['formationDisplay'] ?? ''),
        ], static fn(string $v): bool => $v !== ''));

        $substantialFields = [
            'objectifsParcours' => ['minChars' => 30, 'minWords' => 6],
            'resultatsAttendus' => ['minChars' => 25, 'minWords' => 5],
            'contenuFormation' => ['minChars' => 30, 'minWords' => 6],
            'prerequis' => ['minChars' => 20, 'minWords' => 4],
            'poursuitesEtudes' => ['minChars' => 20, 'minWords' => 4],
            'debouches' => ['minChars' => 20, 'minWords' => 4],
            'rythmeFormationTexte' => ['minChars' => 15, 'minWords' => 3],
            'modalitesAlternance' => ['minChars' => 20, 'minWords' => 4],
            'stageText' => ['minChars' => 20, 'minWords' => 4],
            'projetText' => ['minChars' => 20, 'minWords' => 4],
            'memoireText' => ['minChars' => 20, 'minWords' => 4],
            'situationProText' => ['minChars' => 20, 'minWords' => 4],
            'descriptifHautPage' => ['minChars' => 25, 'minWords' => 5],
            'descriptifBasPage' => ['minChars' => 25, 'minWords' => 5],
        ];

        /** @var array<string, array{label: string, text: string}> $nonEmptyForDuplicates */
        $nonEmptyForDuplicates = [];

        foreach ($payload['texts'] as $fieldKey => $info) {
            $value = (string)($info['value'] ?? '');
            $label = (string)($info['label'] ?? $fieldKey);

            if ($value === '') {
                continue;
            }

            $isSubstantial = isset($substantialFields[$fieldKey]);
            $minChars = $substantialFields[$fieldKey]['minChars'] ?? 10;
            $minWords = $substantialFields[$fieldKey]['minWords'] ?? 2;

            $fieldIssues = $this->textQualityDetector->analyzeField(
                field: $fieldKey,
                fieldLabel: $label,
                rawValue: $value,
                expectSubstantialText: $isSubstantial,
                minChars: $minChars,
                minWords: $minWords,
                referenceLabels: $referenceLabels,
            );

            foreach ($fieldIssues as $issue) {
                $issues[] = $issue;
            }

            if ($isSubstantial) {
                $nonEmptyForDuplicates[$fieldKey] = ['label' => $label, 'text' => $value];
            }
        }

        foreach ($this->textQualityDetector->detectDuplicateFields($nonEmptyForDuplicates) as $dupIssue) {
            $issues[] = $dupIssue;
        }

        // Cohérences structurelles : stage / projet / mémoire / situation pro / alternance
        $modalites = $payload['modalites'];
        if (($modalites['hasStage'] ?? false) === true) {
            if (($payload['texts']['stageText']['value'] ?? '') === '') {
                $issues[] = new CoherenceIssue(
                    field: 'stageText',
                    fieldLabel: 'Modalités de stage',
                    severity: CoherenceIssue::SEVERITY_WARNING,
                    category: CoherenceIssue::CATEGORY_INCOHERENCE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: 'Le parcours déclare un stage obligatoire/prévu mais aucun descriptif de stage n\'est saisi.',
                );
            }
            if ((float)($modalites['nbHeuresStages'] ?? 0) <= 0.0) {
                $issues[] = new CoherenceIssue(
                    field: 'nbHeuresStages',
                    fieldLabel: 'Volume horaire de stage',
                    severity: CoherenceIssue::SEVERITY_WARNING,
                    category: CoherenceIssue::CATEGORY_INCOHERENCE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: 'Un stage est activé mais le nombre d\'heures de stage est nul ou non renseigné.',
                );
            }
        } elseif (($modalites['hasStage'] ?? null) === false && mb_strlen((string)($payload['texts']['stageText']['value'] ?? '')) > 40) {
            $issues[] = new CoherenceIssue(
                field: 'hasStage',
                fieldLabel: 'Stage',
                severity: CoherenceIssue::SEVERITY_WARNING,
                category: CoherenceIssue::CATEGORY_INCOHERENCE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: 'Un texte détaillé de stage est renseigné alors que l\'option « Stage » est désactivée.',
            );
        }

        if (($modalites['hasProjet'] ?? false) === true) {
            if (($payload['texts']['projetText']['value'] ?? '') === '' || (float)($modalites['nbHeuresProjet'] ?? 0) <= 0.0) {
                $issues[] = new CoherenceIssue(
                    field: 'projetText',
                    fieldLabel: 'Projet tutoré',
                    severity: CoherenceIssue::SEVERITY_WARNING,
                    category: CoherenceIssue::CATEGORY_INCOHERENCE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: 'Un projet tutoré est activé mais son descriptif ou son volume horaire reste incomplet.',
                );
            }
        }

        if (($modalites['hasSituationPro'] ?? false) === true) {
            if (($payload['texts']['situationProText']['value'] ?? '') === '' || (float)($modalites['nbHeuresSituationPro'] ?? 0) <= 0.0) {
                $issues[] = new CoherenceIssue(
                    field: 'situationProText',
                    fieldLabel: 'Mises en situation professionnelle',
                    severity: CoherenceIssue::SEVERITY_WARNING,
                    category: CoherenceIssue::CATEGORY_INCOHERENCE,
                    source: CoherenceIssue::SOURCE_DETERMINISTIC,
                    message: 'Les mises en situation professionnelle sont activées mais le descriptif ou le volume horaire est manquant.',
                );
            }
        }

        if (($modalites['hasMemoire'] ?? false) === true && ($payload['texts']['memoireText']['value'] ?? '') === '') {
            $issues[] = new CoherenceIssue(
                field: 'memoireText',
                fieldLabel: 'Mémoire',
                severity: CoherenceIssue::SEVERITY_WARNING,
                category: CoherenceIssue::CATEGORY_INCOHERENCE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: 'Un mémoire est activé mais ses modalités ne sont pas décrites.',
            );
        }

        // Alternance cochée dans les régimes sans texte explicatif
        $regimes = $payload['identity']['regimesInscription'] ?? [];
        $hasAlternanceRegime = false;
        foreach ($regimes as $regime) {
            if (!in_array($regime, [RegimeInscriptionEnum::FI->value, RegimeInscriptionEnum::FC->value], true)) {
                $hasAlternanceRegime = true;
                break;
            }
        }
        if ($hasAlternanceRegime && ($payload['texts']['modalitesAlternance']['value'] ?? '') === '') {
            $issues[] = new CoherenceIssue(
                field: 'modalitesAlternance',
                fieldLabel: 'Modalités d\'alternance',
                severity: CoherenceIssue::SEVERITY_WARNING,
                category: CoherenceIssue::CATEGORY_INCOHERENCE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: 'Un régime d\'alternance (apprentissage / contrat pro) est actif mais les modalités d\'alternance sont vides.',
            );
        }

        // Contrôle des libellés dans la maquette UE / EC
        foreach ($payload['maquette'] as $semestreInfo) {
            foreach ($semestreInfo['ues'] as $ueInfo) {
                $ueIssues = $this->textQualityDetector->analyzeField(
                    field: 'maquette',
                    fieldLabel: sprintf('Maquette — %s / %s', $semestreInfo['semestre'], $ueInfo['libelle'] ?: 'UE'),
                    rawValue: $ueInfo['libelle'],
                    expectSubstantialText: false,
                );
                foreach ($ueIssues as $ueIssue) {
                    $issues[] = $ueIssue;
                }
                foreach ($ueInfo['ecs'] as $ecLabel) {
                    $ecIssues = $this->textQualityDetector->analyzeField(
                        field: 'maquette',
                        fieldLabel: sprintf('Maquette — %s / EC', $semestreInfo['semestre']),
                        rawValue: $ecLabel,
                        expectSubstantialText: false,
                    );
                    foreach ($ecIssues as $ecIssue) {
                        $issues[] = $ecIssue;
                    }
                }
            }
        }

        // Si formation classique sans aucune UE alors que des textes de contenu existent
        if (($payload['identity']['isClassique'] ?? true) === true && $payload['maquette'] === []) {
            $issues[] = new CoherenceIssue(
                field: 'maquette',
                fieldLabel: 'Structure de maquette',
                severity: CoherenceIssue::SEVERITY_INFO,
                category: CoherenceIssue::CATEGORY_COMPLETUDE_SEMANTIQUE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: 'Aucune UE/maquette structurée n\'est rattachée à ce parcours pour contrôler la cohérence avec les objectifs.',
            );
        }

        $score = $this->computeScoreFromIssues($issues);
        $status = $this->resolveStatus($score, $issues);
        $summary = $this->buildDeterministicSummary($issues, 'parcours');

        return new CoherenceCheckResult(
            status: $status,
            score: $score,
            deterministicScore: $score,
            summary: $summary,
            issues: $issues,
            contentHash: $contentHash,
            isAiGenerated: false,
            providerLabel: 'Pré-filtre déterministe',
            isPersisted: false,
            isStale: false,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function evaluateDeterministicFicheFromPayload(
        FicheMatiere $fiche,
        array        $payload,
        string       $contentHash,
    ): CoherenceCheckResult
    {
        $issues = [];
        $referenceLabels = array_values(array_filter([
            (string)($payload['identity']['libelle'] ?? ''),
            (string)($payload['identity']['parcoursLibelle'] ?? ''),
        ], static fn(string $v): bool => $v !== ''));

        $nonEmptyForDuplicates = [];
        foreach ($payload['texts'] as $fieldKey => $info) {
            $value = (string)($info['value'] ?? '');
            $label = (string)($info['label'] ?? $fieldKey);
            if ($value === '') {
                continue;
            }

            $isSubstantial = in_array($fieldKey, ['description', 'objectifs'], true);
            $fieldIssues = $this->textQualityDetector->analyzeField(
                field: $fieldKey,
                fieldLabel: $label,
                rawValue: $value,
                expectSubstantialText: $isSubstantial,
                minChars: 25,
                minWords: 5,
                referenceLabels: $referenceLabels,
            );
            foreach ($fieldIssues as $issue) {
                $issues[] = $issue;
            }
            if ($isSubstantial) {
                $nonEmptyForDuplicates[$fieldKey] = ['label' => $label, 'text' => $value];
            }
        }

        foreach ($this->textQualityDetector->detectDuplicateFields($nonEmptyForDuplicates) as $dupIssue) {
            $issues[] = $dupIssue;
        }

        $volumes = $payload['volumes'];
        $totalHeures = $volumes['cmPresentiel'] + $volumes['tdPresentiel'] + $volumes['tpPresentiel']
            + $volumes['cmDistanciel'] + $volumes['tdDistanciel'] + $volumes['tpDistanciel'];

        if ($totalHeures > 0.0 && ($payload['nbMccc'] ?? 0) === 0 && ($payload['identity']['typeMccc'] ?? null) === null) {
            $issues[] = new CoherenceIssue(
                field: 'mccc',
                fieldLabel: 'Volumes horaires / MCCC',
                severity: CoherenceIssue::SEVERITY_WARNING,
                category: CoherenceIssue::CATEGORY_INCOHERENCE,
                source: CoherenceIssue::SOURCE_DETERMINISTIC,
                message: sprintf('La fiche déclare %.1f h d\'enseignement mais aucune MCCC n\'est configurée.', $totalHeures),
            );
        }

        $score = $this->computeScoreFromIssues($issues);
        $status = $this->resolveStatus($score, $issues);
        $summary = $this->buildDeterministicSummary($issues, 'fiche matière');

        return new CoherenceCheckResult(
            status: $status,
            score: $score,
            deterministicScore: $score,
            summary: $summary,
            issues: $issues,
            contentHash: $contentHash,
            isAiGenerated: false,
            providerLabel: 'Pré-filtre déterministe',
            isPersisted: false,
            isStale: false,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function runAiParcoursAnalysis(array $payload, CoherenceCheckResult $deterministic): CoherenceCheckResult
    {
        $prompt = $this->buildParcoursAiPrompt($payload, $deterministic);

        $response = $this->llmClient->chatCompletion([
            [
                'role' => 'system',
                'content' => 'Tu es un auditeur qualité académique pour l\'offre de formation universitaire française (ORéOF / HCÉRES / CFVU). '
                    . 'Analyse la cohérence interne des textes saisis d\'un parcours et leur adéquation avec son intitulé, son niveau de diplôme, ses modalités (stage, projet, alternance) et sa maquette (UE/EC/compétences). '
                    . 'Repère impérativement : '
                    . '1) Les contenus fictifs, de test, placeholders ou remplissages vides de sens ("lorem", "à compléter", phrases génériques creuses). '
                    . '2) Les contradictions ou incohérences (ex. objectifs sans rapport avec la maquette UE/EC ou la mention, niveau inadapté entre prérequis et diplôme, stage mentionné dans un texte mais absent des modalités ou inversement, intitulé déconnecté des débouchés). '
                    . '3) Les champs copiés-collés ou redondants sans valeur ajoutée. '
                    . 'Réponds UNIQUEMENT par un objet JSON valide sans texte avant ni après, respectant exactement ce schéma : '
                    . '{"score": <int 0..100>, "summary": "<synthèse factuelle en français de 2 à 3 phrases>", "issues": [{"field": "<clé du champ ou maquette/global>", "fieldLabel": "<libellé lisible>", "severity": "alert|warning|info", "category": "fictif|remplissage|incoherence|completude_semantique", "message": "<explication précise et actionnable>", "excerpt": "<court extrait ou null>"}]}',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ], temperature: 0.15, maxTokens: 1300, timeout: 30.0);

        return $this->mergeAiResponse($response['content'], $response['model'], $deterministic);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function runAiFicheAnalysis(array $payload, CoherenceCheckResult $deterministic): CoherenceCheckResult
    {
        $prompt = $this->buildFicheAiPrompt($payload, $deterministic);

        $response = $this->llmClient->chatCompletion([
            [
                'role' => 'system',
                'content' => 'Tu es un auditeur qualité académique pour l\'offre de formation universitaire française (ORéOF). '
                    . 'Analyse la cohérence d\'une fiche matière (EC) : intitulé FR/EN, description/programme, objectifs pédagogiques, compétences, volumes horaires et MCCC. '
                    . 'Repère les contenus fictifs, de remplissage, traductions anglaises absentes/incohérentes, objectifs vagues ou déconnectés de l\'intitulé/programme. '
                    . 'Réponds UNIQUEMENT par un objet JSON valide sans texte avant ni après, respectant ce schéma : '
                    . '{"score": <int 0..100>, "summary": "<synthèse factuelle en français en 2 phrases>", "issues": [{"field": "<libelle|libelleAnglais|description|objectifs|mccc|global>", "fieldLabel": "<libellé>", "severity": "alert|warning|info", "category": "fictif|remplissage|incoherence|completude_semantique", "message": "<explication précise>", "excerpt": "<extrait ou null>"}]}',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ], temperature: 0.15, maxTokens: 900, timeout: 25.0);

        return $this->mergeAiResponse($response['content'], $response['model'], $deterministic);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildParcoursAiPrompt(array $payload, CoherenceCheckResult $deterministic): string
    {
        $id = $payload['identity'];
        $lines = [
            '=== IDENTITÉ DU PARCOURS ===',
            sprintf('Formation : %s', $id['formationDisplay'] ?: 'Non renseignée'),
            sprintf('Type de diplôme : %s (%s)', $id['typeDiplome'] ?: '?', $id['typeDiplomeCourt'] ?: '?'),
            sprintf('Domaines : %s', $id['domaines'] !== [] ? implode(', ', $id['domaines']) : 'Non renseignés'),
            sprintf('Parcours : %s (%s)', $id['parcoursLibelle'] ?: 'Non renseigné', $id['typeParcours'] ?: 'Classique'),
            sprintf('Modalité : %s | Régimes : %s', $id['modaliteEnseignement'] ?? 'Non définie', $id['regimesInscription'] !== [] ? implode(', ', $id['regimesInscription']) : 'Non définis'),
            '',
            '=== TEXTES SAISIS ===',
        ];

        foreach ($payload['texts'] as $key => $info) {
            $value = (string)($info['value'] ?? '');
            if ($value === '') {
                $lines[] = sprintf('[%s | %s] : (vide)', $key, $info['label']);
            } else {
                $clipped = mb_strlen($value) > 900 ? mb_substr($value, 0, 900) . '…' : $value;
                $lines[] = sprintf('[%s | %s] : %s', $key, $info['label'], $clipped);
            }
        }

        $mod = $payload['modalites'];
        $lines[] = '';
        $lines[] = '=== MODALITÉS DÉCLARÉES ===';
        $lines[] = sprintf(
            'Stage: %s (%.1fh) | Projet: %s (%.1fh) | Situation pro: %s (%.1fh) | Mémoire: %s',
            $mod['hasStage'] === true ? 'oui' : ($mod['hasStage'] === false ? 'non' : 'non précisé'),
            (float)($mod['nbHeuresStages'] ?? 0),
            $mod['hasProjet'] === true ? 'oui' : ($mod['hasProjet'] === false ? 'non' : 'non précisé'),
            (float)($mod['nbHeuresProjet'] ?? 0),
            $mod['hasSituationPro'] === true ? 'oui' : ($mod['hasSituationPro'] === false ? 'non' : 'non précisé'),
            (float)($mod['nbHeuresSituationPro'] ?? 0),
            $mod['hasMemoire'] === true ? 'oui' : ($mod['hasMemoire'] === false ? 'non' : 'non précisé'),
        );

        if ($payload['competences'] !== []) {
            $lines[] = '';
            $lines[] = '=== BLOCS DE COMPÉTENCES ===';
            foreach (array_slice($payload['competences'], 0, 12) as $bloc) {
                $items = array_slice($bloc['items'], 0, 8);
                $lines[] = sprintf('- %s : %s', $bloc['bloc'], implode(' ; ', $items));
            }
        }

        if ($payload['maquette'] !== []) {
            $lines[] = '';
            $lines[] = '=== RÉSUMÉ MAQUETTE (SEMESTRES / UE / EC) ===';
            foreach (array_slice($payload['maquette'], 0, 10) as $semestre) {
                $ueParts = [];
                foreach (array_slice($semestre['ues'], 0, 10) as $ue) {
                    $ecs = array_slice($ue['ecs'], 0, 8);
                    $ueParts[] = sprintf('%s (%s)', $ue['libelle'] ?: 'UE', $ecs !== [] ? implode(', ', $ecs) : 'aucun EC');
                }
                $lines[] = sprintf('- %s : %s', $semestre['semestre'], implode(' | ', $ueParts));
            }
        }

        if ($deterministic->hasIssues()) {
            $lines[] = '';
            $lines[] = '=== ALERTES DÉTERMINISTES DÉJÀ DÉTECTÉES ===';
            foreach ($deterministic->issues as $issue) {
                $lines[] = sprintf('- [%s] %s : %s', $issue->severity, $issue->fieldLabel, $issue->message);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildFicheAiPrompt(array $payload, CoherenceCheckResult $deterministic): string
    {
        $id = $payload['identity'];
        $vol = $payload['volumes'];
        $lines = [
            '=== IDENTITÉ DE LA FICHE MATIÈRE ===',
            sprintf('Intitulé : %s', $id['libelle'] ?: 'Non renseigné'),
            sprintf('Intitulé anglais : %s', $id['libelleAnglais'] ?: 'Non renseigné'),
            sprintf('Parcours / Formation : %s (%s)', $id['parcoursLibelle'] ?: 'Hors diplôme', $id['formationDisplay'] ?: '-'),
            sprintf('Volumes : CM %.1fh / TD %.1fh / TP %.1fh / TE %.1fh | ECTS : %.1f | Type MCCC : %s (%d épreuves)',
                $vol['cmPresentiel'] + $vol['cmDistanciel'],
                $vol['tdPresentiel'] + $vol['tdDistanciel'],
                $vol['tpPresentiel'] + $vol['tpDistanciel'],
                $vol['te'],
                $vol['ects'],
                $id['typeMccc'] ?? 'non défini',
                $payload['nbMccc']
            ),
            '',
            '=== TEXTES SAISIS ===',
        ];

        foreach ($payload['texts'] as $key => $info) {
            $value = (string)($info['value'] ?? '');
            $clipped = mb_strlen($value) > 1000 ? mb_substr($value, 0, 1000) . '…' : $value;
            $lines[] = sprintf('[%s | %s] : %s', $key, $info['label'], $clipped !== '' ? $clipped : '(vide)');
        }

        if ($payload['competences'] !== []) {
            $lines[] = '';
            $lines[] = 'Compétences associées : ' . implode(' ; ', array_slice($payload['competences'], 0, 10));
        }

        if ($deterministic->hasIssues()) {
            $lines[] = '';
            $lines[] = 'Alertes déterministes déjà détectées :';
            foreach ($deterministic->issues as $issue) {
                $lines[] = sprintf('- [%s] %s : %s', $issue->severity, $issue->fieldLabel, $issue->message);
            }
        }

        return implode("\n", $lines);
    }

    private function mergeAiResponse(
        string               $rawContent,
        string               $model,
        CoherenceCheckResult $deterministic,
    ): CoherenceCheckResult
    {
        $decoded = $this->decodeJsonFromLlm($rawContent);
        if ($decoded === null) {
            throw new \RuntimeException('Réponse IA non exploitable (JSON invalide).');
        }

        $aiIssues = [];
        if (isset($decoded['issues']) && is_array($decoded['issues'])) {
            foreach ($decoded['issues'] as $rawIssue) {
                if (!is_array($rawIssue)) {
                    continue;
                }
                $message = trim((string)($rawIssue['message'] ?? ''));
                if ($message === '') {
                    continue;
                }
                $aiIssues[] = CoherenceIssue::fromArray([
                    'field' => $rawIssue['field'] ?? 'global',
                    'fieldLabel' => $rawIssue['fieldLabel'] ?? 'Analyse sémantique',
                    'severity' => $rawIssue['severity'] ?? CoherenceIssue::SEVERITY_WARNING,
                    'category' => $rawIssue['category'] ?? CoherenceIssue::CATEGORY_INCOHERENCE,
                    'source' => CoherenceIssue::SOURCE_AI,
                    'message' => $message,
                    'excerpt' => $rawIssue['excerpt'] ?? null,
                ]);
            }
        }

        // Fusionner sans doublonner bêtement sur le même champ+message
        $mergedIssues = $deterministic->issues;
        foreach ($aiIssues as $aiIssue) {
            $duplicate = false;
            foreach ($mergedIssues as $existing) {
                if (
                    $existing->field === $aiIssue->field
                    && mb_strtolower($existing->message) === mb_strtolower($aiIssue->message)
                ) {
                    $duplicate = true;
                    break;
                }
            }
            if (!$duplicate) {
                $mergedIssues[] = $aiIssue;
            }
        }

        $aiScoreRaw = isset($decoded['score']) && is_numeric($decoded['score']) ? (int)$decoded['score'] : $deterministic->score;
        $aiScore = max(0, min(100, $aiScoreRaw));
        // On borne pour que l'IA ne masque pas une alerte déterministe grave
        $finalScore = min($aiScore, $deterministic->score);
        $finalScore = min($finalScore, $this->computeScoreFromIssues($mergedIssues));

        $summary = trim((string)($decoded['summary'] ?? ''));
        if ($summary === '') {
            $summary = $this->buildDeterministicSummary($mergedIssues, 'dossier');
        }

        $status = $this->resolveStatus($finalScore, $mergedIssues);

        return new CoherenceCheckResult(
            status: $status,
            score: $finalScore,
            deterministicScore: $deterministic->deterministicScore,
            summary: $summary,
            issues: $mergedIssues,
            contentHash: $deterministic->contentHash,
            isAiGenerated: true,
            providerLabel: sprintf('IA (%s)', $model),
            isPersisted: false,
            isStale: false,
            analyzedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonFromLlm(string $rawContent): ?array
    {
        $trimmed = trim($rawContent);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/su', $trimmed, $m) === 1) {
            $trimmed = $m[1];
        } else {
            $start = strpos($trimmed, '{');
            $end = strrpos($trimmed, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $trimmed = substr($trimmed, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<CoherenceIssue> $issues
     */
    private function computeScoreFromIssues(array $issues): int
    {
        $score = 100;
        foreach ($issues as $issue) {
            $score -= match ($issue->severity) {
                CoherenceIssue::SEVERITY_ALERT => 28,
                CoherenceIssue::SEVERITY_WARNING => 12,
                default => 4,
            };
        }

        return max(0, $score);
    }

    /**
     * @param list<CoherenceIssue> $issues
     */
    private function resolveStatus(int $score, array $issues): string
    {
        foreach ($issues as $issue) {
            if ($issue->severity === CoherenceIssue::SEVERITY_ALERT) {
                return CoherenceAnalyse::STATUS_ALERT;
            }
        }

        if ($score < 50) {
            return CoherenceAnalyse::STATUS_ALERT;
        }

        foreach ($issues as $issue) {
            if ($issue->severity === CoherenceIssue::SEVERITY_WARNING) {
                return CoherenceAnalyse::STATUS_WARNING;
            }
        }

        if ($score < 80) {
            return CoherenceAnalyse::STATUS_WARNING;
        }

        return CoherenceAnalyse::STATUS_OK;
    }

    /**
     * @param list<CoherenceIssue> $issues
     */
    private function buildDeterministicSummary(array $issues, string $entityLabel): string
    {
        if ($issues === []) {
            return sprintf('Aucun contenu fictif, remplissage ou incohérence structurelle détecté sur ce %s par le pré-filtre.', $entityLabel);
        }

        $alerts = 0;
        $warnings = 0;
        foreach ($issues as $issue) {
            if ($issue->severity === CoherenceIssue::SEVERITY_ALERT) {
                $alerts++;
            } elseif ($issue->severity === CoherenceIssue::SEVERITY_WARNING) {
                $warnings++;
            }
        }

        return sprintf(
            '%d anomalie(s) détectée(s) sur ce %s (%d alerte(s) critique(s), %d point(s) d\'attention).',
            count($issues),
            $entityLabel,
            $alerts,
            $warnings
        );
    }

    private function persistParcoursResult(
        Parcours             $parcours,
        ?CoherenceAnalyse    $existing,
        CoherenceCheckResult $result,
    ): void
    {
        $entity = $existing ?? new CoherenceAnalyse();
        $entity->setParcours($parcours);
        $this->fillEntityFromResult($entity, $result);

        $parcours->setCoherenceAnalyse($entity);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function persistFicheResult(
        FicheMatiere         $fiche,
        ?CoherenceAnalyse    $existing,
        CoherenceCheckResult $result,
    ): void
    {
        $entity = $existing ?? new CoherenceAnalyse();
        $entity->setFicheMatiere($fiche);
        $this->fillEntityFromResult($entity, $result);

        $fiche->setCoherenceAnalyse($entity);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function fillEntityFromResult(CoherenceAnalyse $entity, CoherenceCheckResult $result): void
    {
        $entity
            ->setContentHash($result->contentHash)
            ->setStatus($result->status)
            ->setScore($result->score)
            ->setDeterministicScore($result->deterministicScore)
            ->setSummary($result->summary)
            ->setIssues(array_map(
                static fn(CoherenceIssue $issue): array => $issue->toArray(),
                $result->issues
            ))
            ->setIsAiGenerated($result->isAiGenerated)
            ->setProviderLabel($result->providerLabel)
            ->setAnalyzedAt(new \DateTimeImmutable());
    }

    private function hydrateFromEntity(CoherenceAnalyse $entity, bool $isStale): CoherenceCheckResult
    {
        $issues = array_map(
            static fn(array $item): CoherenceIssue => CoherenceIssue::fromArray($item),
            $entity->getIssues()
        );

        return new CoherenceCheckResult(
            status: $entity->getStatus(),
            score: $entity->getScore() ?? 100,
            deterministicScore: $entity->getDeterministicScore() ?? ($entity->getScore() ?? 100),
            summary: (string)($entity->getSummary() ?? ''),
            issues: $issues,
            contentHash: $entity->getContentHash(),
            isAiGenerated: $entity->isAiGenerated(),
            providerLabel: (string)($entity->getProviderLabel() ?? 'Analyse enregistrée'),
            isPersisted: true,
            isStale: $isStale,
            analyzedAt: $entity->getAnalyzedAt(),
        );
    }
}
