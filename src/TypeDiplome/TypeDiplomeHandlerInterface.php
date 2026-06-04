<?php

namespace App\TypeDiplome;

use App\DTO\StructureParcours;
use App\Entity\Parcours;


interface TypeDiplomeHandlerInterface extends DiplomeExportInterface, McccInterface
{

    public function supports(string $type): bool;

    public function calculStructureParcours(Parcours $parcours, bool $withEcts = true, bool $withBcc = true): StructureParcours; //StructureParcoursInterface ?

    public function showStructure(Parcours $parcours): array;

    public function getStructureCompetences(Parcours $parcours);

    public function getTypeEpreuves(): array;

    public function getLibelleCourt(): string;
}
