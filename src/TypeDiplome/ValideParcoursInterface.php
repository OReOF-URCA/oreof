<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/ValideParcoursInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:31
 */

namespace App\TypeDiplome;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Service\Validation\Dto\ValidationResult;

interface ValideParcoursInterface
{
    public function valideParcours(StructureParcours $structureParcours): ValidationResult;

    public function valideSemestre(StructureSemestre $structureSemestre): ValidationResult;
}
