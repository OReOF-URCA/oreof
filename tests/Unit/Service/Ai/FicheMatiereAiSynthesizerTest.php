<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\DTO\Comparison\FicheMatiereChangeItem;
use App\DTO\Comparison\FicheMatiereChangeReport;
use App\Service\Ai\FicheMatiereAiSynthesizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FicheMatiereAiSynthesizerTest extends TestCase
{
    public function testSummarizeFallsBackToDeterministicSummaryWhenNoApiKey(): void
    {
        $httpClient = new MockHttpClient();
        $cache = new ArrayAdapter();
        $synthesizer = new FicheMatiereAiSynthesizer(
            httpClient: $httpClient,
            cache: $cache,
            aiApiUrl: 'https://api.mistral.ai/v1/chat/completions',
            aiApiKey: '',
            aiModel: 'mistral-small-latest',
        );

        $report = new FicheMatiereChangeReport(
            ficheId: 42,
            ficheLibelle: 'Thermodynamique',
            ficheSlug: 'thermodynamique',
            sourceType: FicheMatiereChangeReport::SOURCE_ORIGINE_N1,
            sourceLabel: 'Fiche d\'origine N-1 (2025-2026)',
            hasSnapshotAvailable: false,
            hasOrigineN1Available: true,
            changes: [
                new FicheMatiereChangeItem(
                    category: FicheMatiereChangeItem::CATEGORY_VOLUMES,
                    field: 'volumeCmPresentiel',
                    label: 'CM Présentiel',
                    before: '10 h',
                    after: '14 h (+4 h)',
                ),
                new FicheMatiereChangeItem(
                    category: FicheMatiereChangeItem::CATEGORY_MCCC,
                    field: 'ects',
                    label: 'Crédits ECTS',
                    before: '3 ECTS',
                    after: '4 ECTS (+1 ECTS)',
                ),
            ],
        );

        $result = $synthesizer->summarize($report);

        self::assertFalse($result->isAiGenerated);
        self::assertTrue($result->hasRegulatoryImpact);
        self::assertCount(2, $result->highlights);
        self::assertStringContainsString('2 modifications', $result->summary);
        self::assertNotNull($result->fallbackReason);
    }

    public function testSummarizeCallsLlmApiWhenApiKeyConfigured(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => "La fiche matière évolue sur ses volumes horaires et ses crédits ECTS.\n- Augmentation de 4h de CM présentiel.\n- Passage de 3 à 4 ECTS.",
                    ],
                ],
            ],
        ]));

        $httpClient = new MockHttpClient([$mockResponse]);
        $cache = new ArrayAdapter();
        $synthesizer = new FicheMatiereAiSynthesizer(
            httpClient: $httpClient,
            cache: $cache,
            aiApiUrl: 'https://api.mistral.ai/v1/chat/completions',
            aiApiKey: 'test-secret-key',
            aiModel: 'mistral-small-latest',
        );

        $report = new FicheMatiereChangeReport(
            ficheId: 42,
            ficheLibelle: 'Thermodynamique',
            ficheSlug: 'thermodynamique',
            sourceType: FicheMatiereChangeReport::SOURCE_SNAPSHOT_VALIDE,
            sourceLabel: 'Snapshot JSON validé du 03/04/2026',
            hasSnapshotAvailable: true,
            hasOrigineN1Available: false,
            changes: [
                new FicheMatiereChangeItem(
                    category: FicheMatiereChangeItem::CATEGORY_VOLUMES,
                    field: 'volumeCmPresentiel',
                    label: 'CM Présentiel',
                    before: '10 h',
                    after: '14 h (+4 h)',
                ),
            ],
        );

        $result = $synthesizer->summarize($report);

        self::assertTrue($result->isAiGenerated);
        self::assertSame('IA (mistral-small-latest)', $result->providerLabel);
        self::assertTrue($result->hasRegulatoryImpact);
        self::assertSame('La fiche matière évolue sur ses volumes horaires et ses crédits ECTS.', $result->summary);
        self::assertCount(2, $result->highlights);
    }
}

