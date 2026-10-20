<?php

namespace App\Serializer;

use App\Enums\ModaliteEnseignementEnum;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @deprecated Legacy enum-only denormalizer. Use canonical referential keys for
 *             new payloads; this adapter cannot represent custom modalities.
 */
class ModaliteEnseignementEnumNormalizer implements DenormalizerInterface
{
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ModaliteEnseignementEnum::class;
    }

    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (is_null($data)) {
            return ModaliteEnseignementEnum::NON_DEFINI;
        }

        if (is_array($data)) {
            $data = $data['value'] ?? null;
        }

        return \App\Referentiel\ModaliteEnseignement::legacyCase(is_int($data) || is_string($data) ? $data : null)
            ?? ModaliteEnseignementEnum::NON_DEFINI;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ModaliteEnseignementEnum::class => true,
        ];
    }
}
