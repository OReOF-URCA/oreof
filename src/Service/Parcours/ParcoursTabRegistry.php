<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Parcours/ParcoursTabRegistry.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/01/2026 09:12
 */

namespace App\Service\Parcours;

use App\Form\ParcoursStep1Type;
use App\Form\ParcoursStep2Type;
use App\Form\ParcoursStep3Type;
use App\Form\ParcoursStep5Type;
use App\Form\ParcoursStep6Type;

final class ParcoursTabRegistry
{
    public const TABS = ['presentation', 'descriptif', 'maquette', 'et_apres', 'admission'];

    public static function assertTab(string $tabKey): void
    {
        if (!in_array($tabKey, self::TABS, true)) {
            throw new \InvalidArgumentException('Unknown tab: ' . $tabKey);
        }
    }

    public static function formTypeFor(string $tabKey): string
    {
        return match ($tabKey) {
            'presentation' => ParcoursStep1Type::class,
            'descriptif' => ParcoursStep2Type::class,
            'maquette' => ParcoursStep3Type::class,
            'et_apres' => ParcoursStep6Type::class,
            'admission' => ParcoursStep5Type::class,
            default => throw new \InvalidArgumentException('Unknown tab: ' . $tabKey),
        };
    }

    public static function validationGroupsFor(string $tabKey): array
    {
        // si tu veux des contraintes spécifiques par tab
        return ['Default', 'tab_' . $tabKey];
    }
}
