<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/DiffBadgeExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/05/2024 08:03
 */

namespace App\Twig;

use App\DTO\DiffObject;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DiffBadgeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('displayDiff', $this->displayDiff(...), ['is_safe' => ['html']]),
            new TwigFilter('diffNew', $this->diffNew(...), ['is_safe' => ['html']]),
            new TwigFilter('diffOriginal', $this->diffOriginal(...), ['is_safe' => ['html']]),
            new TwigFilter('diffNewOriginal', $this->diffNewOriginal(...), ['is_safe' => ['html']]),
            new TwigFilter('diffStrike', $this->diffStrike(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Ancienne valeur en rouge barré, nouvelle valeur en gras noir.
     */
    public function diffStrike(DiffObject $value): string
    {
        if (false === $value->isDifferent()) {
            return (string) $value->new;
        }

        return '<span class="text-danger text-decoration-line-through">' . $value->original . '</span> '
            . '<strong>' . $value->new . '</strong>';
    }

    public function displayDiff(DiffObject $value): string
    {
        if (false === $value->isDifferent()) {
            return $value->original ?? '';
        }

        return '<span class="text-danger text-decoration-line-through">'.$value->original.'</span> <span class="text-success">'.$value->new.'</span>';
    }

    public function diffNew(DiffObject $value): string
    {
        if (false === $value->isDifferent()) {
            return $value->new;
        }

        return '<span class="diff-new">'.$value->new.'</span>';
    }

    public function diffOriginal(DiffObject $value): string
    {
        if (false === $value->isDifferent()) {
            return $value->original;
        }

        return '<span class="diff-original">'.$value->original.'</span>';
    }

    public function diffNewOriginal(DiffObject $value): string
    {
        if (false === $value->isDifferent()) {
            return $value->original. ' (pas de modification)';
        }
        return '<span class="diff-new">'.$value->new.'</span> (au lieu de <span class="diff-original">'.$value->original.'</span>)';
    }
}
