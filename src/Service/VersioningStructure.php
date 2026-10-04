<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/VersioningStructure.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/05/2024 07:26
 */

namespace App\Service;

use App\DTO\DiffObject;
use App\DTO\HeuresEctsEc;
use App\DTO\HeuresEctsFormation;
use App\DTO\HeuresEctsSemestre;
use App\DTO\HeuresEctsUe;
use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Utils\Tools;
use DateTime;
use Doctrine\Common\Collections\Collection;

class VersioningStructure
{
    private bool $isBut = false;
    private ?StructureParcours $dtoOrigine = null;
    private StructureParcours $dtoNouveau;

    public function __construct(
    ) {
    }

    public function setDto(StructureParcours $dtoOrigine, StructureParcours $dtoNouveau): self
    {
        //$versioning = new VersioningStructure();
        $this->dtoOrigine = $dtoOrigine;
        $this->dtoNouveau = $dtoNouveau;
        return $this;

    }

    public function calculDiff(bool $isBut = false): array
    {
        $this->isBut = $isBut;
        // parcourir les deux structures et comparer. Construire un tableau de différences
        $diff = [];
        foreach ($this->dtoOrigine->semestres as $ordreSemestre => $semestre) {
            if (array_key_exists($ordreSemestre, $this->dtoNouveau->semestres)) {
                $diff['semestres'][$ordreSemestre] = $this->compareSemestre($semestre, $this->dtoNouveau->semestres[$ordreSemestre]);
            } else {
                //donc n'existe plus ?
                $diff['semestres'][$ordreSemestre] = $this->compareSemestre($semestre, null);
            }
        }

        foreach ($this->dtoNouveau->semestres as $ordreSemestre => $semestre) {
            //si pas dans l'original, donc ajouté
            if (!array_key_exists($ordreSemestre, $this->dtoOrigine->semestres)) {
                $diff['semestres'][$ordreSemestre] = $this->compareSemestre(null, $semestre);
            }
        }

        $diff['heuresEctsFormation'] = $this->compareHeuresEctsFormation($this->dtoOrigine->heuresEctsFormation, $this->dtoNouveau->heuresEctsFormation);

        return $diff;
    }

    private function compareSemestre(?StructureSemestre $semestreOriginal, ?StructureSemestre $semestreNouveau): array
    {
        $diff = [];

        if ($semestreNouveau !== null && $semestreOriginal !== null) {
            $diff['raccroche'] = new DiffObject($semestreOriginal->raccroche, $semestreNouveau->raccroche);
            $diff['ordre'] = new DiffObject($semestreOriginal->ordre, $semestreNouveau->ordre);
            $diff['heuresEctsSemestre'] = $this->compareHeuresEctsSemestre($semestreOriginal->heuresEctsSemestre, $semestreNouveau->heuresEctsSemestre);
            foreach ($semestreOriginal->ues as $ordreUe => $ue) {
                if (!array_key_exists($ordreUe, $semestreNouveau->ues)) {
                    //donc n'existe plus ?
                    $diff['ues'][$ordreUe] = $this->compareUe($ue, null);
                } else {
                    $diff['ues'][$ordreUe] = $this->compareUe($ue, $semestreNouveau->ues[$ordreUe]);//cas si UE n'existe plus ou si ajouté dans nouveau ?
                }
            }
            foreach ($semestreNouveau->ues as $ordreUe => $ue) {
                if (!array_key_exists($ordreUe, $semestreOriginal->ues)) {
                    //donc n'existe plus ?
                    $diff['ues'][$ordreUe] = $this->compareUe(null, $ue);
                }
            }

            if (array_key_exists('ues', $diff) && count($diff['ues']) > 0) {
                ksort($diff['ues']);
            }
        } elseif ($semestreNouveau !== null) {
            $diff['raccroche'] = new DiffObject(null, $semestreNouveau->raccroche);
            $diff['ordre'] = new DiffObject(null, $semestreNouveau->ordre);
            $diff['heuresEctsSemestre'] = $this->compareHeuresEctsSemestre(null, $semestreNouveau->heuresEctsSemestre);
            foreach ($semestreNouveau->ues as $ordreUe => $ue) {
                //donc n'existe plus ?
                $diff['ues'][$ordreUe] = $this->compareUe(null, $ue);

            }
        } elseif ($semestreOriginal !== null) {
            $diff['raccroche'] = new DiffObject($semestreOriginal->raccroche, null);
            $diff['ordre'] = new DiffObject($semestreOriginal->ordre, null);
            $diff['heuresEctsSemestre'] = $this->compareHeuresEctsSemestre($semestreOriginal->heuresEctsSemestre, null);
            foreach ($semestreOriginal->ues as $ordreUe => $ue) {
                //donc n'existe plus ?
                $diff['ues'][$ordreUe] = $this->compareUe($ue, null);

            }
        }

        return $diff;
    }

