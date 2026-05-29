<?php

namespace App\Serializer;

use App\Enums\ModaliteEnseignementEnum;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ModaliteEnseignementEnumNormalizer implements DenormalizerInterface
{
    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return $type === ModaliteEnseignementEnum::class;
    }

    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
        if (is_null($data)) {
            return ModaliteEnseignementEnum::NON_DEFINI;
        }

        if (is_array($data)) {
            $data = $data['value'] ?? null;
        }

        return ModaliteEnseignementEnum::tryFrom($data) ?? ModaliteEnseignementEnum::NON_DEFINI;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ModaliteEnseignementEnum::class => true,
        ];
    }
}
