<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file src/Twig/Components/UI/PageHeader.php
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * En-tête de page (titre, description, boutons d'action, fil d'Ariane), sticky par défaut.
 *
 * Titre/description : soit un texte déjà traduit (`title`, `description`), soit une clé de traduction
 * (`titleKey`, `descriptionKey` + `translationParams`, `translationDomain`) ; la clé est prioritaire.
 * Les props `title`, `description` et `actions` acceptent un texte ou un fragment Twig capturé (`Markup`).
 */
#[AsTwigComponent('PageHeader', template: 'components/_ui/page_header.html.twig')]
final class PageHeader
{
    public mixed $title = null;

    public mixed $description = null;

    public ?string $titleKey = null;

    public ?string $descriptionKey = null;

    /** @var array<string, mixed> */
    public array $translationParams = [];

    public string $translationDomain = 'header';

    /** Affiche le fil d'Ariane (et le trait de séparation). */
    public bool $breadcrumb = true;

    /** Reste visible au scroll (description masquée, titre réduit). */
    public bool $sticky = true;

    /** Boutons d'action (alternative au bloc `actions`). */
    public mixed $actions = null;
}
