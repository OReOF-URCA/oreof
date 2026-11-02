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
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;

class CalculStructureParcoursBut
{
    /** @var array<int|string, array<string, string>> */
    protected array $tabSemestreEc = [];

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        $dtoStructure = StructureParcours::fromEntity($parcours);

        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $sem = $semestreParcours->getSemestre();
            if ($sem !== null && $sem->isNonDispense() === false && $semestreParcours->isOuvert() === true) {
                $dtoSemestre = $this->calculSemestre($semestreParcours, $optionsCalculStructure, $parcours, $dtoStructure);
                $dtoStructure->addSemestre($semestreParcours->getOrdre(), $dtoSemestre);
            }
        }

        return $dtoStructure;
    }

    public function calculSemestre(
        SemestreParcours $semestreParcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure(),
        ?Parcours $parcours = null,
        ?StructureParcours $dtoStructure = null
    ): StructureSemestre {
        $parcours = $parcours ?? $semestreParcours->getParcours();
        if ($parcours === null) {
            throw new \InvalidArgumentException('Parcours cannot be null for calculSemestre');
        }

        if ($dtoStructure === null) {
            $dtoStructure = StructureParcours::fromEntity($parcours);
        }

        $sem = $semestreParcours->getSemestre();
        $semRaccroche = $sem?->getSemestreRaccroche();
        if ($semRaccroche !== null) {
            $semestre = $semRaccroche->getSemestre();
            $parcoursRaccroche = $semRaccroche->getParcours();
            $raccrocheSemestre = true;
        } else {
            $semestre = $sem;
            $raccrocheSemestre = false;
            $parcoursRaccroche = null;
        }

        $semestreObj = $semestre ?? new Semestre();
        $dtoSemestre = new StructureSemestre(
            $semestreObj,
            $semestreParcours->getOrdre(),
            $raccrocheSemestre,
            $semestreParcours,
            false,
            $optionsCalculStructure->withBcc,
            $parcoursRaccroche
        );
        $dtoSemestre->heuresEctsSemestre = new HeuresEctsSemestre();
        $this->tabSemestreEc[$semestreParcours->getOrdre()] = [];

        if ($semestre === null || $semestre->isNonDispense()) {
            return $dtoSemestre;
        }

        foreach ($semestre->getUes() as $ue) {
            if ($ue !== null && $ue->getUeParent() === null) {
                $display = $ue->display($parcours);
                $ueOrigine = $ue;
                if ($ue->getUeRaccrochee() !== null) {
                    $ue = $ue->getUeRaccrochee()->getUe();
                    $raccrocheUe = true;
                } else {
                    $raccrocheUe = $raccrocheSemestre;
                }

                $dtoUe = new StructureUe(
                    $ue,
                    $raccrocheUe,
                    $display,
                    $ueOrigine,
                    $optionsCalculStructure->withEcts,
                    $optionsCalculStructure->withBcc
                );

                foreach ($ue->getElementConstitutifs() as $elementConstitutif) {
                    if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                        $dtoEc = new StructureEc(
                            $elementConstitutif,
                            $parcours,
                            true,
                            $optionsCalculStructure->withEcts,
                            $optionsCalculStructure->withBcc,
                            $optionsCalculStructure->dataFromFicheMatiere
                        );
                        $dtoStructure->statsFichesMatieresParcours?->addEc($elementConstitutif, $raccrocheUe);

                        foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                            $dtoEcEnfant = new StructureEc(
                                $elementConstitutifEnfant,
                                $parcours,
                                true,
                                $optionsCalculStructure->withEcts,
                                $optionsCalculStructure->withBcc,
                                $optionsCalculStructure->dataFromFicheMatiere
                            );
                            $dtoStructure->statsFichesMatieresParcours?->addEc($elementConstitutifEnfant, $raccrocheUe);
                            $dtoEc->addEcEnfant($elementConstitutifEnfant->getId(), $dtoEcEnfant);
                        }

                        $dtoUe->addEc($dtoEc);
                        $this->addEcSemestre($dtoEc, $dtoSemestre);
                    }
                }

                foreach ($ue->getUeEnfants() as $ueEnfant) {
                    $displayEnfant = $ueEnfant->display($parcours);
                    $ueOrigineEnfant = $ueEnfant;
                    if ($ueEnfant !== null && $ueEnfant->getUeRaccrochee() !== null) {
                        $ueEnfant = $ueEnfant->getUeRaccrochee()->getUe();
                        $raccrocheUeEnfant = true;
                    } else {
                        $raccrocheUeEnfant = $raccrocheUe;
                    }

                    if ($ueEnfant !== null) {
                        $dtoUeEnfant = new StructureUe(
                            $ueEnfant,
                            $raccrocheUeEnfant,
                            $displayEnfant,
                            $ueOrigineEnfant,
                            $optionsCalculStructure->withEcts,
                            $optionsCalculStructure->withBcc
                        );

                        foreach ($ueEnfant->getElementConstitutifs() as $elementConstitutif) {
                            if ($elementConstitutif !== null && $elementConstitutif->getEcParent() === null) {
                                $dtoEc = new StructureEc(
                                    $elementConstitutif,
                                    $parcours,
                                    true,
                                    $optionsCalculStructure->withEcts,
                                    $optionsCalculStructure->withBcc,
                                    $optionsCalculStructure->dataFromFicheMatiere
                                );
                                $dtoStructure->statsFichesMatieresParcours?->addEc($elementConstitutif, $raccrocheUeEnfant);

                                foreach ($elementConstitutif->getEcEnfants() as $elementConstitutifEnfant) {
                                    $dtoEcEnfant = new StructureEc(
                                        $elementConstitutifEnfant,
                                        $parcours,
                                        true,
                                        $optionsCalculStructure->withEcts,
                                        $optionsCalculStructure->withBcc,
                                        $optionsCalculStructure->dataFromFicheMatiere
                                    );
                                    $dtoStructure->statsFichesMatieresParcours?->addEc($elementConstitutifEnfant, $raccrocheUeEnfant);
                                    $dtoEc->addEcEnfant($elementConstitutifEnfant->getId(), $dtoEcEnfant);
                                }

                                $dtoUeEnfant->addEc($dtoEc);
                                $this->addEcSemestre($dtoEc, $dtoSemestre);
                            }
                        }

                        $dtoUe->addUeEnfant($ueOrigineEnfant->getOrdre(), $dtoUeEnfant);
                    }
                }

                $dtoUe->heuresEctsUe->sommeUeEcts = $ue->getEcts();
                $dtoSemestre->heuresEctsSemestre->sommeSemestreEcts += $ue->getEcts();
                $dtoSemestre->addUe($ueOrigine->getOrdre(), $dtoUe);
            }
        }

        return $dtoSemestre;
    }

    private function addEcSemestre(StructureEc $dtoEc, StructureSemestre $dtoSemestre): void
    {
        $ordre = $dtoSemestre->semestre->getOrdre() ?? $dtoSemestre->ordre;
        $code = (string) ($dtoEc->elementConstitutif->getCode() ?: $dtoEc->elementConstitutif->getId());

        //vérifier si le code de l'EC n'est pas déjà ajouté au semestre et ajouter les heures
        if (
            array_key_exists($ordre, $this->tabSemestreEc) &&
            !array_key_exists($code, $this->tabSemestreEc[$ordre])) {
            //on ajoute les heures de l'EC au semestre
            $this->tabSemestreEc[$ordre][$code] = $code;
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
