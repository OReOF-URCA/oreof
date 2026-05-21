<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/ValidationStatusEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/01/2026 06:58
 */


namespace App\Enums;

enum ValidationStatusEnum: string implements BadgeEnumInterface
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case INCOMPLETE = 'incomplete';
    case NA = 'na';

    public function label(): string
    {
        return match ($this) {
            self::VALID => 'Valide',
            self::INVALID => 'Invalide',
            self::INCOMPLETE => 'Incomplet',
            self::NA => 'Non applicable',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::VALID || $this === self::INVALID;
    }

    public function getLibelle(): string
    {
        return $this->label();
    }

    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::VALID => 'success',
            self::INVALID => 'danger',
            self::INCOMPLETE => 'warning',
            self::NA => 'secondary',
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
