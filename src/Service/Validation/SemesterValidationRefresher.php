<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Validation/SemesterValidationRefresher.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/01/2026 07:46
 */

namespace App\Service\Validation;

use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\TypeDiplome\TypeDiplomeResolver;

readonly class SemesterValidationRefresher
{

    public function __construct(
        private TypeDiplomeResolver     $typeDiplomeResolver,
        private ValidationResultApplier $applier
    )
    {
    }

    public function refreshIfDirty(SemestreParcours $s, Parcours $parcours): void
    {
        if (!$s->getSemestre()?->isValidationDirty()) {
            return;
        }
        $this->refresh($s, $parcours);
    }

    public function refresh(SemestreParcours $s, Parcours $parcours): void
    {
        // 2) Résoudre le type diplôme
        $typeDiplome = $this->typeDiplomeResolver->fromParcours($parcours);

        // 3) Valider via le validateur du type diplôme
        $validator = $typeDiplome->getValidator();
        $sem = $typeDiplome->calculStructureSemestre($s, $parcours);
        $result = $validator->valideSemestre($sem);

        // 4) Appliquer en BDD (flags + issues)
        $this->applier->apply($s->getSemestre(), $result, $typeDiplome::class);
    }

    public function forceRefresh(SemestreParcours $semestreParcours, Parcours $parcours): void
    {
        $this->refresh($semestreParcours, $parcours);
    }
}
