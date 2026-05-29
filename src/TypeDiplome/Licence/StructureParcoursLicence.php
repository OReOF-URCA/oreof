<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Licence/StructureParcoursLicence.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\Licence;

use App\DTO\StructureParcours;
use App\Entity\Parcours;
use App\TypeDiplome\Licence\Services\CalculStructureParcoursLicence;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursLicence implements StructureInterface
{

    public function __construct(protected CalculStructureParcoursLicence $calculStructureParcoursLicence)
    {
    }

    public function calcul(Parcours $parcours, bool $withEcts = true, bool $withBcc = true, bool $dataFromFicheMatiere = false): StructureParcours
    {
        return $this->calculStructureParcoursLicence->calcul($parcours, $withEcts, $withBcc, $dataFromFicheMatiere);
    }

    public function calculVersioning(Parcours $parcours): StructureParcours
    {
        return $this->calculStructureParcoursLicence->calculVersioning($parcours);
    }
}
