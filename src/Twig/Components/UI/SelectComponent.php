<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @author louca
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Select', template: 'components/_ui/select.html.twig')]
final class SelectComponent
{
    public string $name = '';
    public ?string $id = null;
    public ?string $label = null;
    public ?string $placeholder = null;
    public ?string $help = null;
    public string $translationDomain = 'form';
    
    /** @var array<string|int, string>|array<int, array{value: string|int, label: string}> */
    public array $options = [];
    
    /** @var string|int|array<string|int>|null */
    public mixed $value = null;

    public bool $autocomplete = false;
    public bool $multiple = false;
    public bool $required = false;
    public bool $disabled = false;
    
    public array $attr = [];
    public string $extraClass = '';

    /**
     * @return array<int, array{value: string|int, label: string, selected: bool}>
     */
    public function getNormalizedOptions(): array
    {
        $normalized = [];
        $currentValues = is_array($this->value) ? $this->value : ($this->value !== null ? [$this->value] : []);
        $currentValues = array_map('strval', $currentValues);

        foreach ($this->options as $key => $option) {
            if (is_array($option)) {
                $val = strval($option['value'] ?? $key);
                $lbl = strval($option['label'] ?? $val);
            } else {
                $val = strval($key);
                $lbl = strval($option);
            }

            $normalized[] = [
                'value' => $val,
                'label' => $lbl,
                'selected' => in_array($val, $currentValues, true),
            ];
        }

        return $normalized;
    }
}
