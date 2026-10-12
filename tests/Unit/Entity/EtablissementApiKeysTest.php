<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Etablissement;
use PHPUnit\Framework\TestCase;

class EtablissementApiKeysTest extends TestCase
{
    public function testDefaultApiKeysIsEmpty(): void
    {
        $etablissement = new Etablissement();
        $this->assertSame([], $etablissement->getApiKeys());
        $this->assertNull($etablissement->getApiKeyForPlatform('Apogée'));
        $this->assertSame('', $etablissement->getApiPlatformsList());
    }

    public function testSetAndGetApiKeys(): void
    {
        $etablissement = new Etablissement();

        $data = [
            [
                'plateforme' => 'Apogée',
                'url' => 'https://apogee.univ-reims.fr/api',
                'login' => 'user_apogee',
                'apiKey' => 'secret_token_123',
            ],
            [
                'plateforme' => 'Pégase',
                'url' => 'https://pegase.univ-reims.fr/api',
                'login' => null,
                'apiKey' => 'pegase_key_456',
            ],
        ];

        $etablissement->setApiKeys($data);

        $this->assertCount(2, $etablissement->getApiKeys());
        $this->assertSame('Apogée, Pégase', $etablissement->getApiPlatformsList());

        $apogee = $etablissement->getApiKeyForPlatform('Apogée');
        $this->assertNotNull($apogee);
        $this->assertSame('https://apogee.univ-reims.fr/api', $apogee['url']);
        $this->assertSame('user_apogee', $apogee['login']);
        $this->assertSame('secret_token_123', $apogee['apiKey']);

        // Case-insensitive lookup
        $pegase = $etablissement->getApiKeyForPlatform('pégase');
        $this->assertNotNull($pegase);
        $this->assertNull($pegase['login']);
        $this->assertSame('pegase_key_456', $pegase['apiKey']);
    }

    public function testSetApiKeysFiltersEmptyEntries(): void
    {
        $etablissement = new Etablissement();

        $data = [
            [
                'plateforme' => 'MonMaster',
                'url' => 'https://monmaster.gouv.fr/api',
                'login' => '',
                'apiKey' => 'mm_secret',
            ],
            [
                'plateforme' => '   ',
                'url' => '',
                'login' => '',
                'apiKey' => '',
            ],
        ];

        $etablissement->setApiKeys($data);

        $this->assertCount(1, $etablissement->getApiKeys());
        $this->assertSame('MonMaster', $etablissement->getApiKeys()[0]['plateforme']);
        $this->assertNull($etablissement->getApiKeys()[0]['login']);
    }

    public function testSetApiKeysWithNullResetsToEmptyArray(): void
    {
        $etablissement = new Etablissement();
        $etablissement->setApiKeys([
            ['plateforme' => 'Test', 'url' => 'https://test.fr', 'apiKey' => 'abc']
        ]);
        $this->assertCount(1, $etablissement->getApiKeys());

        $etablissement->setApiKeys(null);
        $this->assertSame([], $etablissement->getApiKeys());
    }
}
