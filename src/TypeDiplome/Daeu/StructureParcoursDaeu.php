<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Daeu/StructureParcoursDaeu.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\Daeu;

use App\DTO\StructureParcours;
use App\Entity\Parcours;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursDaeu implements StructureInterface
{

    public function calcul(Parcours $parcours, bool $withEcts = true, bool $withBcc = true, bool $dataFromFicheMatiere = false): StructureParcours
    {
        // TODO: Implement calcul() method.
    }

    public function calculVersioning(Parcours $parcours): StructureParcours
    {
        // TODO: Implement calculVersioning() method.
    }
}
