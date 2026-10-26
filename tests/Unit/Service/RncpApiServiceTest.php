<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Etablissement;
use App\Repository\EtablissementRepository;
use App\Service\Api\RncpApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RncpApiServiceTest extends TestCase
{
    private function createEtablissement(bool $withApi = true): Etablissement
    {
        $etablissement = new Etablissement();
        if ($withApi) {
            $etablissement->setApiKeys([
                [
                    'plateforme' => 'Apprentissage (RNCP)',
                    'url' => 'https://api.apprentissage.beta.gouv.fr/api',
                    'login' => null,
                    'apiKey' => 'test_bearer_token_123',
                ],
            ]);
        }

        return $etablissement;
    }

    private function createEtablissementRepository(?Etablissement $etablissement = null): EtablissementRepository
    {
        $repo = $this->createMock(EtablissementRepository::class);
        $repo->method('findOneBy')->willReturn($etablissement);

        return $repo;
    }

    public function testNormalizeCode(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $repo = $this->createEtablissementRepository();
        $service = new RncpApiService($httpClient, $cache, new NullLogger(), $repo);

        $this->assertSame('RNCP34436', $service->normalizeCode('34436'));
        $this->assertSame('RNCP34436', $service->normalizeCode('rncp34436'));
        $this->assertSame('RNCP34436', $service->normalizeCode('rncp 34436 '));
        $this->assertSame('RNCP123', $service->normalizeCode('123'));
        $this->assertSame('', $service->normalizeCode(''));
    }

    public function testIsConfigured(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $etabWith = $this->createEtablissement(true);
        $repo = $this->createEtablissementRepository($etabWith);
        $service = new RncpApiService($httpClient, $cache, new NullLogger(), $repo);

        $this->assertTrue($service->isConfigured($etabWith));

        $etabWithout = $this->createEtablissement(false);
        $this->assertFalse($service->isConfigured($etabWithout));
    }

    public function testVerifyInvalidSyntax(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $repo = $this->createEtablissementRepository();
        $service = new RncpApiService($httpClient, $cache, new NullLogger(), $repo);

        $result = $service->verify(null, 'ABC');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Format RNCP invalide', $result['message']);
    }

    public function testVerifyWithMockHttpApi(): void
    {
        $cache = new ArrayAdapter();
        $mockData = [
            [
                'identifiant' => [
                    'rncp' => 'RNCP34436',
                    'cfd' => '30032601',
                ],
                'intitule' => [
                    'rncp' => 'Licence Professionnelle - Métiers de l\'informatique',
                ],
                'actif' => true,
                'periode_validite' => [
                    'debut' => '2020-01-01',
                    'fin' => '2027-08-31',
                ],
                'certificateurs' => [
                    ['nom' => 'Université de Reims Champagne-Ardenne'],
                ],
                'niveau' => '6 (Bac+3)',
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($mockData), ['http_code' => 200]),
        ]);

        $etab = $this->createEtablissement(true);
        $repo = $this->createEtablissementRepository($etab);
        $service = new RncpApiService($httpClient, $cache, new NullLogger(), $repo);

        $result = $service->verify($etab, '34436');

        $this->assertTrue($result['valid']);
        $this->assertSame('RNCP34436', $result['code']);
        $this->assertTrue($result['actif']);
        $this->assertSame('Licence Professionnelle - Métiers de l\'informatique', $result['intitule']);
        $this->assertSame('2027-08-31', $result['dateFinValidite']);
        $this->assertSame('Université de Reims Champagne-Ardenne', $result['certificateur']);
        $this->assertStringContainsString('34436', $result['url']);
        $this->assertTrue($result['connected']);
    }

    public function testVerifyFallbackWhenApiNotConfigured(): void
    {
        $cache = new ArrayAdapter();
        $httpClient = new MockHttpClient();
        $etab = $this->createEtablissement(false);
        $repo = $this->createEtablissementRepository($etab);
        $service = new RncpApiService($httpClient, $cache, new NullLogger(), $repo);

        $result = $service->verify($etab, 'RNCP34436');

        $this->assertTrue($result['valid']);
        $this->assertSame('RNCP34436', $result['code']);
        $this->assertNull($result['actif']);
        $this->assertFalse($result['connected']);
        $this->assertStringContainsString('34436', $result['url']);
    }
}
