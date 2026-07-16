<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv1/src/TypeDiplome/ExportJsonInterface.php
 * @author davidannebicque
 * @project oreofv1
 * @lastUpdate 13/07/2026 10:16
 */

namespace App\TypeDiplome;

use App\DTO\StructureParcours;
use App\Entity\Parcours;

interface ExportJsonInterface
{
    public function exportJson(StructureParcours $dto, Parcours $parcours): array;
}
