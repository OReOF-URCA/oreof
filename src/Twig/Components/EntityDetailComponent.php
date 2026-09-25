<?php

namespace App\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('EntityDetail', template: 'components/entity_detail.html.twig')]
class EntityDetailComponent
{
    public object $entity;
    public array $config = [];

    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }

    public function getEntityDisplayLabel(?object $entity, array $field): string
    {
        if (null === $entity) {
            return (string)($field['null_label'] ?? '');
        }

        $entityLabel = $field['entity_label'] ?? '__toString';

        if ('__toString' === $entityLabel && method_exists($entity, '__toString')) {
            return (string)$entity;
        }

        $value = $this->getFieldValue($entity, $entityLabel);

        if (null === $value) {
            return sprintf('%s #%s', $entity::class, (string)($this->getEntityIdentifier($entity) ?? '?'));
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return sprintf('%s #%s', $entity::class, (string)($this->getEntityIdentifier($entity) ?? '?'));
    }

    public function getFieldValue(object $entity, string|array $field): mixed
    {
        if (is_array($field)) {
            if (isset($field['callback']) && is_callable($field['callback'])) {
                return ($field['callback'])($entity);
            }

            $field = (string)($field['field'] ?? '');
        }

        $parts = explode('.', $field);
        $value = $entity;

        foreach ($parts as $part) {
            if ($value === null) {
                return null;
            }

            // Essayer le getter standard
            $getter = 'get' . ucfirst($part);
            if (method_exists($value, $getter)) {
                $value = $value->$getter();
                continue;
            }

            // Essayer le getter boolean
            $isGetter = 'is' . ucfirst($part);
            if (method_exists($value, $isGetter)) {
                $value = $value->$isGetter();
                continue;
            }

            $hasGetter = 'has' . ucfirst($part);
            if (method_exists($value, $hasGetter)) {
                $value = $value->$hasGetter();
                continue;
            }

            // Si c'est déjà une méthode (avec parenthèses)
            $methodName = rtrim($part, '()');
            if (method_exists($value, $methodName)) {
                $value = $value->$methodName();
                continue;
            }

            return null;
        }

        return $value;
    }

    private function getEntityIdentifier(object $entity): int|string|null
    {
        $identifierValues = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);
        if (1 !== count($identifierValues)) {
            return null;
        }

        return array_values($identifierValues)[0];
    }

    /**
     * Retourne la classe CSS Bootstrap du badge d'un Enum.
     * Priorité : enum_color_method (ex: getColor()) > badge_map > badge_class (fallback)
     */
    public function getEnumBadgeClass(mixed $value, array $field): string
    {
        if (!($value instanceof \UnitEnum)) {
            return $field['badge_class'] ?? 'bg-secondary';
        }

        // 1. Méthode sur l'enum retournant directement la couleur (ex: getColor() => 'success')
        $colorMethod = $field['enum_color_method'] ?? null;
        if ($colorMethod && method_exists($value, $colorMethod)) {
            $color = $value->$colorMethod();
            // Si la méthode retourne 'success', 'primary', etc. => on préfixe bg-
            if (is_string($color) && !str_starts_with($color, 'bg-')) {
                return 'bg-' . $color;
            }
            return (string)$color;
        }

        // 2. badge_map statique (clé = valeur/name de l'enum)
        $key = ($value instanceof \BackedEnum) ? (string)$value->value : $value->name;
        $badgeMap = $field['badge_map'] ?? [];
        if (!empty($badgeMap)) {
            return $badgeMap[$key] ?? $badgeMap[strtolower($key)] ?? ($field['badge_class'] ?? 'bg-secondary');
        }

        return $field['badge_class'] ?? 'bg-secondary';
    }

    /**
     * Retourne le libellé d'un Enum pour l'affichage.
     * Priorité : enum_label_method > enum_map > BackedEnum->value > UnitEnum->name
     */
    public function getEnumLabel(mixed $value, array $field): string
    {
        if (!($value instanceof \UnitEnum)) {
            return (string)($field['empty_text'] ?? '-');
        }

        // 1. Méthode dédiée sur l'enum (ex: label(), getLabel()...)
        $method = $field['enum_label_method'] ?? null;
        if ($method && method_exists($value, $method)) {
            return (string)$value->$method();
        }

        // 2. Map statique fournie via le builder
        $enumMap = $field['enum_map'] ?? [];
        $key = ($value instanceof \BackedEnum) ? (string)$value->value : $value->name;
        if (!empty($enumMap) && isset($enumMap[$key])) {
            return (string)$enumMap[$key];
        }

        // 3. Valeur native : BackedEnum->value, sinon UnitEnum->name
        return $key;
    }
}
