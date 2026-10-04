<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/StructureParcoursBut.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\Diplomes\But;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Diplomes\But\Services\CalculStructureParcoursBut;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursBut implements StructureInterface
{

    public function __construct(private CalculStructureParcoursBut $calculStructureParcoursBut)
    {
    }

    public function calculVersioning(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return $this->calculStructureParcoursBut->calcul($parcours, $optionsCalculStructure);
    }

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return $this->calculStructureParcoursBut->calcul($parcours, $optionsCalculStructure);
    }

    public function showStructure(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): array
    {
        return [];
    }

    public function calculStructureSemestre(SemestreParcours $semestreParcours, Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureSemestre
    {
        $semestre = $semestreParcours->getSemestre() ?? new \App\Entity\Semestre();
        return new StructureSemestre($semestre, $semestreParcours->getOrdre(), false, $semestreParcours, $optionsCalculStructure->withEcts, $optionsCalculStructure->withBcc);
    }
}
