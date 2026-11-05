<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Referentiel\ReferentielManager;
use BackedEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @deprecated Use ModaliteEnseignementType or a dedicated string-key referential field
 *             once the underlying entity property supports configurable values.
 * Transitional choice field: displays configurable labels while preserving
 * the existing backed-enum entity property, API payload and database format.
 *
 * Custom referential values without a matching legacy enum are intentionally
 * not selectable until the corresponding entity field is migrated.
 */
final class LegacyReferentielEnumType extends AbstractType
{
    public function __construct(private readonly ReferentielManager $referentiels)
    {
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['referentiel', 'enum_class']);
        $resolver->setAllowedTypes('referentiel', 'string');
        $resolver->setAllowedTypes('enum_class', 'string');

        $resolver->setDefaults([
            'choice_label' => static fn (BackedEnum $enum): string => $enum->name,
            'choice_value' => static fn (?BackedEnum $enum): string => $enum === null ? '' : (string) $enum->value,
            'choices' => function (Options $options): array {
                $enumClass = $options['enum_class'];
                if (!enum_exists($enumClass) || !is_subclass_of($enumClass, BackedEnum::class)) {
                    throw new \InvalidArgumentException(sprintf('Expected a backed enum, got "%s".', $enumClass));
                }

                $values = $this->referentiels->getValeurs($options['referentiel'], true);
                if ($values === []) {
                    return $enumClass::cases();
                }

                $choices = [];
                foreach ($values as $value) {
                    if ($value->getLegacyValue() === null) {
                        continue;
                    }
                    $enum = $enumClass::tryFrom(
                        (new \ReflectionEnum($enumClass))->getBackingType()?->getName() === 'int'
                            ? (int) $value->getLegacyValue()
                            : $value->getLegacyValue()
                    );
                    if ($enum !== null) {
                        $choices[] = $enum;
                    }
                }

                // Keep all legacy cases selectable while the entity is enum-backed.
                foreach ($enumClass::cases() as $case) {
                    if (!in_array($case, $choices, true)) {
                        $choices[] = $case;
                    }
                }

                return $choices;
            },
        ]);

        $resolver->setDefault('choice_label', function (Options $options): callable {
            return function (BackedEnum $enum) use ($options): string {
                return $this->referentiels->label($options['referentiel'], $enum->value)
                    ?? (method_exists($enum, 'libelle') ? $enum->libelle() : (string) $enum->value);
            };
        });
    }
}
