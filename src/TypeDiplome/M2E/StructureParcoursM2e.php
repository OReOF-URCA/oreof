<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/M2E/StructureParcoursM2E.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\M2E;

use App\DTO\StructureParcours;
use App\Entity\Parcours;
use App\TypeDiplome\M2E\Services\CalculStructureParcoursM2e;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursM2e implements StructureInterface
{

    public function __construct(protected CalculStructureParcoursM2e $calculStructureParcours)
    {
    }

    public function calcul(Parcours $parcours, bool $withEcts = true, bool $withBcc = true, bool $dataFromFicheMatiere = false): StructureParcours
    {
        return $this->calculStructureParcours->calcul($parcours, $withEcts, $withBcc, $dataFromFicheMatiere);
    }

    public function calculVersioning(Parcours $parcours): StructureParcours
    {
        return $this->calculStructureParcours->calculVersioning($parcours);
    }
}
