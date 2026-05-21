<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/BadgeEnumInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 30/01/2024 09:31
 */

namespace App\Enums;

interface BadgeEnumInterface {
    public function getLibelle(): string;

    public function getBadgeVariant(): string;

    /**
     * @deprecated Utiliser getBadgeVariant() et le composant Twig Badge.
     */
    public function getBadge(): string;
}
