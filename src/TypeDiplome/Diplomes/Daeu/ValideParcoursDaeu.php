<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/ValideParcoursBut.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:30
 */

namespace App\TypeDiplome\Diplomes\Daeu;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\Ue;
use App\Service\Validation\Dto\ValidationResult;
use App\TypeDiplome\ValideParcoursInterface;

class ValideParcoursDaeu implements ValideParcoursInterface
{

    public function valideSemestre(StructureSemestre $structureSemestre): ValidationResult
    {
        return new ValidationResult();
    }

    public function valideUe(Ue $ue): ValidationResult
    {
        return new ValidationResult();
    }

    public function valideParcours(StructureParcours $structureParcours): ValidationResult
    {
        return new ValidationResult();
    }
}
