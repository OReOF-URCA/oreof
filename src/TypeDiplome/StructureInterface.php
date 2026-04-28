<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/StructureInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:29
 */

namespace App\TypeDiplome;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;

interface StructureInterface
{
    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours;

    public function calculStructureSemestre(SemestreParcours $semestreParcours, Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureSemestre;

    public function calculVersioning(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours;

    public function showStructure(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): array;
}
