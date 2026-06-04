<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/StatsBlockComponent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/10/2025 11:21
 */

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('stats_block')]
class StatsBlockComponent
{
    public array $charts = [];
    public array $titles = [];
    public bool $collapsible = false;
}
