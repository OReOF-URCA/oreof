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
use App\Entity\Parcours;

interface StructureInterface
{

    public function calcul(Parcours $parcours, bool $withEcts = true, bool $withBcc = true, bool $dataFromFicheMatiere = false): StructureParcours;

    public function calculVersioning(Parcours $parcours): StructureParcours;
}
