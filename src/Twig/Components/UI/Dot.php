<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/UI/Dot.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 29/05/2026 12:29
 */

namespace App\Twig\Components\UI;

use App\DTO\DotView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Composant minimaliste pour afficher un point/rond coloré avec tooltip optionnelle.
 * Idéal pour les indicateurs de statut, badges visuels compacts.
 *
 * Utilisation directe:
 *   <twig:Dot variant="success" size="md" tooltip="État valide" />
 *
 * Via DTO (badgeValidationDot filter):
 *   <twig:Dot :dto="status|badgeValidationDot('md', 'Custom tooltip')" />
 */
#[AsTwigComponent('Dot', template: 'components/_ui/dot.html.twig')]
final class Dot
{
    /** Mapping taille vers rem */
    private const SIZE_MAP = [
        'xs' => '0.75',
        'sm' => '1',
        'md' => '1.5',
        'lg' => '2',
        'xl' => '3',
    ];
    public ?DotView $dto = null;
    /** primary | success | warning | danger | info | secondary */
    public ?string $variant = null;
    /** xs (0.75) | sm (1) | md (1.5) | lg (2) | xl (3) */
    public ?string $size = 'xs';
    /** Texte de l'info-bulle (optionnel) */
    public ?string $tooltip = null;
    /** Classes CSS supplémentaires */
    public ?string $extraClass = null;

    #[PostMount]
    public function mount(): void
    {
        if ($this->dto === null) {
            return;
        }

        $this->variant ??= $this->dto->variant;
        $this->size ??= $this->dto->size;
        $this->tooltip ??= $this->dto->tooltip;
        $this->extraClass ??= $this->dto->extraClass;
    }

    public function getSizeValue(): string
    {
        return self::SIZE_MAP[$this->size ?? 'md'] ?? self::SIZE_MAP['md'];
    }

    public function getVariantClasses(): string
    {
        $variant = $this->variant ?? 'secondary';
        return match ($variant) {
            'success' => 'bg-green-500',
            'warning' => 'bg-yellow-400',
            'danger' => 'bg-red-500',
            'info' => 'bg-blue-500',
            'primary' => 'bg-indigo-600',
            'secondary' => 'bg-gray-400',
            default => 'bg-gray-400',
        };
    }
}






