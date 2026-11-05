<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\FicheMatiere;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class FicheMatiereDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const ALREADY_CALLED = 'FICHE_MATIERE_DENORMALIZER_ALREADY_CALLED';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (is_array($data)) {
            if (isset($data['langueDispense'])) {
                $data['langueDispense'] = FicheMatiere::normalizeLangueArray((array) $data['langueDispense']);
            }
            if (isset($data['langueSupport'])) {
                $data['langueSupport'] = FicheMatiere::normalizeLangueArray((array) $data['langueSupport']);
            }
        }

        $context[self::ALREADY_CALLED] = true;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_array($data)
            && $type === FicheMatiere::class
            && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            FicheMatiere::class => false,
        ];
    }
}
