<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\DTO\Comparison\AiDiffSummaryResult;
use App\DTO\Comparison\FicheMatiereChangeItem;
use App\DTO\Comparison\FicheMatiereChangeReport;
use App\Repository\EtablissementRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FicheMatiereAiSynthesizer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly ?EtablissementRepository $etablissementRepository = null,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire('%env(default::AI_API_URL)%')]
        private readonly ?string $envAiUrl = null,
        #[Autowire('%env(default::AI_API_KEY)%')]
        private readonly ?string $envAiKey = null,
        #[Autowire('%env(default::AI_MODEL)%')]
        private readonly ?string $envAiModel = null,
    ) {
    }

    public function summarize(FicheMatiereChangeReport $report, bool $forceRefresh = false): AiDiffSummaryResult
    {
        if (!$report->hasReference()) {
            return new AiDiffSummaryResult(
                summary: 'Aucune version de référence (snapshot JSON ou fiche N-1) n\'est disponible pour cette fiche matière.',
                highlights: [],
                isAiGenerated: false,
                providerLabel: 'Synthèse locale',
                hasRegulatoryImpact: false,
            );
        }

        if (!$report->hasChanges()) {
            return new AiDiffSummaryResult(
                summary: sprintf('Aucune différence détectée par rapport à la référence (%s).', $report->sourceLabel),
                highlights: [],
                isAiGenerated: false,
                providerLabel: 'Synthèse locale',
                hasRegulatoryImpact: false,
            );
        }

        $hasRegulatoryImpact = $this->detectRegulatoryImpact($report);
        $cacheKey = 'ai_fiche_diff_' . $report->getFingerprint();

        if ($forceRefresh) {
            $this->cache->delete($cacheKey);
        }

        if (!$this->isAiConfigured()) {
            return $this->buildDeterministicResult(
                $report,
                $hasRegulatoryImpact,
                'Aucune configuration IA active (clé d\'API absente dans l\'établissement ou dans le fichier d\'environnement .env) — synthèse déterministe locale activée.'
            );
        }

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($report, $hasRegulatoryImpact): AiDiffSummaryResult {
                $item->expiresAfter(3600 * 24);

                return $this->callLlmApi($report, $hasRegulatoryImpact);
            });
        } catch (\Throwable $e) {
            $this->logger?->warning('Échec de l\'appel IA pour la synthèse de fiche matière : {message}', [
                'message' => $e->getMessage(),
                'ficheId' => $report->ficheId,
            ]);

            return $this->buildDeterministicResult(
                $report,
                $hasRegulatoryImpact,
                sprintf('Service IA indisponible (%s) — repli sur la synthèse locale.', $e->getMessage())
            );
        }
    }

    /**
     * Résout la configuration IA en cherchant :
     * 1. Dans la configuration de l'établissement (Administration > Établissement > APIs tierces : Oratio, ROMEO, LLM, IA...)
     * 2. Dans les variables d'environnement (AI_API_URL, AI_API_KEY, AI_MODEL)
     *
     * @return array{url: ?string, apiKey: ?string, model: ?string, source: string}
     */
    public function resolveConfig(): array
    {
        $etablissement = $this->etablissementRepository?->findOneBy([]);
        $etabConfig = $etablissement?->getAiApiConfiguration();

        if ($etabConfig !== null && !empty($etabConfig['url'])) {
            $url = trim((string) $etabConfig['url']);
            if (!str_ends_with($url, '/chat/completions')) {
                $url = rtrim($url, '/') . '/chat/completions';
            }

            $apiKey = !empty($etabConfig['apiKey']) ? trim((string) $etabConfig['apiKey']) : null;
            // Le champ 'login' de l'API tierce peut spécifier le modèle (ex: mistral-small-3.2-24b)
            $model = !empty($etabConfig['login']) ? trim((string) $etabConfig['login']) : null;
            if ($model === null && !empty($this->envAiModel)) {
                $model = trim((string) $this->envAiModel);
            }

            return [
                'url' => $url,
                'apiKey' => $apiKey,
                'model' => $model ?: 'default',
                'source' => sprintf('Établissement (%s)', $etabConfig['plateforme']),
            ];
        }

        $envUrl = trim((string) $this->envAiUrl);
        $envKey = trim((string) $this->envAiKey);
        $envModel = trim((string) $this->envAiModel);

        if ($envUrl !== '') {
            if (!str_ends_with($envUrl, '/chat/completions')) {
                $envUrl = rtrim($envUrl, '/') . '/chat/completions';
            }

            return [
                'url' => $envUrl,
                'apiKey' => $envKey !== '' && $envKey !== 'change-me' ? $envKey : null,
                'model' => $envModel !== '' ? $envModel : 'default',
                'source' => 'Environnement (.env)',
            ];
        }

        return [
            'url' => null,
            'apiKey' => null,
            'model' => null,
            'source' => 'none',
        ];
    }

    public function isAiConfigured(): bool
    {
        $config = $this->resolveConfig();
        if ($config['url'] === null) {
            return false;
        }

        if ($config['apiKey'] !== null) {
            return true;
        }

        // Autoriser un endpoint local sans clé (ex: Ollama / vLLM sur localhost)
        $url = $config['url'];
        return str_contains($url, 'localhost') || str_contains($url, '127.0.0.1') || str_contains($url, 'host.docker.internal');
    }

    private function callLlmApi(FicheMatiereChangeReport $report, bool $hasRegulatoryImpact): AiDiffSummaryResult
    {
        $config = $this->resolveConfig();
        $apiUrl = $config['url'];
        if ($apiUrl === null) {
            throw new \RuntimeException('URL de l\'API IA non configurée.');
        }

        $model = $config['model'] ?: 'default';
        $apiKey = $config['apiKey'];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => $headers,
            'timeout' => 15.0,
            'json' => [
                'model' => $model,
                'temperature' => 0.2,
                'max_tokens' => 600,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en ingénierie pédagogique universitaire française (Service des Études et de la Scolarité / CFVU). '
                            . 'À partir d\'une liste structurée de modifications sur une fiche matière (EC), rédige une synthèse concise, factuelle et directement exploitable en commission ou par un administrateur. '
                            . 'Ne jamais inventer de chiffres. Distingue clairement les modifications structurantes (volumes horaires, ECTS, MCCC, intitulé) des ajustements descriptifs ou organisationnels. '
                            . 'Réponds en français avec un paragraphe de synthèse globale de 2 à 3 phrases, suivi de 2 à 5 points clés courts commençant par "- ".',
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPromptPayload($report),
                    ],
                ],
            ],
        ]);

        $data = $response->toArray();
        $rawText = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

        if ($rawText === '') {
            throw new \RuntimeException('Réponse vide du modèle IA.');
        }

        [$summaryText, $highlights] = $this->parseAiResponse($rawText);

        if ($highlights === []) {
            $highlights = $this->buildDeterministicHighlights($report);
        }

        return new AiDiffSummaryResult(
            summary: $summaryText,
            highlights: $highlights,
            isAiGenerated: true,
            providerLabel: sprintf('IA (%s)', $model),
            hasRegulatoryImpact: $hasRegulatoryImpact,
        );
    }

    private function buildPromptPayload(FicheMatiereChangeReport $report): string
    {
        $lines = [
            sprintf('Fiche matière : "%s" (#%d)', $report->ficheLibelle, $report->ficheId),
            sprintf('Référence comparée : %s', $report->sourceLabel),
            'Modifications constatées :',
        ];

        foreach ($report->getChangesByCategory() as $category => $items) {
            $lines[] = sprintf("\n[%s]", FicheMatiereChangeItem::labelForCategory($category));
            foreach ($items as $item) {
                $before = mb_strlen($item->before) > 350 ? mb_substr($item->before, 0, 350) . '…' : $item->before;
                $after = mb_strlen($item->after) > 350 ? mb_substr($item->after, 0, 350) . '…' : $item->after;
                $lines[] = sprintf('- %s : « %s » -> « %s »', $item->label, $before, $after);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseAiResponse(string $rawText): array
    {
        $lines = explode("\n", $rawText);
        $paragraphs = [];
        $highlights = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^[-*•]\s+(.+)$/u', $trimmed, $m)) {
                $highlights[] = trim((string) preg_replace('/\*\*(.+?)\*\*/', '$1', $m[1]));
            } else {
                $paragraphs[] = trim((string) preg_replace('/\*\*(.+?)\*\*/', '$1', $trimmed));
            }
        }

        $summary = $paragraphs !== []
            ? implode(' ', $paragraphs)
            : $rawText;

        return [$summary, $highlights];
    }

    private function buildDeterministicResult(
        FicheMatiereChangeReport $report,
        bool $hasRegulatoryImpact,
        ?string $fallbackReason = null,
    ): AiDiffSummaryResult {
        $byCategory = $report->getChangesByCategory();
        $totalCount = $report->countChanges();
        $categoriesLabels = $report->getImpactedCategoriesLabels();

        $intro = sprintf(
            'Cette fiche matière présente %d modification%s par rapport à la référence « %s », répartie%s sur : %s.',
            $totalCount,
            $totalCount > 1 ? 's' : '',
            $report->sourceLabel,
            $totalCount > 1 ? 's' : '',
            implode(', ', $categoriesLabels)
        );

        if ($hasRegulatoryImpact) {
            $impactSentence = 'Attention : des éléments soumis à validation en conseil / CFVU (volumes horaires, crédits ECTS, modalités de contrôle des connaissances ou intitulé) ont été modifiés.';
        } else {
            $impactSentence = 'Les changements portent uniquement sur les descriptifs pédagogiques, les compétences ou l\'organisation interne, sans impact sur les volumes horaires ni sur les MCCC.';
        }

        return new AiDiffSummaryResult(
            summary: $intro . ' ' . $impactSentence,
            highlights: $this->buildDeterministicHighlights($report),
            isAiGenerated: false,
            providerLabel: 'Synthèse structurée locale',
            hasRegulatoryImpact: $hasRegulatoryImpact,
            fallbackReason: $fallbackReason,
        );
    }

    /**
     * @return list<string>
     */
    private function buildDeterministicHighlights(FicheMatiereChangeReport $report): array
    {
        $highlights = [];
        foreach ($report->changes as $change) {
            if (in_array($change->field, ['description', 'objectifs'], true)) {
                $lenBefore = mb_strlen($change->before === 'Non renseigné' ? '' : $change->before);
                $lenAfter = mb_strlen($change->after === 'Non renseigné' ? '' : $change->after);
                if ($lenBefore === 0 && $lenAfter > 0) {
                    $highlights[] = sprintf('%s : rédaction initiale ajoutée (%d caractères).', $change->label, $lenAfter);
                } elseif ($lenAfter === 0 && $lenBefore > 0) {
                    $highlights[] = sprintf('%s : contenu supprimé.', $change->label);
                } else {
                    $delta = $lenAfter - $lenBefore;
                    $highlights[] = sprintf(
                        '%s : texte remanié (%s%d caractères).',
                        $change->label,
                        $delta >= 0 ? '+' : '',
                        $delta
                    );
                }
                continue;
            }

            $highlights[] = sprintf('%s : passage de « %s » à « %s ».', $change->label, $change->before, $change->after);
        }

        return $highlights;
    }

    private function detectRegulatoryImpact(FicheMatiereChangeReport $report): bool
    {
        foreach ($report->changes as $change) {
            if (in_array($change->category, [FicheMatiereChangeItem::CATEGORY_VOLUMES, FicheMatiereChangeItem::CATEGORY_MCCC], true)) {
                return true;
            }
            if (in_array($change->field, ['libelle', 'enseignementMutualise'], true)) {
                return true;
            }
        }

        return false;
    }
}

