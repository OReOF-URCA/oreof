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
use App\DTO\HeuresEctsSemestre;
use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\Mccc;
use App\Utils\Tools;
use DateTime;
use DateTimeInterface;

class VersioningStructureExtractDiff
{
    // On ne sauvegarde dans le tableau que les différences.

    public array $diffUe = [];
    public array $diff = [];
    public array $diffAdd = []; //todo: a gérer
    public array $trad = [];
    public array $diffRemove = [];//todo: a gérer

    public function __construct(
        private StructureParcours $dtoOrigine,
        private StructureParcours $dtoNouveau,
        private array $typeEpreuves
    ) {
        $this->trad = [
'COL_MCCC_CC' => 'Contrôle Continu',
'COL_MCCC_CT' => 'Contrôle Terminal',
            'COL_MCCC_SECONDE_CHANCE_CC_SUP_10' => 'Session 2 CC > 10',
            'COL_SECONDE_CHANCE_CT' => 'Session 2 Contrôle Terminal',
'COL_MCCC_TP' => 'Travaux Pratiques',
           'COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP' => 'Session 2 CC avec TP',
              'COL_MCCC_SECONDE_CHANCE_CC_SANS_TP' => 'Session 2 CC sans TP',
            'COL_MCCC_CCI' => 'Contrôle Continu Intégral',
        ];
    }

    //todo: gérer le cas d'ajout d'une UE, voire d'un Semestre
    //todo: gérer le cas d'une suppression EC, UE, Semestre entre ancien et nouveau

    public function extractDiff(): void
    {
        // parcourir les deux structures et comparer. Construire un tableau de différences
        foreach ($this->dtoOrigine->semestres as $ordreSemestre => $semestre) {
            if (array_key_exists($ordreSemestre, $this->dtoNouveau->semestres)) {
                $this->diff['semestres'][$ordreSemestre]['semestre'] = $this->dtoNouveau->semestres[$ordreSemestre];
                $this->diff['semestres'][$ordreSemestre]['heuresEctsSemestre'] = $this->compareHeuresEctsSemestre($semestre->heuresEctsSemestre, $this->dtoNouveau->semestres[$ordreSemestre]->heuresEctsSemestre);
                $this->diffAdd['semestres'][$ordreSemestre] = [];
                $this->diffRemove['semestres'][$ordreSemestre] = [];

                $this->compareSemestre($semestre, $this->dtoNouveau->semestres[$ordreSemestre], $ordreSemestre);
            }
        }
        $this->diff['heuresEctsFormation'] = $this->compareHeuresEctsFormation($this->dtoOrigine->heuresEctsFormation, $this->dtoNouveau->heuresEctsFormation);
    }

    private function compareSemestre(
        StructureSemestre $semestreOriginal,
        StructureSemestre $semestreNouveau,
        int               $ordreSemestre
    ): void {
        foreach ($semestreOriginal->ues as $ordreUe => $ue) {
            //            $this->hasModification = false;
            $ueNouvelle = $semestreNouveau->ues[$ordreUe] ?? null;
            $modifs = $this->compareUe($ue, $ueNouvelle);//cas si UE n'existe plus ou si ajouté dans nouveau ?
            if ($modifs !== false) {
                // 'ueCible' = StructureUe du côté cible (entités live), utilisée
                // pour identifier l'UE à recopier dans l'outil de comparaison.
                $this->diffUe[$ordreSemestre][] =
                    ['ue' => $ue, 'ueCible' => $ueNouvelle, 'modifications' => $modifs];
            }
        }
    }

    private function compareHeuresEctsSemestre(HeuresEctsSemestre $heuresEctsSemestre, HeuresEctsSemestre $heuresEctsSemestreNouveau): array
    {
        $diff = [];
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

        //$this->hasModification = $this->hasModifications($diff);

        return $diff;
    }