    private function compareHeuresEctsSemestre(?HeuresEctsSemestre $heuresEctsSemestre, ?HeuresEctsSemestre $heuresEctsSemestreNouveau): array
    {
        $diff = [];
        if ($heuresEctsSemestre !== null && $heuresEctsSemestreNouveau !== null) {
            $diff['sommeSemestreEcts'] = new DiffObject($heuresEctsSemestre->sommeSemestreEcts, $heuresEctsSemestreNouveau->sommeSemestreEcts);
            $diff['sommeSemestreCmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreCmPres), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreCmPres));
            $diff['sommeSemestreTdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTdPres), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTdPres));
            $diff['sommeSemestreTpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTpPres), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTpPres));
            $diff['sommeSemestreTePres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTePres), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTePres));
            $diff['sommeSemestreCmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreCmDist), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreCmDist));
            $diff['sommeSemestreTdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTdDist), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTdDist));
            $diff['sommeSemestreTpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTpDist), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTpDist));

            $sommeSemestreTotalPres = $heuresEctsSemestre->sommeSemestreCmPres + $heuresEctsSemestre->sommeSemestreTdPres + $heuresEctsSemestre->sommeSemestreTpPres;
            $diff['sommeSemestreTotalPres'] = new DiffObject(Tools::filtreHeures($sommeSemestreTotalPres), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTotalPres()));

            $sommeSemestreTotalDist = $heuresEctsSemestre->sommeSemestreCmDist + $heuresEctsSemestre->sommeSemestreTdDist + $heuresEctsSemestre->sommeSemestreTpDist;
            $diff['sommeSemestreTotalDist'] = new DiffObject(Tools::filtreHeures($sommeSemestreTotalDist), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTotalDist()));

            $sommeSemestreTotalPresDist = $sommeSemestreTotalPres + $sommeSemestreTotalDist;
            $diff['sommeSemestreTotalPresDist'] = new DiffObject(Tools::filtreHeures($sommeSemestreTotalPresDist), Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTotalPresDist()));
        } elseif ($heuresEctsSemestre !== null) {
            $diff['sommeSemestreEcts'] = new DiffObject($heuresEctsSemestre->sommeSemestreEcts, null);
            $diff['sommeSemestreCmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreCmPres), null);
            $diff['sommeSemestreTdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTdPres), null);
            $diff['sommeSemestreTpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTpPres), null);
            $diff['sommeSemestreTePres'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTePres), null);
            $diff['sommeSemestreCmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreCmDist), null);
            $diff['sommeSemestreTdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTdDist), null);
            $diff['sommeSemestreTpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsSemestre->sommeSemestreTpDist), null);

            $sommeSemestreTotalPres = $heuresEctsSemestre->sommeSemestreCmPres + $heuresEctsSemestre->sommeSemestreTdPres + $heuresEctsSemestre->sommeSemestreTpPres;
            $diff['sommeSemestreTotalPres'] = new DiffObject(Tools::filtreHeures($sommeSemestreTotalPres), null);

            $sommeSemestreTotalDist = $heuresEctsSemestre->sommeSemestreCmDist + $heuresEctsSemestre->sommeSemestreTdDist + $heuresEctsSemestre->sommeSemestreTpDist;
            $diff['sommeSemestreTotalDist'] = new DiffObject(Tools::filtreHeures($sommeSemestreTotalDist), null);

            $sommeSemestreTotalPresDist = $sommeSemestreTotalPres + $sommeSemestreTotalDist;
            $diff['sommeSemestreTotalPresDist'] = new DiffObject(Tools::filtreHeures($sommeSemestreTotalPresDist), null);
        } elseif ($heuresEctsSemestreNouveau !== null) {
            $diff['sommeSemestreEcts'] = new DiffObject(null, $heuresEctsSemestreNouveau->sommeSemestreEcts);
            $diff['sommeSemestreCmPres'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreCmPres));
            $diff['sommeSemestreTdPres'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTdPres));
            $diff['sommeSemestreTpPres'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTpPres));
            $diff['sommeSemestreTePres'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTePres));
            $diff['sommeSemestreCmDist'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreCmDist));
            $diff['sommeSemestreTdDist'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTdDist));
            $diff['sommeSemestreTpDist'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTpDist));

            $diff['sommeSemestreTotalPres'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTotalPres()));

            $diff['sommeSemestreTotalDist'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTotalDist()));

            $diff['sommeSemestreTotalPresDist'] = new DiffObject(null, Tools::filtreHeures($heuresEctsSemestreNouveau->sommeSemestreTotalPresDist()));
        }

        return $diff;
    }

