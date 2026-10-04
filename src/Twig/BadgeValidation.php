<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/BadgeValidation.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/01/2026 08:01
 */

namespace App\Twig;

use App\DTO\BadgeView;
use App\DTO\DotView;
use App\Entity\ValidationIssue;
use App\Enums\ValidationStatusEnum;
use App\Presenter\BadgePresenter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BadgeValidation extends AbstractExtension
{
    public function __construct(private readonly BadgePresenter $badgePresenter)
    {
    }

    public function getFilters(): array
    {
        return [
            // Nouvelles méthodes retournant BadgeView pour le composant Badge
            new TwigFilter('badgeValidationLongDto', $this->badgeValidationLongDto(...)),
            new TwigFilter('badgeValidationShortDto', $this->badgeValidationShortDto(...)),
            // Nouveau filtre pour le composant Dot (rond coloré minimaliste)
            new TwigFilter('badgeValidationDot', $this->badgeValidationDot(...)),
            new TwigFilter('displayMessage', $this->displayMessage(...), ['is_safe' => ['html']])
        ];
    }

    public function displayMessage(ValidationIssue $issue): string
    {
        switch ($issue->getRuleCode()) {
            case 'EC_MISSING':
                return $issue->getMessage() . '(' . $issue->getPayload()['ec'] . ')';
            case 'UE_MISSING':
                return $issue->getMessage() . '(' . $issue->getPayload()['ue'] . ')';
            case 'MCCC_MISSING':
                return $issue->getMessage() . '(' . $issue->getPayload()['ec'] . ')';
            case 'BCC_INCOMPLETE':
                return $issue->getMessage() . '(' . $issue->getPayload()['ec'] . ')';
            case 'ECTS_INVALID':
                return $issue->getMessage() . '(' . $issue->getPayload()['ec'] . ')';
            case 'FICHE_MATIERE_MISSING':
                return $issue->getMessage() . '(' . $issue->getPayload()['ec'] . ', ' . $issue->getPayload()['ue'] . ' )';
            default:
                return $issue->getMessage();
        }
    }

    /**
     * Retourne un BadgeView pour le composant Badge (version courte - cercle coloré)
     */
    public function badgeValidationShortDto(ValidationStatusEnum $status, string $size = '1.5'): BadgeView
    {
        return $this->badgePresenter->fromValidationStatusShort($status, $size);
    }

    /**
     * Retourne un BadgeView pour le composant Badge (version longue - avec libellé et icône)
     */
    public function badgeValidationLongDto(ValidationStatusEnum $status): BadgeView
    {
        return $this->badgePresenter->fromValidationStatusLong($status);
    }

    /**
     * Retourne un DotView pour le composant Dot (simple rond coloré avec tooltip)
     */
    public function badgeValidationDot(ValidationStatusEnum $status, string $size = 'md', string $tooltip = ''): DotView
    {
        return $this->badgePresenter->fromValidationStatusDot($status, $size, $tooltip);
    }

}
