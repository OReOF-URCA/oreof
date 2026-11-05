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
 * @deprecated Use referential 'niveau_langue' and App\Referentiel\NiveauLangue instead.
 */
enum NiveauLangueEnum: string
{
    case  A1 = 'A1';
    case  A2 = 'A2';
    case  B1 = 'B1';
    case  B2 = 'B2';
    case  C1 = 'C1';
    case  C2 = 'C2';

    public function libelle(): string
    {
        return match ($this) {
            self::A1 => 'A1 (Utilisateur élémentaire débutant)',
            self::A2 => 'A2 (Utilisateur élémentaire intermédiaire)',
            self::B1 => 'B1 (Utilisateur indépendant)',
            self::B2 => 'B2 (Utilisateur indépendant avancé)',
            self::C1 => 'C1 (Utilisateur expérimenté autonome)',
            self::C2 => 'C2 (Utilisateur expérimenté maîtrise)',
        };
    }
}
