<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Licence/StructureParcoursLicence.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\Diplomes\Licence;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Diplomes\Licence\Services\CalculStructureParcoursLicence;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursLicence implements StructureInterface
{

    public function __construct(protected CalculStructureParcoursLicence $calculStructureParcoursLicence)
    {
    }

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return $this->calculStructureParcoursLicence->calcul($parcours, $optionsCalculStructure);
    }

    public function calculVersioning(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return $this->calculStructureParcoursLicence->calculVersioning($parcours);
    }

    public function showStructure(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): array
    {
        return [];
    }

    public function calculStructureSemestre(SemestreParcours $semestreParcours, Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureSemestre
    {
        return $this->calculStructureParcoursLicence->calculSemestre($semestreParcours, $optionsCalculStructure, $parcours);
    }
}
