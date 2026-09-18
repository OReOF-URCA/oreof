<?php

namespace App\Workflow\Form;

use App\DTO\Workflow\ModalFormMetaDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MetaDrivenFormFactory
{
    public function __construct(
        private readonly FormFactoryInterface  $formFactory,
        private readonly MetaFormTypeResolver  $typeResolver,
        private readonly MetaFormOptionsFilter $optionsFilter,
    )
    {
    }

    /** @param array<string, mixed> $additionalOptions */
    public function create(ModalFormMetaDto $meta, string $transition, array $additionalOptions = []): FormInterface
    {
        if (null !== $meta->formType) {
            if (!class_exists($meta->formType) || !is_a($meta->formType, AbstractType::class, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Le FormType "%s" configuré pour la transition "%s" est invalide.',
                    $meta->formType,
                    $transition,
                ));
            }

            $options = array_replace_recursive(
                ['attr' => ['id' => $meta->formId]],
                $meta->options,
                $additionalOptions,
            );

            return $this->formFactory->create($meta->formType, null, $options);
        }

        $builder = $this->formFactory->createBuilder(FormType::class, null, [
            'attr' => ['id' => $meta->formId],
            'translation_domain' => 'process',
            'constraints' => $this->buildConstraints($meta),
        ]);

        foreach ($meta->fields as $field) {
            $type = $this->typeResolver->resolve($field->type);

            $options = [
                'required' => $field->required,
            ];

            $options['label_translation_parameters'] = [
                '%transition%' => $transition,
                '%field%' => $field->name,
            ];

            $options['help_translation_parameters'] = [
                '%transition%' => $transition,
                '%field%' => $field->name,
            ];

            $options = array_replace($options, $this->optionsFilter->filter($field->options));

            $builder->add($field->name, $type, $options);
        }

        return $builder->getForm();
    }

    /** @return list<Callback> */
    private function buildConstraints(ModalFormMetaDto $meta): array
    {
        $constraints = [];

        foreach ($meta->rules as $rule) {
            if ('at_least_one' !== ($rule['type'] ?? null)) {
                continue;
            }

            $fields = array_values(array_filter(
                $rule['fields'] ?? [],
                static fn (mixed $field): bool => is_string($field) && '' !== trim($field),
            ));
            $message = (string) ($rule['message'] ?? 'Au moins une des valeurs demandées doit être renseignée.');

            $constraints[] = new Callback(
                callback: static function (mixed $data, ExecutionContextInterface $context) use ($fields, $message): void {
                    if (!is_array($data)) {
                        return;
                    }

                    foreach ($fields as $field) {
                        $value = $data[$field] ?? null;
                        if (true === $value || (is_object($value) && null !== $value)) {
                            return;
                        }

                        if (is_scalar($value) && '' !== trim((string) $value) && '0' !== (string) $value) {
                            return;
                        }
                    }

                    $context->buildViolation($message)->addViolation();
                },
            );
        }

        return $constraints;
    }

    public function createEmpty(string $formId = 'modal_form'): FormInterface
    {
        return $this->formFactory->createBuilder(FormType::class, null, [
            'attr' => ['id' => $formId],
            'translation_domain' => 'form',
        ])->getForm();
    }
}
