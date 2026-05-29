<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/RessourceEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Enums;

enum RessourceEnum: string
{
    case app_parcours = 'app_parcours';
    case app_formation = 'app_formation';
    case app_composante = 'app_composante';
    case app_etablissement = 'app_etablissement';
    case app_fiche_matiere = 'app_fiche_matiere';
    case app_fiche_matiere_hd = 'app_fiche_matiere_hd';
    case app_ec = 'app_ec';

    public static function getRessources(): array
    {
        $ressources = [];
        foreach (self::cases() as $ressource) {
            $ressources[$ressource->value] = $ressource->name;
        }
        asort($ressources);

        return $ressources;
    }
}
