<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Twig/Components/UI/Spinner.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 21/05/2026 10:48
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Spinner', template: 'components/_ui/spinner.html.twig')]
final class Spinner
{
    /** primary | success | warning | danger | info | secondary */
    public string $variant = 'secondary';

    /** sm | md | lg */
    public string $size = 'md';

    /** Texte d'accessibilite affiche dans le screen reader */
    public string $label = 'Chargement...';

    /** Classes CSS supplementaires */
    public string $extraClass = '';
}

