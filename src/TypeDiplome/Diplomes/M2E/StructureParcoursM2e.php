<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Diplomes/M2E/StructureParcoursM2e.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/03/2026 14:59
 */

namespace App\TypeDiplome\Diplomes\M2E;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Diplomes\M2E\Services\CalculStructureParcoursM2e;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursM2e implements StructureInterface
{

    public function __construct(protected CalculStructureParcoursM2e $calculStructureParcours)
    {
    }

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return $this->calculStructureParcours->calcul($parcours, $optionsCalculStructure->withEcts, $optionsCalculStructure->withBcc, true);
    }

    public function calculVersioning(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return $this->calculStructureParcours->calculVersioning($parcours);
    }

    public function calculStructureSemestre(SemestreParcours $semestreParcours, Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureSemestre
    {
        $semestre = $semestreParcours->getSemestre() ?? new \App\Entity\Semestre();
        return new StructureSemestre($semestre, $semestreParcours->getOrdre(), false, $semestreParcours, $optionsCalculStructure->withEcts, $optionsCalculStructure->withBcc);
    }

    public function showStructure(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): array
    {
        return [];
    }
}
