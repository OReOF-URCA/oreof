<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Type de formulaire dynamique basé sur une définition JSON.
 *
 * Permet de générer automatiquement des champs de formulaire
 * à partir d'une définition de schéma stockée en JSON.
 *
 * Exemple de définition :
 * [
 *   "url_inscription" => [
 *     "type" => "url",
 *     "label" => "URL d'inscription",
 *     "required" => true,
 *     "help" => "Lien vers le formulaire d'inscription"
 *   ],
 *   "capacite_max" => [
 *     "type" => "integer",
 *     "label" => "Capacité maximale",
 *     "required" => false,
 *     "min" => 0,
 *     "max" => 9999
 *   ]
 * ]
 */
class DynamicFieldsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldDefinitions = $options['field_definitions'];

        if (empty($fieldDefinitions) || !is_array($fieldDefinitions)) {
            return;
        }

        foreach ($fieldDefinitions as $fieldName => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $fieldType = $this->getFormType($definition['type'] ?? 'text');
            $fieldOptions = $this->buildFieldOptions($definition);

            $builder->add($fieldName, $fieldType, $fieldOptions);
        }
    }

    /**
     * Retourne le type de formulaire Symfony correspondant au type de champ.
     */
    private function getFormType(string $type): string
    {
        return match ($type) {
            'text' => TextType::class,
            'textarea' => TextareaType::class,
            'email' => EmailType::class,
            'url' => UrlType::class,
            'integer', 'number' => IntegerType::class,
            'float', 'decimal' => NumberType::class,
            'checkbox', 'boolean' => CheckboxType::class,
            'date' => DateType::class,
            'choice', 'select' => ChoiceType::class,
            default => TextType::class,
        };
    }

    /**
     * Construit les options du champ à partir de la définition.
     */
    private function buildFieldOptions(array $definition): array
    {
        $options = [
            'label' => $definition['label'] ?? null,
            'required' => $definition['required'] ?? false,
            'help' => $definition['help'] ?? null,
            'attr' => $definition['attr'] ?? [],
        ];

        // Ajouter les contraintes de validation
        $constraints = [];

        if ($options['required']) {
            $constraints[] = new Assert\NotBlank([
                'message' => ($definition['label'] ?? 'Ce champ') . ' est obligatoire.',
            ]);
        }

        // Contraintes spécifiques selon le type
        $type = $definition['type'] ?? 'text';

        switch ($type) {
            case 'email':
                $constraints[] = new Assert\Email([
                    'message' => 'L\'adresse email {{ value }} n\'est pas valide.',
                ]);
                break;

            case 'url':
                $constraints[] = new Assert\Url([
                    'message' => 'L\'URL {{ value }} n\'est pas valide.',
                ]);
                break;

            case 'integer':
            case 'number':
            case 'float':
            case 'decimal':
                if (isset($definition['min'])) {
                    $constraints[] = new Assert\GreaterThanOrEqual([
                        'value' => $definition['min'],
                        'message' => 'La valeur doit être supérieure ou égale à {{ compared_value }}.',
                    ]);
                    $options['attr']['min'] = $definition['min'];
                }
                if (isset($definition['max'])) {
                    $constraints[] = new Assert\LessThanOrEqual([
                        'value' => $definition['max'],
                        'message' => 'La valeur doit être inférieure ou égale à {{ compared_value }}.',
                    ]);
                    $options['attr']['max'] = $definition['max'];
                }
                break;

            case 'text':
            case 'textarea':
                if (isset($definition['max_length'])) {
                    $constraints[] = new Assert\Length([
                        'max' => $definition['max_length'],
                        'maxMessage' => 'Le texte ne peut pas dépasser {{ limit }} caractères.',
                    ]);
                    $options['attr']['maxlength'] = $definition['max_length'];
                }
                break;

            case 'choice':
            case 'select':
                if (isset($definition['choices'])) {
                    $options['choices'] = $definition['choices'];
                }
                if (isset($definition['multiple'])) {
                    $options['multiple'] = $definition['multiple'];
                }
                if (isset($definition['expanded'])) {
                    $options['expanded'] = $definition['expanded'];
                }
                break;
        }

        if (!empty($constraints)) {
            $options['constraints'] = $constraints;
        }

        // Options supplémentaires depuis la définition
        if (isset($definition['placeholder'])) {
            $options['attr']['placeholder'] = $definition['placeholder'];
        }

        // Valeur par défaut
        if (isset($definition['default'])) {
            $options['data'] = $definition['default'];
        }

        return $options;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'field_definitions' => [],
        ]);

        $resolver->setAllowedTypes('field_definitions', 'array');
    }
}
