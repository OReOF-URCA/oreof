<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/StructureSemestre.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 01/10/2023 08:48
 */

namespace App\DTO;

use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;

use Symfony\Component\Serializer\Attribute\Groups;

class StructureSemestre
{
    #[Groups(['DTO_json_versioning'])]
    public Semestre $semestre;

    public ?SemestreParcours $calculStructureParcours;

    #[Groups(['DTO_json_versioning'])]
    public bool $raccroche = false;

    /** @var StructureUe[] $ues */
    #[Groups(['DTO_json_versioning'])]
    public array $ues = [];

    #[Groups(['DTO_json_versioning'])]
    public int $ordre;

    #[Groups(['DTO_json_versioning'])]
    public HeuresEctsSemestre $heuresEctsSemestre;

    public ?Parcours $parcoursRaccroche = null;
    public ?SemestreParcours $semestreParcours = null;
    private bool $withEcts;
    private bool $withBcc;

    public function __construct(Semestre $semestre, int $ordre, bool $raccroche = false, SemestreParcours $semestreParcours = null, bool $withEcts = true, $withBcc = true, ?Parcours $parcoursRaccroche = null)
    {
        $this->withEcts = $withEcts;
        $this->withBcc = $withBcc;
        $this->ordre = $ordre;
        $this->semestre = $semestre;
        $this->parcoursRaccroche = $parcoursRaccroche;
        $this->semestreParcours = $semestreParcours;
        $this->raccroche = $raccroche;
        if ($this->withEcts) {
            $this->heuresEctsSemestre = new HeuresEctsSemestre();
        }
    }

    public function addUe(?int $idUe, StructureUe $structureUe): void
    {
        if($idUe !== null){
            $this->ues[$idUe] = $structureUe;
        }
        else {
            $this->ues[] = $structureUe;
        }

        ksort($this->ues, SORT_NUMERIC);

        // todo: cette partie est à déporter dans le calcul des structures
        if ($this->withEcts) {
            $this->heuresEctsSemestre->addUe($structureUe->getHeuresEctsUe());
        }
    }

    public function getAnnee(): int
    {
        //si ordre = 1 ou 2 alors année = 1
        //si ordre = 3 ou 4 alors année = 2
        //si ordre = 5 ou 6 alors année = 3

        return match ($this->ordre) {
            1, 2 => 1,
            3, 4 => 2,
            5, 6 => 3,
            default => 0,
        };
    }

    // public function ues(): array
    // {
    //     sort($this->ues);
    //     return $this->ues;
    // }
    public function getUe(?int $getid): ?StructureUe
    {
        return $this->ues[$getid] ?? null;
    }
}