    private function compareUe(StructureUe $ueOriginale, ?StructureUe $ueNouvelle): array|false
    {
        $diff = [];
        $ueDiff = false;

        if ($ueNouvelle !== null) {
            $diff['display'] = new DiffObject($ueOriginale->display, $ueNouvelle->display);
            $diff['libelle'] = new DiffObject($ueOriginale->ue->getLibelle(), $ueNouvelle->ue->getLibelle());
            foreach ($ueOriginale->elementConstitutifs as $ordreEc => $ec) {
                if (!array_key_exists($ordreEc, $ueNouvelle->elementConstitutifs)) {
                    //donc n'existe plus ?
                    $diffEc = $this->compareElementConstitutif($ec, null);
                } else {
                    $diffEc = $this->compareElementConstitutif($ec, $ueNouvelle->elementConstitutifs[$ordreEc]);
                }
                if ($diffEc !== false) {
                    $ueDiff = true;
                    $diff['elementConstitutifs'][$ordreEc] = $diffEc;
                }
            }

            foreach ($ueNouvelle->elementConstitutifs as $ordreEc => $ec) {
                if (!array_key_exists($ordreEc, $ueOriginale->elementConstitutifs)) {
                    //donc nouvel EC
                    $diffEc = $this->compareElementConstitutif(null, $ec);
                    if ($diffEc !== false) {
                        $ueDiff = true;
                        $diff['elementConstitutifs'][$ordreEc] = $diffEc;
                    }
                }
            }


            foreach ($ueOriginale->uesEnfants() as $ordreUeEnfant => $ueEnfant) {
                if (array_key_exists($ordreUeEnfant, $ueNouvelle->uesEnfants())) {
                    $diffCompare = $this->compareUe($ueEnfant, $ueNouvelle->uesEnfants()[$ordreUeEnfant]);
                    if ($diffCompare !== false) {
                        $diff['uesEnfants'][$ordreUeEnfant] = $diffCompare;
                    }
                } else {
                    //donc n'existe plus ?
                }
            }
        }

        return $this->hasModifications($diff) || $ueDiff ? $diff : false;
    }

    private function compareElementConstitutif(?StructureEc $ecOriginal, ?StructureEc $ecNouveau): array|false
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
            //  $diff['raccroche'] = new DiffObject(null, $ecNouveau->raccroche);
            $diff['heuresEctsEc'] = $this->compareHeuresEctsEc(null, $ecNouveau->heuresEctsEc);
            $diff['typeMccc'] = new DiffObject('', $ecNouveau->typeMccc);
            $diff['mcccs'] = $this->compareMcccs([], $ecNouveau->mcccs, $diff['typeMccc']);

