<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/CalculStructureParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/10/2023 13:19
 */

namespace App\TypeDiplome\Diplomes\But\Services;

use App\DTO\HeuresEctsSemestre;
use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;

class CalculStructureParcoursBut
{
    protected array $tabSemestreEc = [];

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        $dtoStructure = StructureParcours::fromEntity($parcours);
        //$dtoStructure->setParcours($parcours);

        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            if ($semestreParcours->getSemestre()?->getSemestreRaccroche() !== null) {
                $semestre = $semestreParcours->getSemestre()?->getSemestreRaccroche()?->getSemestre();
                $raccrocheSemestre = true;
            } else {
                $semestre = $semestreParcours->getSemestre();
                $raccrocheSemestre = false;
            }

            if ($semestre !== null && $semestre->isNonDispense() === false && $semestreParcours->isOuvert() === true) {
                $dtoSemestre = new StructureSemestre($semestre, $semestreParcours->getOrdre(), $raccrocheSemestre, $semestreParcours, false);
                $dtoSemestre->heuresEctsSemestre = new HeuresEctsSemestre();
                $this->tabSemestreEc[$semestreParcours->getOrdre()] = [];

                foreach ($semestre->getUes() as $ue) {
                    if ($ue !== null && $ue->getUeParent() === null) {
                        $display = $ue->display($parcours);
                        if ($ue->getUeRaccrochee() !== null) {
                            $ueOrigine = $ue;
                            $ue = $ue->getUeRaccrochee()->getUe();
                            $raccrocheUe = true;
                        } else {
                            $raccrocheUe = $raccrocheSemestre;
                        }

                        $dtoUe = new StructureUe($ue, $raccrocheUe, $display, $ueOrigine ?? null);
                        foreach ($ue->getElementConstitutifs() as $elementConstitutif) {
                            if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                //récupérer le bon EC selon tous les liens
                                $dtoEc = new StructureEc($elementConstitutif, $parcours, true);
                                $dtoStructure->statsFichesMatieresParcours?->addEc($elementConstitutif, $raccrocheUe);
                                foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                    $dtoEcEnfant = new StructureEc($elementConstitutifEnfant, $parcours, true);
                                    $dtoStructure->statsFichesMatieresParcours?->addEc($elementConstitutifEnfant, $raccrocheUe);
                                    $dtoEc->addEcEnfant($elementConstitutifEnfant->getId(), $dtoEcEnfant);
                                }
                                $dtoUe->addEc($dtoEc);
                                $this->addEcSemestre($dtoEc, $dtoSemestre);
                            }

                        }
                        $dtoUe->heuresEctsUe->sommeUeEcts = $ue->getEcts();
                        $dtoSemestre->heuresEctsSemestre->sommeSemestreEcts += $ue->getEcts();
                        $dtoSemestre->addUe($ue->getOrdre(), $dtoUe);//todo: utilisation de l'ordre de l'ue plutôt que l'id pour la comparaison
                    }
                }
                $dtoStructure->addSemestre($semestreParcours->getOrdre(), $dtoSemestre);
            }
        }

        return $dtoStructure;
    }

    private function addEcSemestre(StructureEc $dtoEc, StructureSemestre $dtoSemestre): void
    {
        //vérifier si le code de l'EC n'est pas déjà ajouté au semestre et ajouter les heures
        if (
            array_key_exists($dtoSemestre->semestre->getOrdre(), $this->tabSemestreEc) &&
            !array_key_exists($dtoEc->elementConstitutif->getCode(), $this->tabSemestreEc[$dtoSemestre->semestre->getOrdre()])) {
            //on ajoute les heures de l'EC au semestre
            $this->tabSemestreEc[$dtoSemestre->semestre->getOrdre()][$dtoEc->elementConstitutif->getCode()] = $dtoEc->elementConstitutif->getCode();
            $dtoSemestre->heuresEctsSemestre->sommeSemestreCmDist += $dtoEc->heuresEctsEc->cmDist;
            $dtoSemestre->heuresEctsSemestre->sommeSemestreTdDist += $dtoEc->heuresEctsEc->tdDist;
            $dtoSemestre->heuresEctsSemestre->sommeSemestreTpDist += $dtoEc->heuresEctsEc->tpDist;
            $dtoSemestre->heuresEctsSemestre->sommeSemestreCmPres += $dtoEc->heuresEctsEc->cmPres;
            $dtoSemestre->heuresEctsSemestre->sommeSemestreTdPres += $dtoEc->heuresEctsEc->tdPres;
            $dtoSemestre->heuresEctsSemestre->sommeSemestreTpPres += $dtoEc->heuresEctsEc->tpPres;
            $dtoSemestre->heuresEctsSemestre->sommeSemestreTePres += $dtoEc->heuresEctsEc->tePres;
        }
    }
}
