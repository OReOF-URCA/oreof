<?php

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Bouton qui copie une URL dans le presse-papiers (contrôleur Stimulus `copy-link`).
 *
 * <twig:CopyLinkButton url="{{ url('app_tutoriel_index') }}" />
 */
#[AsTwigComponent('CopyLinkButton', template: 'components/_ui/copy_link_button.html.twig')]
final class CopyLinkButton
{
    /** URL absolue à copier */
    public string $url = '';

    public string $label = 'Copier le lien';

    public string $copiedLabel = 'Lien copié';

    public string $variant = 'secondary';

    public string $size = 'sm';
}
