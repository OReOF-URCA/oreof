<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/UI/DeleteButton.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 17/05/2026 19:57
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('DeleteButton', template: 'components/_ui/delete_button.html.twig')]
final class DeleteButton
{
    /** URL cible de la suppression */
    public string $url = '';

    /** Token id pour csrf_token(...) */
    public string $csrfTokenId = '';

    /** POST par defaut + _method si necessaire (DELETE, PUT...) */
    public string $method = 'POST';

    /** Champs caches supplementaires */
    public array $hiddenFields = [];

    /** Texte du bouton declencheur */
    public string $label = '';

    /** Texte de tooltip du bouton declencheur */
    public string $tooltip = 'Supprimer';

    /** Icône du bouton declencheur */
    public string $icon = 'icon:delete';

    /** Style du bouton declencheur */
    public string $variant = 'danger';


    /** Classes CSS supplémentaires */
    public string $extraClass = '';

    public string $size = 'sm';

    public bool $soft = true;

    public bool $outline = false;

    public bool $disabled = false;

    /** Texte de la modale */
    public string $title = 'Confirmer la suppression';

    public string $message = 'Cette action est irreversible. Voulez-vous continuer ?';

    public string $confirmLabel = 'Supprimer';

    public string $cancelLabel = 'Annuler';

    /** Template facultatif pour le contenu de la modale */
    public string $bodyTemplate = '';

    public array $bodyContext = [];

    /** Suffixe optionnel pour stabiliser l'id HTML */
    public string $id = '';
}