            return $this->hasModifications($diff) ? $diff : false;
        }

        if ($ecOriginal !== null && $ecNouveau === null) {
            if ($ecOriginal->elementConstitutif->getFicheMatiere() !== null) {
                $libelleNew = $ecOriginal->elementConstitutif->getFicheMatiere()->getLibelle();
            } else {
                $libelleNew = $ecOriginal->elementConstitutif->getLibelle();
            }

            $diff['libelle'] = new DiffObject($libelleNew, '-');
            $diff['code'] = new DiffObject($ecOriginal->elementConstitutif->getCode(), '');
            $diff['heuresEctsEc'] = $this->compareHeuresEctsEc($ecOriginal->heuresEctsEc, null);
            $diff['typeMccc'] = new DiffObject($ecOriginal->typeMccc, '-');
            $diff['mcccs'] = $this->compareMcccs($ecOriginal->mcccs, [], $diff['typeMccc']);

            return $this->hasModifications($diff) ? $diff : false;
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
        $diff['heuresEctsEc'] = $this->compareHeuresEctsEc($ecOriginal->heuresEctsEc, $ecNouveau->heuresEctsEc);
        if ($ecOriginal->typeMccc !== null && $ecNouveau->typeMccc !== null) {
            $diff['typeMccc'] = new DiffObject($ecOriginal->typeMccc, $ecNouveau->typeMccc);
            $diff['mcccs'] = $this->compareMcccs($ecOriginal->mcccs, $ecNouveau->mcccs, $diff['typeMccc']);
        }

        if ($ecNouveau->elementConstitutif->getNatureUeEc()?->isChoix()) {
            //EC enfants
            foreach ($ecNouveau->elementsConstitutifsEnfants as $ordreEc => $ecEnfant) {
                if (array_key_exists($ordreEc, $ecOriginal->elementsConstitutifsEnfants)) {
                    $modif = $this->compareElementConstitutif($ecOriginal->elementsConstitutifsEnfants[$ordreEc], $ecEnfant);
                    if ($modif !== null) {
                        $diff['ecEnfants'][$ordreEc] = $modif;
                    }
                } else {
                    //création
                    $diff['ecEnfants'][$ordreEc] = $this->compareElementConstitutif(null, $ecEnfant);
                }
            }
        }

        return $this->hasModifications($diff) ? $diff : false;
    }

    private function compareHeuresEctsEc(?HeuresEctsEc $heuresEctsEc, ?HeuresEctsEc $heuresEctsEc1): array|false
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

        return $this->hasModifications($diff) ? $diff : false;
    }

    private function compareHeuresEctsFormation(mixed $heuresEctsFormation, $heuresEctsFormationNouveau): array|false
    {
        $diff = [];
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

    private function compareMcccs(?array $mcccsOriginal, ?array $mcccsNouveau, DiffObject $typeMccc): ?string
    {
        if (null === $mcccsOriginal && null === $mcccsNouveau) {
            return 'Pas de MCCC';
        }

        if ($typeMccc->isDifferent()) {
            //construire la phrase en partant de Original
            return $this->getMcccFromNew($mcccsNouveau, $typeMccc->new);
        }

        //construire la phrase avec les différences

        $original = [];
        foreach ($mcccsOriginal as $mccc) {
            $original[] = $this->createMcccFromArray($mccc);
        }

        return $this->getMcccFromDiff($original, $mcccsNouveau, $typeMccc->new);
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
        $mccc->setPourcentage($mcccOriginal['pourcentage']);
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

    private function hasModifications(array $diff): bool
    {
        foreach ($diff as $key => $value) {
            if (is_array($value)) {
                if ($this->hasModifications($value)) {
                    return true;
                }
            }

            if ($value instanceof DiffObject && $value->isDifferent()) {
                return true;
            }

            if ($key === 'mcccs' && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function getMcccFromNew(?array $mcccsNouveau, ?string $typeMccc): string
    {
        if ($typeMccc === null) {
            return 'Pas de type de MCCC';
        }

        $mcccs = $this->getMcccs($mcccsNouveau, $typeMccc);
        $texte = '';

        switch ($typeMccc) {
            case 'cc':
                $texte = '';
                $texteAvecTp = '';
                $hasTp = false;
                $pourcentageTp = 0;
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1])) {
                    $nb = 1;
                    $nb2 = 1;
                    foreach ($mcccs[1]['cc'] as $mccc) {
                        for ($i = 1; $i <= $mccc->getNbEpreuves(); $i++) {
                            $texte .= 'CC' . $nb . ' (' . $mccc->getPourcentage() . '%) + ';
                            $nb++;
                        }

                        if ($mccc->hasTp()) {
                            $hasTp = true;
                            if ($mccc->getNbEpreuves() === 1) {
                                //si une seule épreuve de CC, pas de prise en compte du %de TP en seconde session
                                $pourcentageTp += $mccc->getPourcentage();
                            } else {
                                $pourcentageTp += $mccc->pourcentageTp();
                            }
                            $texteAvecTp .= 'TPr' . $nb2 . ' (' . $mccc->getPourcentage() . '%); ';


                            $nb2++;
                        }
                    }

                    $texte = substr($texte, 0, -2);
                    //$this->excelWriter->writeCellXY(self::COL_MCCC_CC, $ligne, $texte);
                }

                if (array_key_exists(2, $mcccs) && array_key_exists('et', $mcccs[2]) && is_array($mcccs[2]['et'])) {
                    $texte2 = '';
                    $pourcentageTpEt = $pourcentageTp / count($mcccs[2]['et']);
                    foreach ($mcccs[2]['et'] as $mccc) {
                        $texte2 .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        $texteAvecTp .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageTpEt);
                    }

                    $texte2 = substr($texte2, 0, -2);
                }

                if ($hasTp) {
                    $texteAvecTp = substr($texteAvecTp, 0, -2);
                    $texte .= " Session 2 : ".str_replace(';', '+', $texteAvecTp);
                    //$this->excelWriter->writeCellXY(self::COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP, $ligne, str_replace(';', '+', $texteAvecTp));
                } else {
                    $texte .= " Session 2 : ".$texte2;
                }

                break;
            case 'cci':
                $texte = '';
                foreach ($mcccs as $mccc) {
                    $texte .= 'CC' . $mccc->getNumeroSession() . ' (' . $mccc->getPourcentage() . '%); ';
                }
                $texte = substr($texte, 0, -2);
                break;
            case 'cc_ct':
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1]) && $mcccs[1]['cc'] !== null) {
                    $texte = '';
                    foreach ($mcccs[1]['cc'] as $mccc) {
                        $texte .= 'CC ' . $mccc->getNbEpreuves() . ' épreuve(s) (' . $mccc->getPourcentage() . '%) + ';
                    }
                    $texte = substr($texte, 0, -2);
                }

                $texteAvecTp = '';
                $texteCc = '';
                $pourcentageTp = 0;
                $pourcentageCc = 0;
                $nb = 1;
                $hasTp = false;
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1])) {
                    foreach ($mcccs[1]['cc'] as $mccc) {
                        if ($mccc->hasTp()) {
                            $hasTp = true;
                            if ($mccc->getNbEpreuves() === 1) {
                                //si une seule épreuve de CC, pas de prise en compte du %de TP en seconde session
                                $pourcentageTp += $mccc->getPourcentage();
                            } else {
                                $pourcentageTp += $mccc->pourcentageTp();
                            }
                        }
                        $pourcentageCc += $mccc->getPourcentage();
                        $texteCc .= 'CC (' . $mccc->getPourcentage() . '%); ';
                    }

                    if ($hasTp) {
                        $texteAvecTp .= 'TPr (' . $pourcentageTp . '%); ';
                    }

                    if (array_key_exists('et', $mcccs[1]) && $mcccs[1]['et'] !== null) {
                        $texteEpreuve = '';
                        foreach ($mcccs[1]['et'] as $mccc) {
                            $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        }

                        $texteEpreuve = substr($texteEpreuve, 0, -2);
                        $texte .= $texteEpreuve;
                    }
                }

                if (array_key_exists(2, $mcccs) && array_key_exists('et', $mcccs[2]) && $mcccs[2]['et'] !== null) {
                    $texteEpreuve = '';
                    $pourcentageTpEt = $pourcentageTp / count($mcccs[2]['et']);
                    $pourcentageCcEt = $pourcentageCc / count($mcccs[2]['et']);
                    foreach ($mcccs[2]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        $texteAvecTp .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageTpEt);
                        $texteCc .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageCcEt);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $texteCc = substr($texteCc, 0, -2);
                    $texteCc = str_replace('CC', 'CCr', $texteCc);
                    $texteAvecTp = substr($texteAvecTp, 0, -2);


                    if ($hasTp) {
                        $texte .= " Session 2 : ".str_replace(';', '+', $texteAvecTp);
                    } else {
                        //si TP cette celulle est vide...
                        $texte .= " Session 2 : ".$texteEpreuve;
                    }

                    $texte .= " Session 2 : ".str_replace(';', '+', $texteCc);
                }
                break;
            case 'ct':
                $quitus = false; //todo: $ec->isQuitus();
                if (array_key_exists(1, $mcccs) && array_key_exists('et', $mcccs[1]) && $mcccs[1]['et'] !== null) {
                    $texteEpreuve = '';
                    foreach ($mcccs[1]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc, $quitus);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $texte .= $texteEpreuve;
                    //$this->excelWriter->writeCellXY(self::COL_MCCC_CT, $ligne, $texteEpreuve);
                }

                if (array_key_exists(2, $mcccs) && array_key_exists('et', $mcccs[2]) && $mcccs[2]['et'] !== null) {
                    $texteEpreuve = '';
                    foreach ($mcccs[2]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc, $quitus);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $texte .= ' Session 2 : ' . $texteEpreuve;
                }
                break;
        }

        return $texte;
    }

    private function displayTypeEpreuveWithDureePourcentage(Mccc $mccc, ?bool $quitus = false): string
    {
        $texte = '';
        foreach ($mccc->getTypeEpreuve() as $type) {
            if ($type !== "" && $this->typeEpreuves[$type] !== null) {
                if ($quitus === true) {
                    $texte .= 'QUITUS ' . $this->typeEpreuves[$type]->getSigle();
                } else {
                    $duree = '';
                    if ($this->typeEpreuves[$type]->isHasDuree() === true) {
                        $duree = ' ' . $this->displayDuree($mccc->getDuree());
                    }

                    $texte .= $this->typeEpreuves[$type]->getSigle() . $duree . ' (' . $mccc->getPourcentage() . '%); ';
                }
            } else {
                $texte .= 'erreur épreuve; ';
            }
        }

        return $texte;
    }

    private function displayTypeEpreuveWithDureePourcentageTp(Mccc $mccc, float $pourcentage): string
    {
        $texte = '';
        foreach ($mccc->getTypeEpreuve() as $type) {
            if ($type !== "" && $this->typeEpreuves[$type] !== null) {
                $duree = '';
                if ($this->typeEpreuves[$type]->isHasDuree() === true) {
                    $duree = ' ' . $this->displayDuree($mccc->getDuree());
                }
                if (($mccc->getPourcentage() - $pourcentage) > 0.0) {
                    $texte .= $this->typeEpreuves[$type]->getSigle() . $duree . ' (' . ($mccc->getPourcentage() - $pourcentage) . '%); ';
                }
            } else {
                $texte .= 'erreur épreuve; ';
            }
        }

        return $texte;
    }

    private function getMcccs(array $mcccs, string $typeMccc): array
    {
        $tabMcccs = [];

        if ($typeMccc === 'cci') {
            foreach ($mcccs as $mccc) {
                $tabMcccs[$mccc->getNumeroSession()] = $mccc;
            }
        } else {
            foreach ($mcccs as $mccc) {
                if ($mccc->isSecondeChance()) {
                    $tabMcccs[3]['chance'] = $mccc;
                } elseif ($mccc->isControleContinu() === true && $mccc->isExamenTerminal() === false) {
                    $tabMcccs[$mccc->getNumeroSession()]['cc'][$mccc->getNumeroEpreuve() ?? 1] = $mccc;
                } elseif ($mccc->isControleContinu() === false && $mccc->isExamenTerminal() === true) {
                    $tabMcccs[$mccc->getNumeroSession()]['et'][$mccc->getNumeroEpreuve() ?? 1] = $mccc;
                }
            }
        }

        return $tabMcccs;
    }

    protected function displayDuree(?DateTimeInterface $duree): string
    {
        if ($duree === null) {
            return '';
        }

        return $duree->format('H\hi');
    }

    private function getMcccFromDiff(array $original, ?array $mcccsNouveau, string $typeMccc): string
    {
        $texte ='';
        $diffMccc = [];
        $mcccsOriginal = $this->getMcccs($original, $typeMccc);
        $mcccsNew = $this->getMcccs($mcccsNouveau, $typeMccc);

        //cas Original sans écrire dans les cellules
        $displayMcccOriginal = $this->calculDisplayMccc($mcccsOriginal, $typeMccc, false);
        $displayMcccNew =$this->calculDisplayMccc($mcccsNew, $typeMccc, false);


        //fusionner les deux tableaux $displayMcccOriginal et $displayMcccNew en construisant un objet DiffObject
        foreach ($displayMcccOriginal as $key => $value) {
            if (array_key_exists($key, $displayMcccNew)) {
                $diffMccc[$key] = new DiffObject($value, $displayMcccNew[$key]);
            } else {
                $diffMccc[$key] = new DiffObject($value, '');
            }
        }

        foreach ($displayMcccNew as $key => $value) {
            if (!array_key_exists($key, $displayMcccOriginal)) {
                $diffMccc[$key] = new DiffObject('', $value);
            }
        }

        foreach ($diffMccc as $key => $value) {
            if ($value->isDifferent()) {
                $texte .= $this->trad[$key] . ' : ' . $value->displayDiff() . '; ';
            }
        }


        return $texte;
    }

    private function calculDisplayMccc(array $mcccs, string $typeMccc, bool $isQuitus): array
    {
        $tDisplay = [];

        switch ($typeMccc) {
            case 'cc':
                $texte = '';
                $texteAvecTp = '';
                $hasTp = false;
                $pourcentageTp = 0;
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1])) {
                    $nb = 1;
                    $nb2 = 1;
                    /** @var Mccc $mccc */
                    foreach ($mcccs[1]['cc'] as $mccc) {
                        for ($i = 1; $i <= $mccc->getNbEpreuves(); $i++) {
                            $texte .= 'CC' . $nb . ' (' . $mccc->getPourcentage() . '%); ';
                            $nb++;
                        }

                        if ($mccc->hasTp()) {
                            $hasTp = true;
                            if ($mccc->getNbEpreuves() === 1) {
                                //si une seule épreuve de CC, pas de prise en compte du %de TP en seconde session
                                //todo: interdire la saisie d'un pourcentage de TP si une seule épreuve de CC
                                $pourcentageTp += $mccc->getPourcentage();
                            } else {
                                $pourcentageTp += $mccc->pourcentageTp();
                            }
                            $texteAvecTp .= 'TPr' . $nb2 . ' (' . $mccc->getPourcentage() . '%); ';


                            $nb2++;
                        }
                    }

                    $texte = substr($texte, 0, -2);
                    $tDisplay['COL_MCCC_CC'] = $texte;
                }

                if (array_key_exists(2, $mcccs) && array_key_exists('et', $mcccs[2]) && is_array($mcccs[2]['et'])) {
                    $texte = '';
                    $pourcentageTpEt = $pourcentageTp / count($mcccs[2]['et']);
                    foreach ($mcccs[2]['et'] as $mccc) {
                        $texte .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        $texteAvecTp .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageTpEt);
                    }

                    $texte = substr($texte, 0, -2);
                }

                if ($hasTp) {
                    $texteAvecTp = substr($texteAvecTp, 0, -2);
                    $tDisplay['COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP'] = str_replace(';', '+', $texteAvecTp);
                } else {
                    $tDisplay['COL_MCCC_SECONDE_CHANCE_CC_SANS_TP'] = $texte;
                }

                break;
            case 'cci':
                $texte = '';
                /** @var Mccc $mccc */
                foreach ($mcccs as $mccc) {
                    $texte .= 'CC' . $mccc->getNumeroSession() . ' (' . $mccc->getPourcentage() . '%); ';
                }
                $texte = substr($texte, 0, -2);
                $tDisplay['COL_MCCC_CCI'] = $texte;

                break;
            case 'cc_ct':
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1]) && $mcccs[1]['cc'] !== null) {
                    $texte = '';
                    /** @var Mccc $mccc */
                    foreach ($mcccs[1]['cc'] as $mccc) {
                        $texte .= 'CC ' . $mccc->getNbEpreuves() . ' épreuve(s) (' . $mccc->getPourcentage() . '%); ';
                    }
                    $texte = substr($texte, 0, -2);
                    $tDisplay['COL_MCCC_CC'] = $texte;
                }

                $texteAvecTp = '';
                $texteCc = '';
                $pourcentageTp = 0;
                $pourcentageCc = 0;
                $nb = 1;
                $hasTp = false;
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1])) {
                    foreach ($mcccs[1]['cc'] as $mccc) {
                        if ($mccc->hasTp()) {
                            $hasTp = true;
                            if ($mccc->getNbEpreuves() === 1) {
                                //si une seule épreuve de CC, pas de prise en compte du %de TP en seconde session
                                $pourcentageTp += $mccc->getPourcentage();
                            } else {
                                $pourcentageTp += $mccc->pourcentageTp();
                            }
                        }
                        $pourcentageCc += $mccc->getPourcentage();
                        $texteCc .= 'CC (' . $mccc->getPourcentage(). '%); ';
                    }

                    if ($hasTp) {
                        $texteAvecTp .= 'TPr (' . $pourcentageTp . '%); ';
                    }

                    if (array_key_exists('et', $mcccs[1]) && $mcccs[1]['et'] !== null) {
                        $texteEpreuve = '';
                        foreach ($mcccs[1]['et'] as $mccc) {
                            $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        }

                        $texteEpreuve = substr($texteEpreuve, 0, -2);
                        $tDisplay['COL_MCCC_CT'] = $texteEpreuve;
                    }
                }

                if (array_key_exists(2, $mcccs) && array_key_exists('et', $mcccs[2]) && $mcccs[2]['et'] !== null) {
                    $texteEpreuve = '';
                    $pourcentageTpEt = $pourcentageTp / count($mcccs[2]['et']);
                    $pourcentageCcEt = $pourcentageCc / count($mcccs[2]['et']);
                    foreach ($mcccs[2]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc);
                        $texteAvecTp .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageTpEt);
                        $texteCc .= $this->displayTypeEpreuveWithDureePourcentageTp($mccc, $pourcentageCcEt);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $texteCc = substr($texteCc, 0, -2);
                    $texteCc = str_replace('CC', 'CCr', $texteCc);
                    $texteAvecTp = substr($texteAvecTp, 0, -2);

                    if ($hasTp) {
                        $tDisplay['COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP'] = str_replace(';', '+', $texteAvecTp);
                    } else {
                        //si TP cette celulle est vide...
                        $tDisplay['COL_MCCC_SECONDE_CHANCE_CC_SANS_TP'] = $texteEpreuve;
                    }
                    $tDisplay['COL_MCCC_SECONDE_CHANCE_CC_SUP_10'] = str_replace(';', '+', $texteCc);
                }

                //on garde CC et on complète avec le reste de pourcentage de l'ET

                break;
            case 'ct':
                if (array_key_exists(1, $mcccs) && array_key_exists('et', $mcccs[1]) && $mcccs[1]['et'] !== null) {
                    $texteEpreuve = '';
                    foreach ($mcccs[1]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc, $isQuitus);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);

                    $tDisplay['COL_MCCC_CT'] = $texteEpreuve;
                }

                if (array_key_exists(2, $mcccs) && array_key_exists('et', $mcccs[2]) && $mcccs[2]['et'] !== null) {
                    $texteEpreuve = '';
                    foreach ($mcccs[2]['et'] as $mccc) {
                        $texteEpreuve .= $this->displayTypeEpreuveWithDureePourcentage($mccc, $isQuitus);
                    }

                    $texteEpreuve = substr($texteEpreuve, 0, -2);
                    $tDisplay['COL_MCCC_SECONDE_CHANCE_CT'] = $texteEpreuve;
                }
                break;
        }

        return $tDisplay;
    }
}
