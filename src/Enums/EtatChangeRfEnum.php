<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/EtatDpeEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Enums;

enum EtatChangeRfEnum: string implements BadgeEnumInterface
{


    case demande_initialisee = 'demande_initialisee';
    case soumis_conseil = 'soumis_conseil';
    case soumis_ses = 'soumis_ses';
    case soumis_cfvu = 'soumis_cfvu';
    case attente_pv = 'attente_pv';
    case effectuee = 'effectuee';
    case verification_pv = 'verification_pv';


    public function libelle(): string
    {
        return match ($this) {
            self::demande_initialisee => 'Demande initialisée',
            self::soumis_conseil => 'Demande soumise au conseil',
            self::soumis_ses => 'Demande soumise en central',
            self::soumis_cfvu => 'Demande soumise à la CFVU',
            self::attente_pv => 'Demande validée en attente de PV',
            self::effectuee => 'Demande effectuée',
            self::verification_pv => 'PV soumis en cours de vérification',

        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::demande_initialisee => 'secondary',
            self::attente_pv => 'warning',
            self::soumis_conseil, self::soumis_ses, self::soumis_cfvu, self::verification_pv => 'info',
            self::effectuee  => 'success',
        };
    }

    public function getLibelle(): string
    {
        return $this->libelle();
    }

    public function getBadgeVariant(): string
    {
        return $this->badge();
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
