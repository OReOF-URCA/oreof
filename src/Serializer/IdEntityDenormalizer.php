<?php

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class IdEntityDenormalizer implements DenormalizerAwareInterface, DenormalizerInterface
{
    use DenormalizerAwareTrait;

    // Le denormalizer ne doit être appelé qu'une seule fois par objet
    private const ALREADY_CALLED = 'ID_ENTITY_DENORMALIZER_ALREADY_CALLED';

    // Si le résultat peut être mis en cache
    private array $supportedEntities = [
        'App\Entity\ElementConstitutif' => false,
        'App\Entity\Ue' => false,
    ];

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []) : mixed
    {
        // Si l'on dénormalise l'objet, on le marque dans le contexte, pour éviter un deuxième passage
        $context[self::ALREADY_CALLED] = true;
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);
        $object->setDeserializedId($data['id']);

        return $object;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []) : bool
    {
        return array_key_exists($type, $this->supportedEntities)
            && isset($context[self::ALREADY_CALLED]) === false
            && $format === 'json';
    }

    public function getSupportedTypes(?string $format): array {
        return $this->supportedEntities;
    }

}
