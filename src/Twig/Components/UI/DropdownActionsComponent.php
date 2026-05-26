<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Twig/Components/UI/DropdownActionsComponent.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 19/05/2026 13:58
 */

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'dropdown_actions', template: 'components/_ui/dropdown_actions.html.twig')]
class DropdownActionsComponent
{
    /**
     * @var array<int, array{
     *   label: string,
     *   url?: string,
     *   icon?: string,
     *   method?: 'GET'|'POST'|'DELETE',
     *   turboFrame?: string,
     *   confirm?: string,
     *   danger?: bool,
     *   disabled?: bool,
     *   csrf?: string
     * }>
     */
    public array $items = [];

    /** Texte SR pour le bouton */
    public string $label = 'Actions';
    public string $help = 'Actions';
    public bool $labelSrOnly = false;

    /** Permet de distinguer plusieurs dropdown sur une page (facultatif) */
    public ?string $id = null;
}
