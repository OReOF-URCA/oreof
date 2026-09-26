<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/TypeParcoursEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 21/12/2023 07:37
 */

namespace App\Enums;

enum TypeParcoursEnum: string
{
    case TYPE_PARCOURS_CLASSIQUE = 'classique';
    case TYPE_PARCOURS_LAS1 = 'las1';
    case TYPE_PARCOURS_LAS23 = 'las23';
    case TYPE_PARCOURS_LAS123 = 'las123';
    case TYPE_PARCOURS_PORTAIL_SANTE = 'portail_sante';
    case TYPE_PARCOURS_CPI = 'cpi';
    case TYPE_PARCOURS_ALTERNANCE = 'alternance';

    public function libelle(): string
    {
        return match ($this) {
            self::TYPE_PARCOURS_CLASSIQUE => 'Classique',
            self::TYPE_PARCOURS_PORTAIL_SANTE => 'Portail Santé',
            self::TYPE_PARCOURS_LAS1 => 'LAS1',
            self::TYPE_PARCOURS_LAS23 => 'LAS2/LAS3',
            self::TYPE_PARCOURS_LAS123 => 'LAS 1/2/3',
            self::TYPE_PARCOURS_CPI => 'CPI',
            self::TYPE_PARCOURS_ALTERNANCE => 'En alternance',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TYPE_PARCOURS_CLASSIQUE, self::TYPE_PARCOURS_LAS1, self::TYPE_PARCOURS_PORTAIL_SANTE => 'primary',
            self::TYPE_PARCOURS_LAS23, self::TYPE_PARCOURS_CPI, self::TYPE_PARCOURS_ALTERNANCE => 'info',
            self::TYPE_PARCOURS_LAS123 => 'primary'
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::TYPE_PARCOURS_CLASSIQUE => 'Classique',
            self::TYPE_PARCOURS_LAS1, self::TYPE_PARCOURS_LAS23, self::TYPE_PARCOURS_LAS123 => 'Accès santé',
            self::TYPE_PARCOURS_PORTAIL_SANTE => 'Portail Santé',
            self::TYPE_PARCOURS_CPI => 'CPI',
            self::TYPE_PARCOURS_ALTERNANCE => 'En alternance',
        };
    }
}
