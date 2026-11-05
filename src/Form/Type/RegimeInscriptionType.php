<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Enums\RegimeInscriptionEnum;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

/**
 * Uses the persisted enum backed values for historical records and stable
 * referential codes for new values. The model contains strings, not enums.
 */
final class RegimeInscriptionType extends AbstractType
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
        $resolver->setDefaults([
            'choices' => function (Options $options): array {
                $values = $this->referentiels->choices('regime_inscription');
                if ($values === []) {
                    foreach (RegimeInscriptionEnum::cases() as $case) {
                        $values[$case->value] = $case->value;
                    }
                }

                // Include inactive values already assigned to this record, so
                // opening and saving an existing form never silently loses them.
                $data = $options['data'] ?? null;
                if (is_array($data)) {
                    foreach ($data as $item) {
                        $key = \App\Referentiel\RegimeInscription::key($item);
                        $values[$key] ??= $this->referentiels->label('regime_inscription', $key) ?? $key;
                    }
                }

                $choices = [];
                foreach ($values as $key => $label) {
                    $display = $label;
                    if (array_key_exists($display, $choices)) {
                        $display .= ' (' . $key . ')';
                    }
                    $choices[$display] = (string) $key;
                }

                return $choices;
            },
            'choice_translation_domain' => false,
            'multiple' => true,
            'expanded' => true,
        ]);
    }
}
