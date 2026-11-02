<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer;

use App\Entity\FicheMatiere;
use App\Serializer\FicheMatiereDenormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class FicheMatiereDenormalizerTest extends TestCase
{
    public function testSupportsDenormalization(): void
    {
        $denormalizer = new FicheMatiereDenormalizer();

        self::assertTrue($denormalizer->supportsDenormalization([], FicheMatiere::class));
        self::assertFalse($denormalizer->supportsDenormalization([], \stdClass::class));
        self::assertFalse($denormalizer->supportsDenormalization('not-array', FicheMatiere::class));
    }

    public function testDenormalizeTransformsLegacyArrays(): void
    {
        $denormalizer = new FicheMatiereDenormalizer();

        $nestedDenormalizer = $this->createMock(DenormalizerInterface::class);
        $nestedDenormalizer->expects(self::once())
            ->method('denormalize')
            ->with(
                self::callback(function (array $data): bool {
                    return $data['langueDispense'] === ['fr']
                        && $data['langueSupport'] === ['en', 'fr'];
                }),
                FicheMatiere::class,
                null,
                self::callback(function (array $context): bool {
                    return ($context[FicheMatiereDenormalizer::ALREADY_CALLED] ?? false) === true;
                })
            )
            ->willReturn(new FicheMatiere());

        $denormalizer->setDenormalizer($nestedDenormalizer);

        $input = [
            'libelle' => 'Test',
            'langueDispense' => [
                ['libelle' => 'Français'],
            ],
            'langueSupport' => [
                ['codeIso' => 'en'],
                ['libelle' => 'Français'],
            ],
        ];

        $result = $denormalizer->denormalize($input, FicheMatiere::class);
        self::assertInstanceOf(FicheMatiere::class, $result);
    }

    public function testFicheMatiereNormalizesLegacyInputs(): void
    {
        $fiche = new FicheMatiere();
        $fiche->setLangueDispense([['libelle' => 'Français']]);
        self::assertSame(['fr'], $fiche->getLangueDispense());

        $fiche->setLangueSupport(['en', ['libelle' => 'Espagnol']]);
        self::assertSame(['en', 'es'], $fiche->getLangueSupport());

        $fiche->setLangueDispense([]);
        self::assertSame(['fr'], $fiche->getLangueDispense(), 'Empty array should fallback to [fr]');
    }
}

