<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Daeu/StructureParcoursDaeu.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\Diplomes\Daeu;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursDaeu implements StructureInterface
{

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return StructureParcours::fromEntity($parcours, $optionsCalculStructure->withEcts, $optionsCalculStructure->withBcc);
    }

    public function calculVersioning(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        return StructureParcours::fromEntity($parcours, $optionsCalculStructure->withEcts, $optionsCalculStructure->withBcc);
    }

    public function showStructure(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): array
    {
        return [];
    }

    public function calculStructureSemestre(SemestreParcours $semestreParcours, Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureSemestre
    {
        return new StructureSemestre();
    }
}
