<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/TypeDiplomeMcccInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/10/2025 18:13
 */

namespace App\TypeDiplome;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;

interface TypeDiplomeMcccInterface
{
    public function checkIfMcccValide(FicheMatiere|ElementConstitutif $owner): bool;
}
