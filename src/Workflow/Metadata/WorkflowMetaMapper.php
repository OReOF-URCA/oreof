<?php

namespace App\Workflow\Metadata;

use App\DTO\Workflow\FieldMetaDto;
use App\DTO\Workflow\ModalFormMetaDto;
use App\DTO\Workflow\WorkflowTransitionMetaDto;

final class WorkflowMetaMapper
{
    public function fromArray(array $meta): WorkflowTransitionMetaDto
    {
        $label = (string)($meta['label'] ?? 'Action');
        $description = isset($meta['description']) ? (string)$meta['description'] : null;

        $recipients = $meta['recipients'] ?? [];
        if (!\is_array($recipients)) {
            $recipients = [];
        }
        $recipients = array_values(array_filter($recipients, fn($v) => \is_string($v) && $v !== ''));

        $handler = isset($meta['handler']) ? (string)$meta['handler'] : null;

        $form = null;
        if (isset($meta['form']) && \is_array($meta['form'])) {
            $form = $this->mapForm($meta['form'], $label);
        }

        $validation = is_array($meta['validation'] ?? null) ? $meta['validation'] : [];
        $viewTemplate = $validation['view'] ?? $meta['view'] ?? null;
        $validationStep = $validation['step'] ?? null;

        return new WorkflowTransitionMetaDto(
            label: $label,
            description: $description,
            buttonClass: isset($meta['button_class']) ? (string)$meta['button_class'] : null,
            buttonIcon: isset($meta['button_icon']) ? (string)$meta['button_icon'] : null,
            type: isset($meta['type']) ? (string)$meta['type'] : null,
            recipients: $recipients,
            handlerCode: $handler,
            form: $form,
            viewTemplate: is_string($viewTemplate) && '' !== trim($viewTemplate) ? $viewTemplate : null,
            validationStep: is_string($validationStep) && '' !== trim($validationStep) ? $validationStep : null,
        );
    }

    private function mapForm(array $form, string $fallbackTitle): ModalFormMetaDto
    {
        $title = (string)($form['title'] ?? $fallbackTitle);
        $submitLabel = (string)($form['submit_label'] ?? 'Valider');
        $formId = (string)($form['id'] ?? 'modal_form');

        $formType = $form['type'] ?? null;
        if (!is_string($formType) || '' === trim($formType)) {
            $formType = null;
        }

        $formOptions = is_array($form['options'] ?? null) ? $form['options'] : [];

        $fieldsRaw = $form['fields'] ?? [];
        if (!\is_array($fieldsRaw)) {
            $fieldsRaw = [];
        }

        $fields = [];
        foreach ($fieldsRaw as $f) {
            if (!\is_array($f)) continue;

            $name = $f['name'] ?? null;
            $type = $f['type'] ?? null;
            if (!\is_string($name) || $name === '' || !\is_string($type) || $type === '') {
                continue;
            }

            $fields[] = new FieldMetaDto(
                name: $name,
                type: $type,
                required: (bool)($f['required'] ?? false),
                label: isset($f['label']) ? (string)$f['label'] : null,
                help: isset($f['help']) ? (string)$f['help'] : null,
                options: (\is_array($f['options'] ?? null) ? $f['options'] : []),
            );
        }

        $rulesRaw = $form['rules'] ?? [];
        $rules = is_array($rulesRaw)
            ? array_values(array_filter($rulesRaw, static fn (mixed $rule): bool => is_array($rule)))
            : [];

        return new ModalFormMetaDto(
            title: $title,
            submitLabel: $submitLabel,
            formId: $formId,
            fields: $fields,
            rules: $rules,
            formType: $formType,
            options: $formOptions,
        );
    }
}
