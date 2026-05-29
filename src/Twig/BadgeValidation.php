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
            // Anciennes méthodes dépréciées pour rétrocompatibilité
            //   new TwigFilter('badgeValidationLong', $this->badgeValidationLong(...), ['is_safe' => ['html']]),
            //   new TwigFilter('badgeValidationShort', $this->badgeValidationShort(...), ['is_safe' => ['html']]),
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
     * @deprecated Utiliser badgeValidationShortDto et le composant Twig Badge au lieu de cette méthode
     */
    public function badgeValidationShort(ValidationStatusEnum $status, string $size = '1.5'): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeValidationShortDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return match ($status) {
            ValidationStatusEnum::VALID => '<span class="inline-block w-' . $size . ' h-' . $size . ' rounded-full bg-green-400"></span>',
            ValidationStatusEnum::INVALID => '<span class="inline-block w-' . $size . ' h-' . $size . ' rounded-full bg-red-400"></span>',
            ValidationStatusEnum::INCOMPLETE => '<span class="inline-block w-' . $size . ' h-' . $size . ' rounded-full bg-orange-300"></span>',
            ValidationStatusEnum::NA => '<span class="inline-block w-' . $size . ' h-' . $size . ' rounded-full bg-gray-400"></span>',
        };
    }

    /**
     * @deprecated Utiliser badgeValidationLongDto et le composant Twig Badge au lieu de cette méthode
     */
    public function badgeValidationLong(ValidationStatusEnum $status): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeValidationLongDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return match ($status) {
            ValidationStatusEnum::VALID => '<span class="inline-block px-2 py-0.5 rounded-full bg-green-600 text-white text-sm">● Conforme aux règles</span>',
            ValidationStatusEnum::INVALID => '<span class="inline-block px-2 py-0.5 rounded-full bg-red-600 text-white text-sm">● Non conforme aux règles</span>',
            ValidationStatusEnum::INCOMPLETE => '<span class="inline-block px-2 py-0.5 rounded-full bg-yellow-300 text-gray-800 text-sm">● Incomplet</span>',
            ValidationStatusEnum::NA => '<span class="inline-block px-2 py-0.5 rounded-full bg-gray-400 text-white text-sm">Non applicable</span>',
        };
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
