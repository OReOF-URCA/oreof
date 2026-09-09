<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/_ui/Button.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 03/05/2026 23:08
 */

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent("Button", template: 'components/_ui/button.html.twig')]
final class Button
{
    /** primary | success | warning | danger | info | secondary */
    public string $variant = 'primary';

    /** sm | md | lg */
    public string $size = 'sm';

    /** outline (bordure seule) ou solid (fond coloré) */
    public bool $outline = false;

    /** soft : fond teinté léger + bordure claire (style par défaut) */
    public bool $soft = true;

    /** Nom de l'icône ux-icon (ex: icon:edit, ph:plus-bold) */
    public string $icon = '';

    /** Icône positionnée après le label */
    public bool $iconEnd = false;

    /** Texte du bouton */
    public string $label = '';

    /** Si renseigné, rend un <a>, sinon un <button> */
    public string $href = '';

    /** target du lien (<a>) */
    public string $target = '';

    /** rel du lien (<a>) */
    public string $rel = '';

    /** Attribut form (pour les boutons hors formulaire) */
    public string $form = '';

    /** Type du bouton : button | submit */
    public string $type = 'button';

    /** Titre tooltip */
    public string $tooltip = '';

    /** Position du tooltip Stimulus */
    public string $tooltipPlacement = 'bottom';

    /** Classe custom du tooltip Stimulus */
    public string $tooltipCustomClass = 'app-tooltip';

    /** Désactivé */
    public bool $disabled = false;

    /** Classes CSS supplémentaires */
    public string $extraClass = '';

    /** data-action Stimulus optionnel */
    public string $dataAction = '';

    /** Attributs HTML additionnels.
     * - array : ['data-live-action-param' => 'sort']
     * - string : live_action(...) ou tout bloc d'attributs brut
     */
    public mixed $attr = [];

    /** Bouton pleine largeur */
    public bool $fullWidth = false;

    /** Centre le contenu du bouton */
    public bool $centered = true;

    public function getSizeClass(): string
    {
        return match ($this->size) {
            'md' => 'px-4 py-2 text-sm h-10',
            'lg' => 'px-5 py-2.5 text-base h-11',
            default => 'px-2.5 py-2 text-sm h-8',
        };
    }

    public function getColorClass(): string
    {
        if ($this->soft) {
            return match ($this->variant) {
                'primary'   => 'border border-primary-300 bg-primary-50 text-primary-700 hover:bg-primary-100 hover:text-primary-800 dark:border-primary-500 dark:bg-primary-900 dark:text-primary-300 dark:hover:border-primary-300 dark:hover:text-primary-100',
                'success'   => 'border border-success-300 bg-success-50 text-success-700 hover:bg-success-100 hover:text-success-800 dark:border-success-500 dark:bg-success-900 dark:text-success-300 dark:hover:border-success-300 dark:hover:text-success-100',
                'warning'   => 'border border-warning-300 bg-warning-50 text-warning-700 hover:bg-warning-100 hover:text-warning-800 dark:border-warning-500 dark:bg-warning-900 dark:text-warning-300 dark:hover:border-warning-300 dark:hover:text-warning-100',
                'danger'    => 'border border-danger-300 bg-danger-50 text-danger-700 hover:bg-danger-100 hover:text-danger-800 dark:border-danger-500 dark:bg-danger-900 dark:text-danger-300 dark:hover:border-danger-300 dark:hover:text-danger-100',
                'info'      => 'border border-info-300 bg-info-50 text-info-700 hover:bg-info-100 hover:text-info-800 dark:border-info-500 dark:bg-info-900 dark:text-info-300 dark:hover:border-info-300 dark:hover:text-info-100',
                default     => 'border border-secondary-300 bg-secondary-50 text-secondary-600 hover:bg-secondary-100 hover:text-secondary-800 dark:border-secondary-600 dark:bg-surface dark:text-secondary-300 dark:hover:border-secondary-300 dark:hover:text-secondary-100 dark:hover:bg-secondary-900',
            };
        }

        if ($this->outline) {
            return match ($this->variant) {
                'primary'   => 'border border-primary-600 text-primary-700 hover:bg-primary-600 hover:text-white focus:ring-primary-500 dark:border-primary-400 dark:text-primary-300 dark:hover:bg-primary-500',
                'success'   => 'border border-success-600 text-success-700 hover:bg-success-600 hover:text-white focus:ring-success-500 dark:border-success-400 dark:text-success-300 dark:hover:bg-success-500',
                'warning'   => 'border border-warning-500 text-warning-700 hover:bg-warning-500 hover:text-secondary-900 focus:ring-warning-400 dark:border-warning-400 dark:text-warning-300 dark:hover:bg-warning-400 dark:hover:text-secondary-900',
                'danger'    => 'border border-danger-600 text-danger-700 hover:bg-danger-600 hover:text-white focus:ring-danger-500 dark:border-danger-400 dark:text-danger-300 dark:hover:bg-danger-500',
                'info'      => 'border border-info-600 text-info-700 hover:bg-info-600 hover:text-white focus:ring-info-500 dark:border-info-400 dark:text-info-300 dark:hover:bg-info-500',
                default     => 'border border-secondary-400 text-secondary-600 hover:bg-secondary-500 hover:text-white focus:ring-secondary-400 dark:border-secondary-500 dark:text-secondary-200 dark:hover:bg-secondary-600',
            };
        }

        return match ($this->variant) {
            'primary'   => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500 border border-transparent',
            'success'   => 'bg-success-600 text-white hover:bg-success-700 focus:ring-success-500 border border-transparent',
            'warning'   => 'bg-warning-500 text-secondary-900 hover:bg-warning-600 focus:ring-warning-400 border border-transparent',
            'danger'    => 'bg-danger-600 text-white hover:bg-danger-700 focus:ring-danger-500 border border-transparent',
            'info'      => 'bg-info-600 text-white hover:bg-info-700 focus:ring-info-500 border border-transparent',
            default     => 'bg-secondary-500 text-white hover:bg-secondary-600 focus:ring-secondary-400 border border-transparent',
        };
    }

    public function getAllClasses(): string
    {
        $classes = [
            'inline-flex items-center gap-1.5 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap',
            $this->centered ? 'justify-center' : '',
            $this->fullWidth ? 'w-full' : '',
            $this->getSizeClass(),
            $this->getColorClass(),
            $this->disabled ? 'pointer-events-none opacity-50' : '',
            $this->extraClass,
        ];

        return implode(' ', array_filter($classes));
    }
}
