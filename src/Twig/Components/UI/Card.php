<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Twig/Components/UI/Card.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 27/05/2026 11:33
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Card', template: 'components/_ui/card.html.twig')]
final class Card
{
    public string $title = '';

    public string $description = '';

    /** default | elevated | flat */
    public string $variant = 'default';

    /** Liste de boutons/actions ou fragments HTML */
    public array $actions = [];

    /** Contenu HTML du footer optionnel */
    public null|array|string $footer = null;

    /** Classes CSS supplémentaires */
    public string $extraClass = '';
}

