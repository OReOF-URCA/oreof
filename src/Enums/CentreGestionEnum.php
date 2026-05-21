<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/CentreGestionEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Enums;

enum CentreGestionEnum: string implements BadgeEnumInterface
{
    case CENTRE_GESTION_ETABLISSEMENT = 'cg_etablissement';
    case CENTRE_GESTION_COMPOSANTE = 'cg_composante';
    case CENTRE_GESTION_FORMATION = 'cg_formation';
    case CENTRE_GESTION_PARCOURS = 'cg_parcours';
    case CENTRE_GESTION_NULL = '';

    public function libelle(): string
    {
        return match ($this) {
            self::CENTRE_GESTION_NULL => '',
            self::CENTRE_GESTION_ETABLISSEMENT => 'Etablissement',
            self::CENTRE_GESTION_COMPOSANTE => 'Composante',
            self::CENTRE_GESTION_FORMATION => 'Formation',
            self::CENTRE_GESTION_PARCOURS => 'Parcours',
        };
    }

    public function getLibelle(): string
    {
        return match ($this) {
            self::CENTRE_GESTION_NULL => '',
            self::CENTRE_GESTION_ETABLISSEMENT => 'Etablissement',
            self::CENTRE_GESTION_COMPOSANTE => 'Composante',
            self::CENTRE_GESTION_FORMATION => 'Formation',
            self::CENTRE_GESTION_PARCOURS => 'Parcours',
        };
    }

    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::CENTRE_GESTION_NULL => 'secondary',
            self::CENTRE_GESTION_ETABLISSEMENT => 'primary',
            self::CENTRE_GESTION_COMPOSANTE => 'success',
            self::CENTRE_GESTION_FORMATION => 'warning',
            self::CENTRE_GESTION_PARCOURS => 'danger',
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

    public static function has(string $value): bool
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return true;
            }
        }
        return false;
    }
}
