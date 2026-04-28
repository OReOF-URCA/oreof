<?php

namespace App\TypeDiplome;

use App\DTO\StructureParcours;
use App\Entity\Parcours;


interface TypeDiplomeHandlerInterface extends TypeDiplomeMcccInterface, StructureInterface, McccInterface, DiplomeExportInterface
{
    public function getTypeEpreuves(): array;

    public function getTemplateFolder(): string;

    public function getValidator(): ValideParcoursInterface;
}
