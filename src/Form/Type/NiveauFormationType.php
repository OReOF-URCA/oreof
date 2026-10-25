<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Enums\NiveauFormationEnum;
use App\Referentiel\NiveauFormation;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Choice field returning canonical string keys, including custom formation levels. */
class NiveauFormationType extends AbstractType
{
    public function __construct(private readonly ?ReferentielManager $referentiels = null)
    {
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // When an entity already has an inactive value assigned, ensure it remains in choices.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            if ($data === null || $data === '') {
                return;
            }

            $key = NiveauFormation::key($data);
            $form = $event->getForm();
            $config = $form->getConfig();
            $choices = $config->getOption('choices') ?? [];

            if (!in_array($key, $choices, true)) {
                $label = $this->referentiels?->label('niveau_formation', $key) ?? $key;
                $newChoices = $choices;
                $newChoices[$label] = $key;
                $parent = $form->getParent();
                if ($parent !== null) {
                    $parentOptions = $config->getOptions();
                    $parentOptions['choices'] = $newChoices;
                    $parent->add($form->getName(), static::class, $parentOptions);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => function (Options $options): array {
                $values = $this->referentiels?->choices('niveau_formation') ?? [];
                if ($values === []) {
                    foreach (NiveauFormationEnum::cases() as $case) {
                        $values[(string) $case->value] = $case->libelle();
                    }
                }

                // Inactive values already assigned to this record must remain selectable.
                $data = $options['data'] ?? null;
                if ($data !== null && $data !== '') {
                    $key = NiveauFormation::key($data);
                    $values[$key] ??= $this->referentiels?->label('niveau_formation', $key) ?? $key;
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