    private function compareUe(?StructureUe $ueOriginale, ?StructureUe $ueNouvelle): array
    {
        $diff = [];

        if ($ueNouvelle !== null && $ueOriginale !== null) {
            $diff['display'] = new DiffObject($ueOriginale->display, $ueNouvelle->display);
            $diff['libelle'] = new DiffObject($ueOriginale->ue->getLibelle(), $ueNouvelle->ue->getLibelle());
            $diff['raccroche'] = new DiffObject($ueOriginale->raccroche, $ueNouvelle->raccroche);
            foreach ($ueOriginale->elementConstitutifs as $ordreEc => $ec) {
                if (!array_key_exists($ordreEc, $ueNouvelle->elementConstitutifs)) {
                    //donc n'existe plus ?
                    $diff['elementConstitutifs'][$ordreEc] = $this->compareElementConstitutif($ec, null);
                } else {
                    $diff['elementConstitutifs'][$ordreEc] = $this->compareElementConstitutif($ec, $ueNouvelle->elementConstitutifs[$ordreEc]);
                }
            }

            foreach ($ueNouvelle->elementConstitutifs as $ordreEc => $ec) {
                if (!array_key_exists($ordreEc, $ueOriginale->elementConstitutifs)) {
                    //donc nouvel EC
                    $diff['elementConstitutifs'][$ordreEc] = $this->compareElementConstitutif(null, $ec);
                }
            }

            foreach ($ueOriginale->uesEnfants() as $ordreUeEnfant => $ueEnfant) {
                if (array_key_exists($ordreUeEnfant, $ueNouvelle->uesEnfants())) {
                    $diff['uesEnfants'][$ordreUeEnfant] = $this->compareUe($ueEnfant, $ueNouvelle->uesEnfants()[$ordreUeEnfant]);
                } else {
                    $diff['uesEnfants'][$ordreUeEnfant] = $this->compareUe($ueEnfant, null);
                }
            }

            foreach ($ueNouvelle->uesEnfants() as $ordreUeEnfant => $ueEnfant) {
                //UE n'existait pas avant, on ajoute
                if (!array_key_exists($ordreUeEnfant, $ueOriginale->uesEnfants())) {
                    $diff['uesEnfants'][$ordreUeEnfant] = $this->compareUe(null, $ueNouvelle->uesEnfants()[$ordreUeEnfant]);
                }
            }

            $diff['heuresEctsUe'] = $this->compareHeuresEctsUe($ueOriginale->heuresEctsUe, $ueNouvelle->heuresEctsUe);
        } elseif ($ueOriginale !== null && $ueNouvelle === null) {
            $diff['display'] = new DiffObject($ueOriginale->display, '-');
            $diff['libelle'] = new DiffObject($ueOriginale->ue->getLibelle(), '-');
            $diff['raccroche'] = new DiffObject($ueOriginale->raccroche, '-');
            foreach ($ueOriginale->elementConstitutifs as $ordreEc => $ec) {
                $diff['elementConstitutifs'][$ordreEc] = $this->compareElementConstitutif($ec, null);
            }

            foreach ($ueOriginale->uesEnfants() as $ordreUeEnfant => $ueEnfant) {
                $diff['uesEnfants'][$ordreUeEnfant] = $this->compareUe($ueEnfant, null);
            }

            $diff['heuresEctsUe'] = $this->compareHeuresEctsUe($ueOriginale->heuresEctsUe, null);
        } else {
            $diff['display'] = new DiffObject('-', $ueNouvelle->display);
            $diff['libelle'] = new DiffObject('-', $ueNouvelle->ue->getLibelle());
            $diff['raccroche'] = new DiffObject('-', $ueNouvelle->raccroche);

            foreach ($ueNouvelle->elementConstitutifs as $ordreEc => $ec) {
                //donc nouvel EC
                $diff['elementConstitutifs'][$ordreEc] = $this->compareElementConstitutif(null, $ec);

            }

            foreach ($ueNouvelle->uesEnfants() as $ordreUeEnfant => $ueEnfant) {
                $diff['uesEnfants'][$ordreUeEnfant] = $this->compareUe(null, $ueEnfant);
            }

            $diff['heuresEctsUe'] = $this->compareHeuresEctsUe(null, $ueNouvelle->heuresEctsUe);
        }

        return $diff;
    }

