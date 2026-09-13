<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/UI/Kpi.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 13/09/2026
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Composant KPI / Compteur statistique synthétique.
 *
 * Utilisation:
 *   <twig:Kpi title="Complétude" value="45" total="50" :percent="90" icon="mdi:chart-pie" />
 *   <twig:Kpi title="En cours" value="12" description="En rédaction" variant="secondary" icon="icon:edit" />
 *   <twig:Kpi title="Attente RP" value="3" description="À valider" variant="warning" icon="icon:clock" />
 *   <twig:Kpi title="Validées" value="28" description="Pour publication" variant="success" icon="icon:check-circle" />
 */
#[AsTwigComponent('Kpi', template: 'components/_ui/kpi.html.twig')]
final class Kpi
{
    /** Libellé / Titre principal (ex: "Complétude", "En cours", "Total fiches") */
    public string $title = '';

    /** Alias optionnel pour title */
    public ?string $label = null;

    /** Valeur principale affichée (ex: 42, "12", 98.5) */
    public string|int|float $value = 0;

    /** Total / Dénominateur optionnel (ex: 50 pour afficher "12 / 50") */
    public null|string|int|float $total = null;

    /** Unité de mesure optionnelle (ex: "h", "ECTS", "%", "€") */
    public ?string $unit = null;

    /** Pourcentage de progression (ex: 75 -> affiche la jauge et "75%") */
    public null|int|float $percent = null;

    /** Active/désactive la barre de progression si percent est fourni */
    public bool $showProgress = true;

    /** Sous-titre ou description contextuelle (ex: "En rédaction", "À valider") */
    public ?string $description = null;

    /** primary | success | warning | danger | info | secondary | default */
    public string $variant = 'default';

    /** Icône optionnelle (ex: 'icon:edit', 'icon:check-circle', 'mdi:chart-pie') */
    public ?string $icon = null;

    /** Lien optionnel */
    public ?string $href = null;

    /** Classes CSS supplémentaires */
    public string $extraClass = '';

    public function getResolvedTitle(): string
    {
        return $this->label ?? $this->title;
    }

    public function getIconColorClass(): string
    {
        return match ($this->variant) {
            'primary' => 'text-primary-500 dark:text-primary-400',
            'success' => 'text-emerald-500 dark:text-emerald-400',
            'warning' => 'text-amber-500 dark:text-amber-400',
            'danger' => 'text-rose-500 dark:text-rose-400',
            'info' => 'text-cyan-500 dark:text-cyan-400',
            'secondary' => 'text-secondary-400 dark:text-secondary-500',
            default => 'text-primary-500 dark:text-primary-400',
        };
    }

    public function getValueColorClass(): string
    {
        return match ($this->variant) {
            'primary' => 'text-primary-600 dark:text-primary-400',
            'success' => 'text-emerald-600 dark:text-emerald-400',
            'warning' => 'text-amber-600 dark:text-amber-400',
            'danger' => 'text-rose-600 dark:text-rose-400',
            'info' => 'text-cyan-600 dark:text-cyan-400',
            'secondary' => 'text-secondary-700 dark:text-secondary-200',
            default => 'text-secondary-900 dark:text-white',
        };
    }

    public function getProgressBarClass(): string
    {
        return match ($this->variant) {
            'primary' => 'bg-primary-500',
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger' => 'bg-danger-500',
            'info' => 'bg-cyan-500',
            'secondary' => 'bg-secondary-500',
            default => 'bg-primary-500',
        };
    }
}
