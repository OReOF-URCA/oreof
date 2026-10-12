<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Etablissement;
use App\Form\EtablissementApiKeysType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class EtablissementApiKeysTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $etablissement = new Etablissement();

        $formData = [
            'apiKeys' => [
                [
                    'plateforme' => 'Apogée',
                    'url' => 'https://apogee.univ-reims.fr/api',
                    'login' => 'user_apogee',
                    'apiKey' => 'mon_secret_123',
                ],
                [
                    'plateforme' => 'Pégase',
                    'url' => 'https://pegase.gouv.fr/ws',
                    'login' => null,
                    'apiKey' => 'mon_secret_pegase',
                ],
            ],
        ];

        $form = $this->factory->create(EtablissementApiKeysType::class, $etablissement);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        $this->assertCount(2, $etablissement->getApiKeys());
        $this->assertSame('Apogée', $etablissement->getApiKeys()[0]['plateforme']);
        $this->assertSame('https://apogee.univ-reims.fr/api', $etablissement->getApiKeys()[0]['url']);
        $this->assertSame('mon_secret_123', $etablissement->getApiKeys()[0]['apiKey']);
    }
}