    private function compareElementConstitutif(?StructureEc $ecOriginal, ?StructureEc $ecNouveau): array
    {
        $diff = [];
        if ($ecOriginal === null && $ecNouveau !== null) {
            if ($ecNouveau->elementConstitutif->getFicheMatiere() !== null) {
                $libelleNew = $ecNouveau->elementConstitutif->getFicheMatiere()->getLibelle();
            } else {
                $libelleNew = $ecNouveau->elementConstitutif->getLibelle();
            }

            $diff['libelle'] = new DiffObject('-', $libelleNew);
            $diff['code'] = new DiffObject('-', $ecNouveau->elementConstitutif->getCode());
            $diff['raccroche'] = new DiffObject(null, $ecNouveau->raccroche);
            $diff['heuresEctsEc'] = $this->compareHeuresEctsEc(null, $ecNouveau->heuresEctsEc);
            $diff['typeMccc'] = new DiffObject('', $ecNouveau->typeMccc);
            $diff['quitus'] = new DiffObject('', $ecNouveau->elementConstitutif->getHasQuitus());
            $diff['mcccs'] = $this->compareMcccs([], $ecNouveau->mcccs);

            //EC enfants
            foreach ($ecNouveau->elementsConstitutifsEnfants as $ordreEc => $ecEnfant) {
                $diff['ecEnfants'][$ordreEc] = $this->compareElementConstitutif(null, $ecEnfant);
            }


            return $diff;
        }

        if ($ecOriginal !== null && $ecNouveau === null) {
            if ($ecOriginal->elementConstitutif->getFicheMatiere() !== null) {
                $libelleNew = $ecOriginal->elementConstitutif->getFicheMatiere()->getLibelle();
            } else {
                $libelleNew = $ecOriginal->elementConstitutif->getLibelle();
            }

            $diff['libelle'] = new DiffObject($libelleNew, '-');
            $diff['code'] = new DiffObject($ecOriginal->elementConstitutif->getCode(), '');
            $diff['raccroche'] = new DiffObject($ecOriginal->raccroche, null);
            $diff['heuresEctsEc'] = $this->compareHeuresEctsEc($ecOriginal->heuresEctsEc, null);
            $diff['typeMccc'] = new DiffObject($ecOriginal->typeMccc, '-');
            $diff['mcccs'] = $this->compareMcccs($ecOriginal->mcccs, []);

            // todo: gérer si un EC est ajouté avec des enfants           if ($ecNouveau->elementConstitutif->getNatureUeEc()?->isChoix()) {
            //                //EC enfants
            //                foreach ($ecNouveau->elementsConstitutifsEnfants as $ordreEc => $ecEnfant) {
            //                    if (array_key_exists($ordreEc, $ecOriginal->elementsConstitutifsEnfants)) {
            //                        $diff['ecEnfants'][$ordreEc] = $this->compareElementConstitutif($ecOriginal->elementsConstitutifsEnfants[$ordreEc], $ecEnfant);
            //                    } //else supprimé
            //                }
            //            }

            return $diff;
        }

        if ($ecOriginal->elementConstitutif->getFicheMatiere() !== null) {
            $libelleOriginal = $ecOriginal->elementConstitutif->getFicheMatiere()->getLibelle();
        } else {
            $libelleOriginal = $ecOriginal->elementConstitutif->getLibelle();
        }

        if ($ecNouveau->elementConstitutif->getFicheMatiere() !== null) {
            $libelleNew = $ecNouveau->elementConstitutif->getFicheMatiere()->getLibelle();
        } else {
            $libelleNew = $ecNouveau->elementConstitutif->getLibelle();
        }

        $diff['libelle'] = new DiffObject($libelleOriginal, $libelleNew);
        $diff['code'] = new DiffObject($ecOriginal->elementConstitutif->getCode(), $ecNouveau->elementConstitutif->getCode());
        $diff['raccroche'] = new DiffObject($ecOriginal->raccroche, $ecNouveau->raccroche);
        $diff['heuresEctsEc'] = $this->compareHeuresEctsEc($ecOriginal->heuresEctsEc, $ecNouveau->heuresEctsEc);

        if ($this->isBut) {
            if ($ecOriginal === null && $ecNouveau !== null) {
                $diff['mcccs'] = $this->compareMcccsBUT(null, $ecNouveau->elementConstitutif->getFicheMatiere()->getMcccs());
                $diff['ects'] = new DiffObject(null, $ecNouveau->elementConstitutif->getEcts());
            } elseif ($ecOriginal !== null && $ecNouveau === null) {
                $diff['mcccs'] = $this->compareMcccsBUT($ecOriginal->elementConstitutif->getFicheMatiere()->getMcccs(), null);
                $diff['ects'] = new DiffObject($ecOriginal->heuresEctsEc->ects, null);
            } else {
                $diff['mcccs'] = $this->compareMcccsBUT($ecOriginal->elementConstitutif->getFicheMatiere()->getMcccs(), $ecNouveau->elementConstitutif->getFicheMatiere()->getMcccs());
                $diff['ects'] = new DiffObject($ecOriginal->heuresEctsEc->ects, $ecNouveau->elementConstitutif->getEcts());
            }

        } else {
            if ($ecOriginal->typeMccc !== null && $ecNouveau->typeMccc !== null) {
                $diff['typeMccc'] = new DiffObject($ecOriginal->typeMccc, $ecNouveau->typeMccc);
                $diff['quitus'] = new DiffObject($ecOriginal->elementConstitutif->getHasQuitus(), $ecNouveau->elementConstitutif->getHasQuitus());
                $diff['mcccs'] = $this->compareMcccs($ecOriginal->mcccs, $ecNouveau->mcccs);
            } elseif (($ecOriginal->typeMccc === null && $ecNouveau->typeMccc !== null)) {
                $diff['typeMccc'] = new DiffObject(null, $ecNouveau->typeMccc);
                $diff['quitus'] = new DiffObject(null, $ecNouveau->elementConstitutif->getHasQuitus());
                $diff['mcccs'] = $this->compareMcccs(null, $ecNouveau->mcccs);
            }

        }

        if ($ecNouveau->elementConstitutif->getNatureUeEc()?->isChoix()) {
            //EC enfants
            foreach ($ecNouveau->elementsConstitutifsEnfants as $ordreEc => $ecEnfant) {
                if (array_key_exists($ordreEc, $ecOriginal->elementsConstitutifsEnfants)) {
                    $diff['ecEnfants'][$ordreEc] = $this->compareElementConstitutif($ecOriginal->elementsConstitutifsEnfants[$ordreEc], $ecEnfant);
                } else {
                    //création
                    $diff['ecEnfants'][$ordreEc] = $this->compareElementConstitutif(null, $ecEnfant);
                }
            }
        }

        return $diff;
    }

