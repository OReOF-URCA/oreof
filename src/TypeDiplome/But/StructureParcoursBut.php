<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/StructureParcoursBut.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:28
 */

namespace App\TypeDiplome\But;

use App\DTO\StructureParcours;
use App\Entity\Parcours;
use App\TypeDiplome\But\Services\CalculStructureParcoursBut;
use App\TypeDiplome\StructureInterface;

final class StructureParcoursBut implements StructureInterface
{

    public function __construct(private CalculStructureParcoursBut $calculStructureParcoursBut)
    {
    }

    public function calcul(Parcours $parcours, bool $withEcts = true, bool $withBcc = true, bool $dataFromFicheMatiere = true): StructureParcours
    {
        return $this->calculStructureParcoursBut->calcul($parcours, $withEcts, $withBcc, $dataFromFicheMatiere);
    }

    public function calculVersioning(Parcours $parcours): StructureParcours
    {
        return $this->calculStructureParcoursBut->calcul($parcours);
    }
}
