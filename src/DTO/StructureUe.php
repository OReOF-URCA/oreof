<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/StructureUe.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 01/10/2023 08:51
 */

namespace App\DTO;

use App\Entity\Ue;

use Symfony\Component\Serializer\Attribute\Groups;

class StructureUe
{
    #[Groups(['DTO_json_versioning'])]
    public string $display = '';

    #[Groups(['DTO_json_versioning'])]
    public ?Ue $ueOrigine;

    #[Groups(['DTO_json_versioning'])]
    public ?Ue $ue;

    #[Groups(['DTO_json_versioning'])]
    public bool $raccroche = false;

    /** @var StructureEc[] $elementConstitutifs */
    #[Groups(['DTO_json_versioning'])]
    public array $elementConstitutifs = [];

    /** @var StructureUe[] $uesEnfants */
    #[Groups(['DTO_json_versioning'])]
    public array $uesEnfants = [];

    /** @var HeuresEctsUe[] $heuresEctsUeEnfants */
    #[Groups(['DTO_json_versioning'])]
    public array $heuresEctsUeEnfants = [];

    #[Groups(['DTO_json_versioning'])]
    public HeuresEctsUe $heuresEctsUe;

    private bool $withEcts;
    private bool $withBcc;

    public function __construct(?Ue $ue, bool $raccroche = false, ?string $display = null, ?Ue $ueOrigine = null, bool $withEcts = true, $withBcc = true)
    {
        $this->withEcts = $withEcts;
        $this->withBcc = $withBcc;
        $this->ue = $ue;
        $this->display = $display ?? '';
        $this->raccroche = $raccroche;
        $this->ueOrigine = $ueOrigine;

        if ($this->withEcts) {
            $this->heuresEctsUe = new HeuresEctsUe();
            if($this->ue) {
                if ($this->ue->getNatureUeEc()?->isLibre()) {
                    // Si UE lbren prise en compte des ECTS de l'UE
                    //todo: faire idem pour BUT
                    $this->heuresEctsUe->sommeUeEcts = $this->ue->getEcts() ?? 0.0;
                }
            }
        }
    }

    public function addUeEnfant(?int $idUe, StructureUe $structureUe): void
    {
        if($idUe !== null) {
            $this->uesEnfants[$idUe] = $structureUe;
            if ($this->withEcts) {
                $this->heuresEctsUeEnfants[$idUe] = $structureUe->heuresEctsUe;
            }
        } else {
            $this->uesEnfants[] = $structureUe;
            if ($this->withEcts) {
                $this->heuresEctsUeEnfants[] = $structureUe->heuresEctsUe;
            }
        }

        ksort($this->uesEnfants, SORT_NUMERIC);

        //gérer pour prendre le max des heures et ects sur tous les enfants de l'EC
    }

    public function addEc(StructureEc|StructureEcVersioning $structureEc): void
    {
        $this->elementConstitutifs[] = $structureEc;

        // todo: cette partie est à déporter dans le calcul des structures
        if ($this->withEcts) {
            $this->heuresEctsUe->addEc($structureEc->getHeuresEctsEc());
        }
    }

    public function ordre(): int
    {
        return $this->ue->getOrdre();
    }

    public function getHeuresEctsUe(): HeuresEctsUe
    {
        if (count($this->heuresEctsUeEnfants) > 0) {
            //parcourir le tableau, comparer les objets et retourner celui dont la somme des heures est la plus grande
            foreach ($this->heuresEctsUeEnfants as $heuresEctsUeEnfant) {
                $this->heuresEctsUe->sommeUeEcts = max($this->heuresEctsUe->sommeUeEcts, $heuresEctsUeEnfant->sommeUeEcts);
                if ($heuresEctsUeEnfant->sommeUeTotalPresDist() > $this->heuresEctsUe->sommeUeTotalPresDist()) {
                    $this->heuresEctsUe = $heuresEctsUeEnfant;
                }
//
//                if (($heuresEctsUeEnfant->sommeUeTotalPres() === 0 && $heuresEctsUeEnfant->sommeUeTotalDist() > 0)) {
//                    if ($heuresEctsUeEnfant->sommeUeTotalDist() > $this->heuresEctsUe->sommeUeTotalDist()) {
//                        $this->heuresEctsUe = $heuresEctsUeEnfant;
//                    }
//                }

                if ($heuresEctsUeEnfant->sommeUeTePres > $this->heuresEctsUe->sommeUeTePres) {
                    $this->heuresEctsUe->sommeUeTePres = $heuresEctsUeEnfant->sommeUeTePres;
                }
            }
        }

        return $this->heuresEctsUe;
    }

    public function uesEnfants(): array
    {
        sort($this->uesEnfants);
        return $this->uesEnfants;
    }

    public function getCodeApogee(): ?string
    {
        return $this->ueOrigine?->getCodeApogee() ?? '';
    }
}