    private function compareHeuresEctsUe(?HeuresEctsUe $heuresEctsUe, ?HeuresEctsUe $heuresEctsUe1): array
    {

        $diff = [];
        if ($heuresEctsUe1 !== null && $heuresEctsUe !== null) {
            $diff['sommeUeEcts'] = new DiffObject($heuresEctsUe->sommeUeEcts, $heuresEctsUe1->sommeUeEcts);
            $diff['sommeUeCmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeCmPres), Tools::filtreHeures($heuresEctsUe1->sommeUeCmPres));
            $diff['sommeUeTdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTdPres), Tools::filtreHeures($heuresEctsUe1->sommeUeTdPres));
            $diff['sommeUeTpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTpPres), Tools::filtreHeures($heuresEctsUe1->sommeUeTpPres));
            $diff['sommeUeTePres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTePres), Tools::filtreHeures($heuresEctsUe1->sommeUeTePres));
            $diff['sommeUeCmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeCmDist), Tools::filtreHeures($heuresEctsUe1->sommeUeCmDist));
            $diff['sommeUeTdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTdDist), Tools::filtreHeures($heuresEctsUe1->sommeUeTdDist));
            $diff['sommeUeTpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTpDist), Tools::filtreHeures($heuresEctsUe1->sommeUeTpDist));

            $sommeUeTotalPres = $heuresEctsUe->sommeUeCmPres + $heuresEctsUe->sommeUeTdPres + $heuresEctsUe->sommeUeTpPres;
            $diff['sommeUeTotalPres'] = new DiffObject(Tools::filtreHeures($sommeUeTotalPres), Tools::filtreHeures($heuresEctsUe1->sommeUeTotalPres()));

            $sommeUeTotalDist = $heuresEctsUe->sommeUeCmDist + $heuresEctsUe->sommeUeTdDist + $heuresEctsUe->sommeUeTpDist;
            $diff['sommeUeTotalDist'] = new DiffObject(Tools::filtreHeures($sommeUeTotalDist), Tools::filtreHeures($heuresEctsUe1->sommeUeTotalDist()));

            $sommeUeTotalPresDist = $sommeUeTotalPres + $sommeUeTotalDist;
            $diff['sommeUeTotalPresDist'] = new DiffObject(Tools::filtreHeures($sommeUeTotalPresDist), Tools::filtreHeures($heuresEctsUe1->sommeUeTotalPresDist()));
        } elseif ($heuresEctsUe !== null && $heuresEctsUe1 === null) {
            $diff['sommeUeEcts'] = new DiffObject($heuresEctsUe->sommeUeEcts, 0);
            $diff['sommeUeCmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeCmPres), 0);
            $diff['sommeUeTdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTdPres), 0);
            $diff['sommeUeTpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTpPres), 0);
            $diff['sommeUeTePres'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTePres), 0);
            $diff['sommeUeCmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeCmDist), 0);
            $diff['sommeUeTdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTdDist), 0);
            $diff['sommeUeTpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsUe->sommeUeTpDist), 0);

            $sommeUeTotalPres = $heuresEctsUe->sommeUeCmPres + $heuresEctsUe->sommeUeTdPres + $heuresEctsUe->sommeUeTpPres;
            $diff['sommeUeTotalPres'] = new DiffObject(Tools::filtreHeures($sommeUeTotalPres), 0);

            $sommeUeTotalDist = $heuresEctsUe->sommeUeCmDist + $heuresEctsUe->sommeUeTdDist + $heuresEctsUe->sommeUeTpDist;
            $diff['sommeUeTotalDist'] = new DiffObject(Tools::filtreHeures($sommeUeTotalDist), 0);

            $sommeUeTotalPresDist = $sommeUeTotalPres + $sommeUeTotalDist;
            $diff['sommeUeTotalPresDist'] = new DiffObject(Tools::filtreHeures($sommeUeTotalPresDist), 0);
        } else {
            $diff['sommeUeEcts'] = new DiffObject(0, $heuresEctsUe1->sommeUeEcts);
            $diff['sommeUeCmPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeCmPres));
            $diff['sommeUeTdPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTdPres));
            $diff['sommeUeTpPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTpPres));
            $diff['sommeUeTePres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTePres));
            $diff['sommeUeCmDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeCmDist));
            $diff['sommeUeTdDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTdDist));
            $diff['sommeUeTpDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTpDist));

            $diff['sommeUeTotalPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTotalPres()));
            $diff['sommeUeTotalDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTotalDist()));
            $diff['sommeUeTotalPresDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsUe1->sommeUeTotalPresDist()));
        }

        return $diff;
    }

    private function compareHeuresEctsEc(?HeuresEctsEc $heuresEctsEc, ?HeuresEctsEc $heuresEctsEc1): array
    {
        $diff = [];

        if ($heuresEctsEc === null && $heuresEctsEc1 !== null) {
            $diff['ects'] = new DiffObject(0, $heuresEctsEc1->ects);
            $diff['cmPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->cmPres));
            $diff['tdPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->tdPres));
            $diff['tpPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->tpPres));
            $diff['tePres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->tePres));
            $diff['cmDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->cmDist));
            $diff['tdDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->tdDist));
            $diff['tpDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->tpDist));

            $diff['sommeEcTotalPres'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->sommeEcTotalPres()));
            $diff['sommeEcTotalDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->sommeEcTotalDist()));
            $diff['sommeEcTotalPresDist'] = new DiffObject(0, Tools::filtreHeures($heuresEctsEc1->sommeEcTotalPresDist()));
        } elseif ($heuresEctsEc !== null && $heuresEctsEc1 === null) {
            $diff['ects'] = new DiffObject($heuresEctsEc->ects, 0);
            $diff['cmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->cmPres), 0);
            $diff['tdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tdPres), 0);
            $diff['tpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tpPres), 0);
            $diff['tePres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tePres), 0);
            $diff['cmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->cmDist), 0);
            $diff['tdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tdDist), 0);
            $diff['tpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tpDist), 0);


            $diff['sommeEcTotalPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->sommeEcTotalPres()), 0);
            $diff['sommeEcTotalDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->sommeEcTotalDist()), 0);
            $diff['sommeEcTotalPresDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->sommeEcTotalPresDist()), 0);
        } else {
            $diff['ects'] = new DiffObject($heuresEctsEc->ects, $heuresEctsEc1->ects);
            $diff['cmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->cmPres), Tools::filtreHeures($heuresEctsEc1->cmPres));
            $diff['tdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tdPres), Tools::filtreHeures($heuresEctsEc1->tdPres));
            $diff['tpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tpPres), Tools::filtreHeures($heuresEctsEc1->tpPres));
            $diff['tePres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tePres), Tools::filtreHeures($heuresEctsEc1->tePres));
            $diff['cmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->cmDist), Tools::filtreHeures($heuresEctsEc1->cmDist));
            $diff['tdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tdDist), Tools::filtreHeures($heuresEctsEc1->tdDist));
            $diff['tpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->tpDist), Tools::filtreHeures($heuresEctsEc1->tpDist));


            $diff['sommeEcTotalPres'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->sommeEcTotalPres()), Tools::filtreHeures($heuresEctsEc1->sommeEcTotalPres()));
            $diff['sommeEcTotalDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->sommeEcTotalDist()), Tools::filtreHeures($heuresEctsEc1->sommeEcTotalDist()));
            $diff['sommeEcTotalPresDist'] = new DiffObject(Tools::filtreHeures($heuresEctsEc->sommeEcTotalPresDist()), Tools::filtreHeures($heuresEctsEc1->sommeEcTotalPresDist()));


        }
        return $diff;
    }

    private function compareHeuresEctsFormation(?HeuresEctsFormation $heuresEctsFormation, ?HeuresEctsFormation $heuresEctsFormationNouveau): array
    {
        $diff = [];
        if ($heuresEctsFormation === null || $heuresEctsFormationNouveau === null) {
            return $diff;
        }

        $diff['sommeFormationEcts'] = new DiffObject($heuresEctsFormation->sommeFormationEcts, $heuresEctsFormationNouveau->sommeFormationEcts);
        $diff['sommeFormationCmPres'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationCmPres), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationCmPres));
        $diff['sommeFormationTdPres'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationTdPres), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTdPres));
        $diff['sommeFormationTpPres'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationTpPres), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTpPres));
        $diff['sommeFormationTePres'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationTePres), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTePres));
        $diff['sommeFormationCmDist'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationCmDist), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationCmDist));
        $diff['sommeFormationTdDist'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationTdDist), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTdDist));
        $diff['sommeFormationTpDist'] = new DiffObject(Tools::filtreHeures($heuresEctsFormation->sommeFormationTpDist), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTpDist));

        $sommeFormationTotalPres = $heuresEctsFormation->sommeFormationCmPres + $heuresEctsFormation->sommeFormationTdPres + $heuresEctsFormation->sommeFormationTpPres;
        $diff['sommeFormationTotalPres'] = new DiffObject(Tools::filtreHeures($sommeFormationTotalPres), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTotalPres()));

        $sommeFormationTotalDist = $heuresEctsFormation->sommeFormationCmDist + $heuresEctsFormation->sommeFormationTdDist + $heuresEctsFormation->sommeFormationTpDist;
        $diff['sommeFormationTotalDist'] = new DiffObject(Tools::filtreHeures($sommeFormationTotalDist), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTotalDist()));

        $sommeFormationTotalPresDist = $sommeFormationTotalDist + $sommeFormationTotalPres;
        $diff['sommeFormationTotalPresDist'] = new DiffObject(Tools::filtreHeures($sommeFormationTotalPresDist), Tools::filtreHeures($heuresEctsFormationNouveau->sommeFormationTotalPresDist()));

        return $diff;
    }

    public function mapStructureForComparison(StructureParcours $dto): array
    {
        $structure = ['semestres' => []];
        // Semestres
        foreach($dto->semestres as $indexS => $semestre) {
            $structure['semestres'][$indexS] = ['idSemestre' => $semestre->semestre->getId(), 'ues' => []];
            $ueArray = $semestre->ues;
            $structure['semestres'][$indexS]['ues'] = $this->mapUeArrayForComparison($ueArray);
            foreach($ueArray as $indexUe => $ue) {
                $ecArray = $ue->elementConstitutifs;
                $structure['semestres'][$indexS]['ues'][$indexUe]['listeEc'] = $this->mapEcArrayForComparison($ecArray);
                foreach($ecArray as $indexEc => $ec) {
                    $ecEnfantData = $ec->elementsConstitutifsEnfants;
                    $structure['semestres'][$indexS]['ues'][$indexUe]['listeEc'][$indexEc]['listeEcsEnfants'] = $this->mapEcArrayForComparison($ecEnfantData);
                }
                $ueEnfantArrayData = $ue->uesEnfants();
                $structure['semestres'][$indexS]['ues'][$indexUe]['uesEnfants'] = $this->mapUeArrayForComparison($ueEnfantArrayData);
                foreach($ueEnfantArrayData as $indexUeEnfant => $ueEnfant) {
                    $ecArrayData = $ueEnfant->elementConstitutifs;
                    $structure['semestres'][$indexS]['ues'][$indexUe]['uesEnfants'][$indexUeEnfant]['listeEc'] = $this->mapEcArrayForComparison($ecArrayData);
                    foreach($ecArrayData as $indexEcEnfantUeEnfant => $ec) {
                        $ecEnfantData = $ec->elementsConstitutifsEnfants;
                        $structure['semestres'][$indexS]['ues'][$indexUe]['uesEnfants'][$indexUeEnfant]['listeEc'][$indexEcEnfantUeEnfant]['listeEcsEnfants'] = $this->mapEcArrayForComparison($ecEnfantData);
                    }
                }
            }
        }

        return $structure;
    }

    public function mapUeArrayForComparison(array $ueArray): array
    {
        return array_values(
            array_map(
                fn ($ue) => ["idUe" => $ue->ue->getId()],
                $ueArray
            )
        );
    }

    public function mapEcArrayForComparison(array $ecArray): array
    {
        return array_values(
            array_map(
                fn ($ec) => ["idEc" => $ec->elementConstitutif->getId()],
                $ecArray
            )
        );
    }

    private function compareMcccs(array|Collection|null $mcccsOriginal, array|Collection|null $mcccsNouveau): array
    {
        if (null === $mcccsOriginal && null === $mcccsNouveau) {
            return [];
        }

        $diff = [];

        foreach ($mcccsNouveau as $mcccNouveau) {
            $diff['new'][$mcccNouveau->getCleUnique()] = $mcccNouveau;
        }

        if (null === $mcccsOriginal) {
            $diff['original'] = [];
        } else {
            foreach ($mcccsOriginal as $mcccOriginal) {
                if (is_array($mcccOriginal)) {
                    $mcccOriginal = $this->createMcccFromArray($mcccOriginal);
                }
                $diff['original'][$mcccOriginal->getCleUnique()] = $mcccOriginal;
            }
        }


        return $diff;
    }

    private function compareMcccsBUT(array|Collection|null $mcccsOriginal, array|Collection|null $mcccsNouveau): array
    {
        if (Tools::isEmptyArrayOrCollection($mcccsOriginal) && Tools::isEmptyArrayOrCollection($mcccsNouveau)) {
            return [];
        }

        $diff = [];
        $t = [];


        if (Tools::isEmptyArrayOrCollection($mcccsOriginal)) {
            foreach ($mcccsNouveau as $mcccNouveau) {
                if (is_array($mcccNouveau)) {
                    $mcccNouveau = $this->createMcccFromArray($mcccNouveau);
                }
                $cleUnique = $mcccNouveau->getCleUnique();
                $diff[$cleUnique]['pourcentage'] = new DiffObject(null, $mcccNouveau->getPourcentage() !== 0.0 ? $mcccNouveau->getPourcentage() : '');
                $diff[$cleUnique]['nbEpreuves'] = new DiffObject(null, $mcccNouveau->getNbEpreuves() !== 0 ? $mcccNouveau->getNbEpreuves() : '');
                $diff[$cleUnique]['libelle'] = new DiffObject(null, $mcccNouveau->getLibelle());

            }
        } else {
            foreach ($mcccsNouveau as $mcccNouveau) {
                $t[$mcccNouveau->getCleUnique()]['pourcentage'] = $mcccNouveau->getPourcentage() !== 0.0 ? $mcccNouveau->getPourcentage() : '';
                $t[$mcccNouveau->getCleUnique()]['nbEpreuves'] = $mcccNouveau->getNbEpreuves() !== 0 ? $mcccNouveau->getNbEpreuves() : '';
                $t[$mcccNouveau->getCleUnique()]['libelle'] = $mcccNouveau->getLibelle();
            }
            foreach ($mcccsOriginal as $mcccOriginal) {
                if (is_array($mcccOriginal)) {
                    $mcccOriginal = $this->createMcccFromArray($mcccOriginal);
                }
                //todo: test d'array_key_exists
                $cleUnique = $mcccOriginal->getCleUnique();
                $diff[$cleUnique]['pourcentage'] = new DiffObject($mcccOriginal->getPourcentage() !== 0.0 ? $mcccOriginal->getPourcentage() : '', $t[$cleUnique]['pourcentage'] ?? null);
                $diff[$cleUnique]['nbEpreuves'] = new DiffObject($mcccOriginal->getNbEpreuves() !== 0 ? $mcccOriginal->getNbEpreuves() : '', $t[$cleUnique]['nbEpreuves'] ?? null);
                $diff[$cleUnique]['libelle'] = new DiffObject($mcccOriginal->getLibelle(), $t[$cleUnique]['libelle'] ?? null);
            }
        }

        return $diff;
    }


    private function createMcccFromArray(array $mcccOriginal): Mccc
    {
        $mccc = new Mccc();
        $mccc->setId($mcccOriginal['id']);
        if (array_key_exists('duree', $mcccOriginal)) {
            // création d'un objet DateTime à partir d'une chaine de caractères
            $mccc->setDuree(new DateTime($mcccOriginal['duree']));
        }
        $mccc->setLibelle($mcccOriginal['libelle']);
        $mccc->setNumeroSession($mcccOriginal['numeroSession']);
        $mccc->setSecondeChance($mcccOriginal['secondeChance'] == 1);
        $mccc->setPourcentage($mcccOriginal['pourcentage'] ?? 0.0);
        $mccc->setNbEpreuves($mcccOriginal['nbEpreuves']);
        $mccc->setTypeEpreuve($mcccOriginal['typeEpreuve']);
        $mccc->setControleContinu($mcccOriginal['controleContinu'] == 1);
        $mccc->setExamenTerminal($mcccOriginal['examenTerminal'] == 1);
        if (array_key_exists('numeroEpreuve', $mcccOriginal)) {
            // création d'un objet DateTime à partir d'une chaine de caractères
            $mccc->setNumeroEpreuve($mcccOriginal['numeroEpreuve']);
        }


        if (array_key_exists('options', $mcccOriginal)) {
            $mccc->setOptions($mcccOriginal['options']);
        }

        return $mccc;
    }

    /**
     * Compare les données d'origine et les actuelles, 
     * autres que celles présentes dans la maquette
     *  - Resp. / Co-Resp de parcours et de formation
     *  - Modalités d'enseignement
     *  - ...
     * @param Parcours $old Données originales 
     * @param Parcours $new Nouvelles données
     */
    public static function calculDiffDescriptifs(Parcours $old, Parcours $new): array {
        $diff = [];
        // Origine
        $oldData = [
            'libelleMention' => $old->getFormation()?->getDisplay() ?? '',
            'libelleParcours' => $old->isParcoursDefaut() ? '' : ($old->getDisplay()),
            'respParcours' => $old->getRespParcours()?->getDisplay() ?? "",
            'coRespParcours' => $old->getCoResponsable()?->getDisplay() ?? "",
            'respFormation' => $old->getFormation()?->getResponsableMention()?->getDisplay() ?? "",
            'modalitesEns' => $old->getModalitesEnseignement()?->libelle() ?? ""
        ];
        // Nouveau
        $newData = [
            'libelleMention' => $new->getFormation()?->getDisplay() ?? '',
            'libelleParcours' => $new->isParcoursDefaut() ? '' : ($new->getDisplay()),
            'respParcours' => $new->getRespParcours()?->getDisplay() ?? "",
            'coRespParcours' => $new->getCoResponsable()?->getDisplay() ?? "",
            'respFormation' => $new->getFormation()?->getResponsableMention()?->getDisplay() ?? "",
            'modalitesEns' => $new->getModalitesEnseignement()?->libelle() ?? "",
        ];

        foreach($oldData as $key => $value){
            $diff[$key] = new DiffObject($value, $newData[$key]);
        }   

        $regimeInscArray = [
            'FI' => RegimeInscriptionEnum::FI,
            'FC' => RegimeInscriptionEnum::FC,
            'FIA' => RegimeInscriptionEnum::FI_APPRENTISSAGE,
            'FCCP' => RegimeInscriptionEnum::FC_CONTRAT_PRO
        ];

        $diff['regimeInscription'] = [];
        foreach($regimeInscArray as $idx => $r) {
            $newRegimeInsc = in_array($r, $new->getRegimeInscription(), true) ? 'X' : '';
            $oldRegimeInsc = in_array($r, $old->getRegimeInscription(), true) ? 'X' : '';
            $diff['regimeInscription'][$idx] = new DiffObject($oldRegimeInsc, $newRegimeInsc);
        }

        return $diff;
    }
}
