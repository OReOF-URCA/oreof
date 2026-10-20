<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Enums\ModaliteEnseignementEnum;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Choice field returning canonical string keys, including custom modalities. */
final class ModaliteEnseignementType extends AbstractType
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
                $values = $this->referentiels->choices('modalite_enseignement');
                if ($values === []) {
                    foreach (ModaliteEnseignementEnum::cases() as $case) {
                        $values[(string) $case->value] = $case->libelle();
                    }
                }

                // Inactive values must remain readable when already selected.
                foreach ($this->referentiels->getValeurs('modalite_enseignement', true) as $value) {
                    $key = $value->getLegacyValue() ?? $value->getCode();
                    $values[$key] ??= $value->getLibelle();
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
            'required' => false,
            'placeholder' => false,
        ]);
    }
}
