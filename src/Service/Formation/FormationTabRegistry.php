<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Parcours/ParcoursTabRegistry.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/01/2026 09:12
 */

namespace App\Service\Formation;

use App\Form\FormationStep1Type;
use App\Form\FormationStep2Type;
use App\Form\FormationStep3Type;

final class FormationTabRegistry
{
    public const TABS = ['localisation', 'presentation', 'structure'];

    public static function assertTab(string $tabKey): void
    {
        if (!in_array($tabKey, self::TABS, true)) {
            throw new \InvalidArgumentException('Unknown tab: ' . $tabKey);
        }
    }

    public static function formTypeFor(string $tabKey): string
    {
        return match ($tabKey) {
            'localisation' => FormationStep1Type::class,
            'presentation' => FormationStep2Type::class,
            'structure' => FormationStep3Type::class,
            default => throw new \InvalidArgumentException('Unknown tab: ' . $tabKey),
        };
    }

    public static function validationGroupsFor(string $tabKey): array
    {
        // si tu veux des contraintes spécifiques par tab
        return ['Default', 'tab_' . $tabKey];
    }
}
