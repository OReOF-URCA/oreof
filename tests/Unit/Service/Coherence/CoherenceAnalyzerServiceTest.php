<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Coherence;

use App\DTO\Coherence\CoherenceIssue;
use App\Entity\CoherenceAnalyse;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Repository\CoherenceAnalyseRepository;
use App\Service\Ai\LlmClient;
use App\Service\Coherence\CoherenceAnalyzerService;
use App\Service\Coherence\ParcoursCoherencePayloadBuilder;
use App\Service\Coherence\TextQualityDetector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CoherenceAnalyzerServiceTest extends TestCase
{
    public function testDeterministicEvaluationFlagsPlaceholderAndStructuralStageMismatch(): void
    {
        $parcours = $this->buildSampleParcours();
        $parcours->setObjectifsParcours('<p>Lorem ipsum dolor sit amet</p>');
        $parcours->setHasStage(true);
        $parcours->setStageText(null);
        $parcours->setNbHeuresStages(0);

        $service = $this->createService(envAiKey: '');

        $result = $service->getIndicatorForParcours($parcours);

        self::assertSame(CoherenceAnalyse::STATUS_ALERT, $result->status);
        self::assertFalse($result->isAiGenerated);
        self::assertGreaterThan(0, $result->getAlertsCount());
        self::assertGreaterThan(0, $result->getWarningsCount());
        self::assertLessThan(70, $result->score);
    }

    public function testAnalyzeParcoursUsesLlmAndMergesIssues(): void
    {
        $parcours = $this->buildSampleParcours();

        $llmJson = json_encode([
            'score' => 68,
            'summary' => 'Les objectifs évoquent le droit alors que le parcours porte sur l\'informatique.',
            'issues' => [
                [
                    'field' => 'objectifsParcours',
                    'fieldLabel' => 'Objectifs du parcours',
                    'severity' => 'warning',
                    'category' => 'incoherence',
                    'message' => 'Décalage thématique entre les objectifs juridiques et la maquette informatique.',
                    'excerpt' => 'Former des spécialistes en droit pénal international.',
                ],
            ],
        ]);

        $mockResponse = new MockResponse((string)json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $llmJson,
                    ],
                ],
            ],
        ]));

        $service = $this->createService(
            httpClient: new MockHttpClient([$mockResponse]),
            envAiUrl: 'https://api.mistral.ai/v1/chat/completions',
            envAiKey: 'sk-test-coherence',
            envAiModel: 'mistral-small-latest',
        );

        $result = $service->analyzeParcours($parcours, useAi: true, forceRefresh: true, persist: false);

        self::assertTrue($result->isAiGenerated);
        self::assertSame('IA (mistral-small-latest)', $result->providerLabel);
        self::assertSame(CoherenceAnalyse::STATUS_WARNING, $result->status);
        self::assertSame(68, $result->score);
        self::assertStringContainsString('droit', $result->summary);

        $aiIssues = array_values(array_filter(
            $result->issues,
            static fn(CoherenceIssue $issue): bool => $issue->source === CoherenceIssue::SOURCE_AI
        ));
        self::assertCount(1, $aiIssues);
        self::assertSame(CoherenceIssue::CATEGORY_INCOHERENCE, $aiIssues[0]->category);
    }

    public function testGetIndicatorDetectsStalePersistedAnalysisWhenContentChanges(): void
    {
        $parcours = $this->buildSampleParcours();
        $detector = new TextQualityDetector();
        $builder = new ParcoursCoherencePayloadBuilder($detector);

        $oldHash = $builder->computeHash($builder->buildParcoursPayload($parcours));

        $persisted = (new CoherenceAnalyse())
            ->setParcours($parcours)
            ->setContentHash($oldHash)
            ->setStatus(CoherenceAnalyse::STATUS_OK)
            ->setScore(95)
            ->setDeterministicScore(100)
            ->setSummary('Contenu cohérent.')
            ->setIsAiGenerated(true)
            ->setProviderLabel('IA (test)')
            ->setAnalyzedAt(new \DateTimeImmutable('-1 hour'));

        $parcours->setCoherenceAnalyse($persisted);

        $service = $this->createService();

        $fresh = $service->getIndicatorForParcours($parcours);
        self::assertFalse($fresh->isStale);
        self::assertSame(95, $fresh->score);

        // Modification du contenu textuel
        $parcours->setContenuFormation('<p>Nouveau contenu mis à jour avec des détails supplémentaires sur les UE.</p>');
        $stale = $service->getIndicatorForParcours($parcours);
        self::assertTrue($stale->isStale);
        self::assertTrue($stale->isPersisted);

        // Si un contenu fictif est saisi après coup, le pré-filtre alerte immédiatement
        $parcours->setObjectifsParcours('<p>Lorem ipsum dolor sit amet</p>');
        $immediateAlert = $service->getIndicatorForParcours($parcours);
        self::assertTrue($immediateAlert->isStale);
        self::assertSame(CoherenceAnalyse::STATUS_ALERT, $immediateAlert->status);
    }

    public function testAnalyzeFicheMatiereFallsBackWhenAiThrows(): void
    {
        $fiche = new FicheMatiere();
        $fiche->setLibelle('Algorithmique avancée');
        $fiche->setDescription('<p>Étude des graphes, programmation dynamique et complexité algorithmique.</p>');
        $fiche->setObjectifs('<p>Maîtriser les structures de données avancées et concevoir des algorithmes optimaux.</p>');

        $failingClient = new MockHttpClient([
            new MockResponse('Upstream timeout', ['http_code' => 503]),
        ]);

        $service = $this->createService(
            httpClient: $failingClient,
            envAiUrl: 'https://api.mistral.ai/v1/chat/completions',
            envAiKey: 'sk-test-coherence',
            envAiModel: 'mistral-small-latest',
        );

        $result = $service->analyzeFicheMatiere($fiche, useAi: true, forceRefresh: true, persist: false);

        self::assertFalse($result->isAiGenerated);
        self::assertNotNull($result->fallbackReason);
        self::assertSame(CoherenceAnalyse::STATUS_OK, $result->status);
    }

    private function buildSampleParcours(): Parcours
    {
        $typeDiplome = new TypeDiplome();
        $typeDiplome->setLibelle('Master');
        $typeDiplome->setLibelleCourt('MASTER');

        $formation = new Formation();
        $formation->setTypeDiplome($typeDiplome);
        $formation->setClassique(false);

        $parcours = new Parcours();
        $parcours->setFormation($formation);
        $parcours->setLibelle('Architecture Logicielle');
        $parcours->setObjectifsParcours('<p>Former des architectes capables de concevoir des systèmes logiciels robustes et maintenables.</p>');
        $parcours->setResultatsAttendus('<p>Concevoir, modéliser et déployer des architectures distribuées résilientes.</p>');
        $parcours->setContenuFormation('<p>Génie logiciel, patrons de conception, systèmes distribués, sécurité et qualité logicielle.</p>');

        return $parcours;
    }

    private function createService(
        ?MockHttpClient $httpClient = null,
        string          $envAiUrl = '',
        string          $envAiKey = '',
        string          $envAiModel = '',
    ): CoherenceAnalyzerService
    {
        $detector = new TextQualityDetector();
        $payloadBuilder = new ParcoursCoherencePayloadBuilder($detector);
        $llmClient = new LlmClient(
            httpClient: $httpClient ?? new MockHttpClient(),
            etablissementRepository: null,
            envAiUrl: $envAiUrl,
            envAiKey: $envAiKey,
            envAiModel: $envAiModel,
        );

        $repo = $this->createStub(CoherenceAnalyseRepository::class);
        $repo->method('findForParcours')->willReturn(null);
        $repo->method('findForFicheMatiere')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);

        return new CoherenceAnalyzerService(
            payloadBuilder: $payloadBuilder,
            textQualityDetector: $detector,
            llmClient: $llmClient,
            analyseRepository: $repo,
            entityManager: $em,
        );
    }
}
