<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Composante;
use App\Entity\Etablissement;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Service\Api\RomeApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RomeApiServiceTest extends TestCase
{
    private function createParcoursWithRomeApi(bool $withApi = true): Parcours
    {
        $etablissement = new Etablissement();
        if ($withApi) {
            $etablissement->setApiKeys([
                [
                    'plateforme' => 'ROME 4.0',
                    'url' => 'https://api.francetravail.io/partenaire/rome-fiches-metiers/v1',
                    'login' => 'client_id_test',
                    'apiKey' => 'client_secret_test',
                ],
            ]);
        }

        $composante = new Composante();
        $composante->setEtablissement($etablissement);

        $formation = new Formation(null);
        $formation->setComposantePorteuse($composante);

        $parcours = new Parcours($formation);

        return $parcours;
    }

    public function testIsConfigured(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $service = new RomeApiService($httpClient, $cache, new NullLogger());

        $parcoursWithout = $this->createParcoursWithRomeApi(false);
        $this->assertFalse($service->isConfigured($parcoursWithout));

        $parcoursWith = $this->createParcoursWithRomeApi(true);
        $this->assertTrue($service->isConfigured($parcoursWith));
    }

    public function testSearchLocalFallback(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $service = new RomeApiService($httpClient, $cache, new NullLogger());

        $parcours = $this->createParcoursWithRomeApi(false);
        $results = $service->search($parcours, 'informatique');

        $this->assertNotEmpty($results);
        $codes = array_column($results, 'code');
        $this->assertContains('M1805', $codes);
    }

    public function testVerifyCodesSyntaxAndLocalRecognition(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $service = new RomeApiService($httpClient, $cache, new NullLogger());

        $parcours = $this->createParcoursWithRomeApi(false);
        $results = $service->verifyCodes($parcours, ['M1805', 'INVALIDE', 'Z9999']);

        $this->assertCount(3, $results);

        // M1805
        $this->assertSame('M1805', $results[0]['code']);
        $this->assertTrue($results[0]['valid']);
        $this->assertSame('Études et développement informatique', $results[0]['libelle']);

        // INVALIDE
        $this->assertSame('INVALIDE', $results[1]['code']);
        $this->assertFalse($results[1]['valid']);

        // Z9999 format correct mais non répertorié
        $this->assertSame('Z9999', $results[2]['code']);
        $this->assertTrue($results[2]['valid']);
    }

    public function testVerifyWithMockHttpApi(): void
    {
        $cache = new ArrayAdapter();

        $responses = [
            // OAuth2 token
            new MockResponse(json_encode(['access_token' => 'fake_token_123'])),
            // Fiche métier M1805
            new MockResponse(json_encode([
                'code' => 'M1805',
                'libelle' => 'Études et développement informatique (Officiel API)',
            ])),
        ];

        $httpClient = new MockHttpClient($responses);
        $service = new RomeApiService($httpClient, $cache, new NullLogger());

        $parcours = $this->createParcoursWithRomeApi(true);
        $results = $service->verifyCodes($parcours, ['M1805']);

        $this->assertCount(1, $results);
        $this->assertSame('M1805', $results[0]['code']);
        $this->assertTrue($results[0]['valid']);
        $this->assertStringContainsString('Officiel API', $results[0]['libelle']);
        $this->assertStringContainsString('conforme ROME 4.0', $results[0]['message']);
    }
}
