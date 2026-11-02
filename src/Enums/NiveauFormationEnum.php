<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/NiveauFormationEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Enums;

/**
 * @deprecated Use referential 'niveau_formation' and App\Referentiel\NiveauFormation instead.
 */
enum NiveauFormationEnum: int
{
    //http://lheo.gouv.fr/2.3/lheo/dict-niveaux.html#dict-niveaux
    case  NON_COMMUNIQUE = 0;
    case  SANS_NIVEAU = 1;
    //case  NIVEAU_VI = 2;
    //case  NIVEAU_V_BIS = 3;
    case  NIVEAU_V = 4;
    case  NIVEAU_IV = 5;
    case  NIVEAU_III = 6;
    case  NIVEAU_II = 7;
    case  NIVEAU_I = 8;

    case NIVEAU_1 = 11;
    case NIVEAU_2 = 12;
    case NIVEAU_3 = 13;
    case NIVEAU_4 = 14;
    case NIVEAU_5 = 15;
    case NIVEAU_6 = 16;
    case NIVEAU_7 = 17;
    case NIVEAU_8 = 18;

    public function libelle(): string
    {
        return match ($this) {
            self::NON_COMMUNIQUE => 'Information non communiquée',
            self::SANS_NIVEAU => 'Sans niveau spécifique',
            //            self::NIVEAU_V => 'niveau V (CAP, BEP, CFPA du premier degré)',
            //            self::NIVEAU_IV => 'niveau IV (BP, BT, baccalauréat ou équivalent)',
            //            self::NIVEAU_III => 'niveau III (BTS, DUT)',
            //            self::NIVEAU_II => 'niveau II (licence ou maîtrise universitaire)',
            //            self::NIVEAU_I => 'niveau I (supérieur à la maîtrise)',
            self::NIVEAU_3 => 'Niveau 3 (CAP, BEP, CFPA du premier degré)',
            self::NIVEAU_4 => 'Niveau 4 (BP, BT, baccalauréat ou équivalent)',
            self::NIVEAU_5 => 'Niveau 5 (bac +2, BTS, DUT ou équivalent)',
            self::NIVEAU_6 => 'Niveau 6 (bac +3, licence, LP, BUT ou équivalent)',
            self::NIVEAU_7 => 'Niveau 7 (bac +5, master ou équivalent)',
            self::NIVEAU_8 => 'Niveau 8 (doctorat)',
            default => 'Inconnu',
        };
    }
}
