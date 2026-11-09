<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Prototype/TypeDiplomeImpactEstimator.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/04/2026 16:40
 */

declare(strict_types=1);

namespace App\Service\Prototype;

use App\Entity\TypeDiplome;

final class TypeDiplomeImpactEstimator
{
    /**
     * @return array{
     *     score: int,
     *     level: string,
     *     canDelete: bool,
     *     blockers: array<int, string>,
     *     counts: array{
     *         formations: int,
     *         ficheMatieres: int,
     *         formationDemandes: int,
     *         typeEcs: int,
     *         typeUes: int,
     *         typeEpreuves: int
     *     }
     * }
     */
    public function estimate(TypeDiplome $typeDiplome): array
    {
        $counts = [
            'formations' => $typeDiplome->getFormations()->count(),
            'ficheMatieres' => $typeDiplome->getFicheMatieres()->count(),
            'formationDemandes' => $typeDiplome->getFormationDemandes()->count(),
            'typeEcs' => $typeDiplome->getTypeEcs()->count(),
            'typeUes' => $typeDiplome->getTypeUes()->count(),
            'typeEpreuves' => $typeDiplome->getTypeEpreuves()->count(),
        ];

        $score =
            ($counts['formations'] * 8)
            + ($counts['ficheMatieres'] * 5)
            + ($counts['formationDemandes'] * 4)
            + ($counts['typeEcs'] * 2)
            + ($counts['typeUes'] * 2)
            + ($counts['typeEpreuves'] * 2);

        $level = match (true) {
            $score === 0 => 'low',
            $score <= 15 => 'medium',
            default => 'high',
        };

        $blockers = [];
        if ($counts['formations'] > 0) {
            $blockers[] = 'Des formations sont encore rattachées.';
        }
        if ($counts['ficheMatieres'] > 0) {
            $blockers[] = 'Des fiches matière sont encore rattachées.';
        }
        if ($counts['formationDemandes'] > 0) {
            $blockers[] = 'Des demandes de formation sont encore rattachées.';
        }

        return [
            'score' => $score,
            'level' => $level,
            'canDelete' => $blockers === [],
            'blockers' => $blockers,
            'counts' => $counts,
        ];
    }
}

