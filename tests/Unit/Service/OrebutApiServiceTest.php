<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Composante;
use App\Entity\Etablissement;
use App\Entity\Formation;
use App\Service\Api\OrebutApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OrebutApiServiceTest extends TestCase
{
    private function createFormation(bool $withApi = false, ?string $customUrl = null): Formation
    {
        $etablissement = new Etablissement();
        if ($withApi) {
            $etablissement->setApiKeys([
                [
                    'plateforme' => 'ORéBUT',
                    'url' => $customUrl ?? 'https://custom-orebut.test/api',
                    'apiKey' => 'test_token',
                ],
            ]);
        }

        $composante = new Composante();
        $composante->setEtablissement($etablissement);

        $formation = new Formation(null);
        $formation->setSigle('INFO');
        $formation->setComposantePorteuse($composante);

        return $formation;
    }

    public function testIsConfiguredAndConfiguration(): void
    {
        $httpClient = new MockHttpClient();
        $service = new OrebutApiService($httpClient, new NullLogger());

        $formationWithout = $this->createFormation(false);
        $this->assertFalse($service->isConfigured($formationWithout));
        $this->assertSame('https://orebut.iut.fr/api', $service->getApiUrl($formationWithout));

        $formationWith = $this->createFormation(true, 'https://orebut.custom.fr/api/');
        $this->assertTrue($service->isConfigured($formationWith));
        $this->assertSame('https://orebut.custom.fr/api', $service->getApiUrl($formationWith));
        $config = $service->getConfiguration($formationWith);
        $this->assertNotNull($config);
        $this->assertSame('test_token', $config['apiKey']);
    }

    public function testGetDepartementSuccess(): void
    {
        $mockData = [
            'sigle' => 'INFO',
            'libelle' => 'Informatique',
            'typeStructure' => 'type2',
            'apcParcours' => [
                ['code' => 'DA', 'libelle' => 'Déploiement d\'applications'],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($mockData, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);

        $httpClient = new MockHttpClient([$mockResponse]);
        $service = new OrebutApiService($httpClient, new NullLogger());

        $formation = $this->createFormation(false);
        $result = $service->getDepartement('INFO', $formation);

        $this->assertNotNull($result);
        $this->assertSame('INFO', $result['sigle']);
        $this->assertSame('Informatique', $result['libelle']);
    }

    public function testGetDepartementNotFoundReturnsNull(): void
    {
        $mockResponse = new MockResponse('Not found', [
            'http_code' => 404,
        ]);

        $httpClient = new MockHttpClient([$mockResponse]);
        $service = new OrebutApiService($httpClient, new NullLogger());

        $result = $service->getDepartement('INCONNU');
        $this->assertNull($result);
    }

    public function testGetCompetences(): void
    {
        $mockData = [
            'hydra:member' => [
                [
                    'id' => 1,
                    'numero' => 1,
                    'nom_court' => 'Réaliser',
                    'libelle' => 'Réaliser un développement d\'application',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($mockData, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/ld+json'],
        ]);

        $httpClient = new MockHttpClient([$mockResponse]);
        $service = new OrebutApiService($httpClient, new NullLogger());

        $competences = $service->getCompetences('INFO');
        $this->assertCount(1, $competences);
        $this->assertSame('Réaliser', $competences[0]['nom_court']);
    }

    public function testGetRessourcesWithPagination(): void
    {
        $page1Data = [
            'hydra:member' => [
                ['codeMatiere' => 'R1.01', 'libelle' => 'Initiation au développement'],
            ],
            'hydra:view' => [
                'hydra:next' => '/specialite/info/ressources?page=2',
            ],
        ];

        $page2Data = [
            'hydra:member' => [
                ['codeMatiere' => 'R1.02', 'libelle' => 'Développement d\'interfaces web'],
            ],
            'hydra:view' => [],
        ];

        $responses = [
            new MockResponse(json_encode($page1Data, JSON_THROW_ON_ERROR), ['http_code' => 200]),
            new MockResponse(json_encode($page2Data, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ];

        $httpClient = new MockHttpClient($responses);
        $service = new OrebutApiService($httpClient, new NullLogger());

        $ressources = $service->getRessources('INFO');
        $this->assertCount(2, $ressources);
        $this->assertSame('R1.01', $ressources[0]['codeMatiere']);
        $this->assertSame('R1.02', $ressources[1]['codeMatiere']);
    }

    public function testGetSaes(): void
    {
        $mockData = [
            'hydra:member' => [
                ['codeMatiere' => 'S1.01', 'libelle' => 'Implémentation d\'un besoin client'],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($mockData, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient([$mockResponse]);
        $service = new OrebutApiService($httpClient, new NullLogger());

        $saes = $service->getSaes('INFO');
        $this->assertCount(1, $saes);
        $this->assertSame('S1.01', $saes[0]['codeMatiere']);
    }

    public function testAnneeParameterIncludedInRequests(): void
    {
        $mockResponse = new MockResponse(json_encode(['sigle' => 'INFO', 'libelle' => 'Info 2027'], JSON_THROW_ON_ERROR), [
            'http_code' => 200,
        ]);

        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($mockResponse, &$requestedUrl) {
            $requestedUrl = $url;
            return $mockResponse;
        });

        $service = new OrebutApiService($httpClient, new NullLogger());
        $service->getDepartement('INFO', null, 2027);

        $this->assertNotNull($requestedUrl);
        $this->assertStringContainsString('annee=2027', $requestedUrl);
    }
}
