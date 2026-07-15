<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('SearchForm', template: 'components/search/form.html.twig')]
final class SearchForm
{
    public string $action = '';

    public string $keyword = '';

    public string $searchType = 'parcours';

    public string $fieldId = 'search-keyword';

    public string $placeholder = 'Entrez votre recherche...';

    public string $submitLabel = 'Rechercher';

    public string $helperText = 'Saisissez au moins 3 caractères.';

    public bool $compact = false;

    public bool $autofocus = false;

    public ?string $resetHref = null;

    public string $extraClass = '';
}
