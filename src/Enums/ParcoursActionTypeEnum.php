<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/ParcoursActionTypeEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/12/2025 18:24
 */

// php

namespace App\Enums;

/**
 * Types d'actions possibles sur une demande de modification de parcours (ChangeParcours).
 * Mappé en base de données sur la colonne ChangeParcours::$actionType.
 */
enum ParcoursActionTypeEnum: string
{
    case MODIFY_LABEL = 'modify_label';
    case CLOSE_PARCOURS = 'close_parcours';
    case CREATE_PARCOURS = 'create_parcours';
    // ajouter d'autres types si besoin
}
