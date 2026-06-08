<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/MenuDisplayModeEnum.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 16:53
 */

namespace App\Navigation;

enum MenuDisplayModeEnum: string
{
    case Dropdown = 'dropdown'; // menu + sous-items classique
    case MegaMenu = 'mega_menu'; // big-menu en colonnes
}
