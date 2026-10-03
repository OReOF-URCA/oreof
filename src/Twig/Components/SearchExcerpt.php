<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Extrait de texte d'un résultat de recherche, termes trouvés surlignés (segments produits par FuzzySearch).
 */
#[AsTwigComponent('SearchExcerpt', template: 'components/search/excerpt.html.twig')]
final class SearchExcerpt
{
    /** @var list<array{texte: string, surligne: bool}> */
    public array $segments = [];

    public ?string $label = null;
}
