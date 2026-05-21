<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/TypeUeEcEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2024 16:33
 */

namespace App\Enums;

enum TypeUeEcEnum: string implements BadgeEnumInterface
{
    case STAGE = 'stage';
    case PROJET = 'projet';
    case NORMAL = 'normal';

    /** @deprecated */
    public function libelle(): string
    {
        return match ($this) {
            self::STAGE => 'Stage',
            self::PROJET => 'Projet',
            self::NORMAL => 'Normal',
        };
    }

    /** @deprecated */
    public function getColor(): string
    {
        return match ($this) {
            self::STAGE => 'primary',
            self::PROJET => 'info',
            self::NORMAL => 'success',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::STAGE => 'Stage',
            self::PROJET => 'Projet',
            self::NORMAL => 'Normal',
        };
    }

    public function getLibelle(): string
    {
        return match ($this) {
            self::STAGE => 'Stage',
            self::PROJET => 'Projet',
            self::NORMAL => 'Normal',
        };
    }

    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::STAGE => 'primary',
            self::PROJET => 'info',
            self::NORMAL => 'success',
        };
    }

    /**
     * @deprecated Utiliser getBadgeVariant() et le composant Twig Badge.
     */
    public function getBadge(): string
    {
        @trigger_error(sprintf('%s::getBadge() is deprecated, use %s::getBadgeVariant() and the Twig Badge component instead.', self::class, self::class), E_USER_DEPRECATED);

        return $this->getBadgeVariant();
    }
}
