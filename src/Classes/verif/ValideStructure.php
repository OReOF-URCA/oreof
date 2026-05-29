<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/verif/ValideStructure.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 29/09/2023 15:37
 */

namespace App\Classes\verif;

use App\Classes\GetElementConstitutif;
use App\Classes\GetUeEcts;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\TypeDiplome;
use App\Entity\Ue;

abstract class ValideStructure extends AbstractValide
{
    private static Parcours $parcours;
    private static array $structure = [];
    private static array $errors = [];

    private static ?TypeDiplome $typeDiplome;

    public static function valideStructure(Parcours $parcours): void
    {
        self::$typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        self::$parcours = $parcours;

        $etatGlobal = self::COMPLET;
        self::$structure['semestres'] = [];

        if (self::$parcours->getSemestreParcours()->count() === 0) {
            self::$errors[] = 'Aucun semestre renseigné';
            $etatGlobal = self::ERREUR;
        } else {
            foreach (self::$parcours->getSemestreParcours() as $semestreParcour) {
                if ($semestreParcour->getSemestre()?->getSemestreRaccroche() !== null) {
                    $sem = $semestreParcour->getSemestre()?->getSemestreRaccroche()?->getSemestre();
                } else {
                    $sem = $semestreParcour->getSemestre();
                }

                self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = self::COMPLET;
                self::$structure['semestres'][$semestreParcour->getOrdre()]['erreur'] = [];
                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'] = [];

                if ($sem !== null && $sem->isNonDispense() !== true && $semestreParcour->isOuvert() === true) {
                    if ($sem->getUes()->count() === 0) {
                        self::$errors[] = 'Aucune UE renseignée pour le semestre ' . $semestreParcour->getOrdre();
                    }
                    $hasUe = count($sem->getUes()) === 0 ? self::VIDE : self::COMPLET;
                    self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'] = [];
                    foreach ($sem->getUes() as $ue) {
                        if ($ue->getUeParent() === null) {
                            self::valideUe($ue, $semestreParcour->getOrdre());
                        }
                    }
                    $nbEctsAttendus = self::$typeDiplome?->getNbEctsParSemestre();
                    $ectsActifs = self::$typeDiplome?->isHasEcts() ?? true;
                    $quotaDepasse = $ectsActifs && $nbEctsAttendus !== null
                        && ($sem->isNonDispense() === false || $semestreParcour->isOuvert() === true)
                        && self::totalEctsSemestre($sem) !== (float)$nbEctsAttendus;
                    if ($quotaDepasse) {
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = self::ERREUR;
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['erreur'][] = 'Le semestre doit faire ' . $nbEctsAttendus . ' ECTS';
                        self::$errors[] = 'Le semestre ' . $semestreParcour->getOrdre() . ' doit faire ' . $nbEctsAttendus . ' ECTS';
                    } else {
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = $hasUe;
                    }
                } else {
                    if ($sem?->isNonDispense() !== true || $semestreParcour->isOuvert()===false) {
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = self::NON_CONCERNE;
                    }
                }
            }
        }
        self::$structure['global'] = $etatGlobal;
    }

    private static function valideUe(Ue $ue, int $ordreSemestre): void
    {
        if ($ue !== null && $ue->getUeRaccrochee() !== null) {
            $ue = $ue->getUeRaccrochee()->getUe();
        }

        if ($ue !== null && $ue->getUeParent() === null) {
            if ($ue->getNatureUeEc()?->isLibre()) {
                self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'] = $ue->getEcts() === 0.0 ? self::VIDE : self::COMPLET;
                self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['ecs'] = [];
                self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['ue'] = $ue;
            } elseif ($ue->getUeEnfants()->count() === 0) {
                self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'] = count($ue->getElementConstitutifs()) === 0 ? self::VIDE : self::COMPLET;
                self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['ecs'] = [];
                self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['ue'] = $ue;
                foreach ($ue->getElementConstitutifs() as $ec) {
                    if ($ec->getEcParent() === null) {
                        self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['ecs'][$ec->getId()] = self::valideEc($ec, $ordreSemestre, $ue);
                    }
                }
            } else {
                if ($ue !== null && $ue->getUeEnfants()->count() > 0 && $ue->getNatureUeEc()?->isChoix() === true) {
                    self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['ue'] = $ue;
                    self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['enfants'] = [];
                    self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'] = self::COMPLET;
                    foreach ($ue->getUeEnfants() as $uee) {
                        self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['enfants'][$uee->getId()]['ue'] = $uee;
                        self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['enfants'][$uee->getId()]['global'] = self::COMPLET;
                        self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['enfants'][$uee->getId()]['ecs'] = [];
                        if (!$uee->getNatureUeEc()?->isLibre()) {
                            foreach ($uee->getElementConstitutifs() as $ec) {
                                if ($ec->getEcParent() === null) {
                                    self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['enfants'][$uee->getId()]['ecs'][$ec->getId()] = self::valideEc($ec, $ordreSemestre, $uee);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public static function getStructure(): array
    {
        return self::$structure;
    }

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function valideEc(ElementConstitutif $ec, int $ordreSemestre, Ue $ue): array
    {
        $t['display'] = $ec->display();
        $t['global'] = self::COMPLET;
        $t['erreur'] = [];

        if ($ec->getNatureUeEc()?->isLibre()) {
            //si l'EC est libre, on ne vérifie pas les infos
            if ($ec->getTypeEc() !== null) {
                $t['global'] = self::COMPLET;
                $t['erreur'] = [];
            } else {
                $t['global'] = self::INCOMPLET;
                $t['erreur'] = ['Type d\'EC non renseigné (disciplinaire, ...)'];
                self::$errors[] = 'Type d\'EC non renseigné (disciplinaire, ...) pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
            }

            //vérification des ECTS, d'un libellé et d'une description
        } elseif ($ec->getNatureUeEc()?->isChoix() || $ec->getEcEnfants()->count() > 0) {
            if (self::$typeDiplome !== null && self::$typeDiplome->isHasEcts()) {
                //si l'EC est un choix, on vérifie les enfants => todo: utiliser self::analyseEc ?
                if ($ec->getEcts() !== null && $ec->getEcts() > 0.0 && $ec->getEcts() <= 30.0) {
                    $t['global'] = self::COMPLET;
                    $t['erreur'] = [];
                } else {
                    if (self::$typeDiplome->isEctsObligatoireSurEc() === true && ($ec->getEcts() === null || $ec->getEcts() === 0.0)) {
                        $t['global'] = self::INCOMPLET_ECTS;
                        $t['erreur'] = ['Les ECTS ne sont pas définis mais ce type de diplôme l\'autorise pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours)];
                    } else {
                        $t['global'] = self::ERREUR;
                        $t['erreur'] = ['Les ECTS ne sont pas définis ou non compris entre 0 et 30 l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours)];
                    }
                }
            }

            $t['enfants'] = [];

            //traiter les enfants
            foreach ($ec->getEcEnfants() as $ecEnfant) {
                $t['enfants'][$ecEnfant->getId()] = self::analyseEc($ecEnfant, $ordreSemestre, $ue);
            }
        } else {
            //sinon cas classique ?
            return self::analyseEc($ec, $ordreSemestre, $ue);
        }

        return $t;
    }

    private static function analyseEc(ElementConstitutif $ec, int $ordreSemestre, Ue $ue): array
    {
        $t['display'] = $ec->display();
        $t['erreur'] = [];
        $t['global'] = self::COMPLET;
        $etatEc = self::COMPLET;

        if ($ec->getTypeEc() === null && $ec->getEcParent() === null) {
            $etatEc = self::INCOMPLET;
            $t['erreur'][] = 'Type d\'EC non renseigné (disciplinaire, ...)';
            self::$errors[] = 'Type d\'EC non renseigné (disciplinaire, ...) pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
        }
        $getElement = new GetElementConstitutif($ec, self::$parcours);
        if ($ec->getNatureUeEc()?->isLibre() === true) {
            if (self::$typeDiplome !== null && self::$typeDiplome->isHasEcts()) {
                $ects = $getElement->getFicheMatiereEcts();
                if (self::$typeDiplome->isEctsObligatoireSurEc() === false && ($ects === null || $ects === 0.0)) {
                    $t['erreur'][] = 'ECTS non renseignés, mais ce type de diplôme l\'autorise';
                    $etatEc = self::INCOMPLET_ECTS;
                    self::$errors[] = 'ECTS non renseignés, mais ce type de diplôme l\'autorise pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
                } else {
                    if ($ects === null ||
                        $ects <= 0.0 ||
                        $ects > 30.0) {
                        $t['erreur'][] = 'ECTS non renseignés';
                        $etatEc = self::ERREUR;
                        self::$errors[] = 'ECTS non renseignés pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
                    }
                }
            }
        } elseif ($ec->getFicheMatiere() === null) {
            $t['global'] = self::VIDE;
            $t['erreur'][] = 'Fiche matière non renseignée';
            self::$errors[] = 'Fiche matière non renseignée pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
            self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'] = self::INCOMPLET;
        } else {
            // MCCC
            // Si MCCC imposées
            // Si MCCC raccrochée
            // Si Enfants et MCCC du parent
            // Sinon
            if ($getElement->getEtatsMccc() !== 'Complet' && $getElement->getElementConstitutif()->isControleAssiduite() === false) {
                //todo: gérer MCCC raccrochées, ou d'un parent
                $t['erreur'][] = 'MCCC non renseignées';
                $etatEc = self::INCOMPLET;
                self::$errors[] = 'MCCC non renseignées pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
            }
            if (self::$typeDiplome !== null && self::$typeDiplome->isHasEcts()) {
                $ects = $getElement->getFicheMatiereEcts();
                if (self::$typeDiplome->isEctsObligatoireSurEc() === false && ($ects === null || $ects === 0.0)) {
                    $t['erreur'][] = 'ECTS non renseignés, mais ce type de diplôme l\'autorise';
                    $etatEc = self::INCOMPLET_ECTS;
                    self::$errors[] = 'ECTS non renseignés, mais ce type de diplôme l\'autorise pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
                } else {
                    if ($ects === null ||
                        $ects <= 0.0 ||
                        $ects > 30.0) {
                        $t['erreur'][] = 'ECTS non renseignés';
                        $etatEc = self::ERREUR;
                        self::$errors[] = 'ECTS non renseignés pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
                    }
                }
            }

            if ($getElement->getEtatStructure() !== 'Complet' && $getElement->isSansHeures() === false) {
                //todo:gérer MCCC raccrochées, ou d'un parent
                $t['erreur'][] = 'Volumes horaires non renseignés';
                $etatEc = self::INCOMPLET;
                self::$errors[] = 'Volumes horaires non renseignés pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
            }

            if ($getElement->getEtatBcc() !== 'Complet') {
                //todo:gérer MCCC raccrochées, ou d'un parent
                $t['erreur'][] = 'BCC incomplet ou non renseignés';
                $etatEc = self::INCOMPLET;
                self::$errors[] = 'BCC incomplet ou non renseignés pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
            }

            $t['global'] = $etatEc;
            if ($ue->getUeParent() === null) {
                if (array_key_exists($ue->getId(), self::$structure['semestres'][$ordreSemestre]['ues'])) {
                    self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'] = self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'] === self::COMPLET ? $etatEc : self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getId()]['global'];
                }
            } else {
                if (array_key_exists($ue->getUeParent()->getId(), self::$structure['semestres'][$ordreSemestre]['ues'])) {
                    self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getUeParent()->getId()]['global'] = self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getUeParent()->getId()]['global'] === self::COMPLET ? $etatEc : self::$structure['semestres'][$ordreSemestre]['ues'][$ue->getUeParent()->getId()]['global'];
                }
            }
        }
        return $t;
    }

    public static function valideStructureBut(Parcours $parcours): array
    {
        self::$parcours = $parcours;
        $etatGlobal = self::COMPLET;
        self::$structure['semestres'] = [];
        self::$structure['global'] = self::COMPLET;
        foreach (self::$parcours->getSemestreParcours() as $semestreParcour) {
            if ($semestreParcour->getSemestre()?->getSemestreRaccroche() !== null) {
                $sem = $semestreParcour->getSemestre()?->getSemestreRaccroche()?->getSemestre();
            } else {
                $sem = $semestreParcour->getSemestre();
            }

            if ($sem !== null && $sem->isNonDispense() === false) {
                $hasUe = count($sem->getUes()) === 0 ? self::VIDE : self::COMPLET;
                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'] = [];
                self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = self::INCOMPLET;

                self::$structure['semestres'][$semestreParcour->getOrdre()]['erreur'] = [];
                foreach ($sem->getUes() as $ue) {
                    if (count($ue->getElementConstitutifs()) === 0) {
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['global'] = self::VIDE;
                        self::$structure['global'] = self::INCOMPLET;
                        self::$errors[] = 'Aucun EC renseigné pour l\'' . $ue->display(self::$parcours);
                    } else {
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['global'] = self::COMPLET;
                    }


                    self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'] = [];
                    foreach ($ue->getElementConstitutifs() as $ec) {
                        //                        if (!$ec->getNatureUeEc()?->isChoix()) {
                        self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['display'] = $ec->display();
                        if ($ec->getFicheMatiere() === null || ($ec->getFicheMatiere()->getMcccs()->count() === 0 && $ec->getFicheMatiere()->isSansNote() === false) || $ec->getFicheMatiere()->etatStructure() !== 'Complet' || ($ec->getTypeEc() === null)) {
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['global'] = self::INCOMPLET;
                            self::$structure['global'] = self::INCOMPLET;
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'] = [];
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['global'] = self::INCOMPLET;
                            $hasUe = self::INCOMPLET;

                            //pour chaque cas indiquer l'erreur
                            if ($ec->getFicheMatiere() === null) {
                                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'][] = 'Fiche matière non renseignée';
                                self::$errors[] = 'Fiche matière non renseignée pour l\'' . $ec->getCode() . ' de l\'' . $ue->display(self::$parcours);
                                self::$structure['global'] = self::INCOMPLET;
                            }

                            if ($ec->getFicheMatiere() !== null && $ec->getFicheMatiere()->getMcccs()->count() === 0 && $ec->getFicheMatiere()->isSansNote() === false) {
                                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'][] = 'MCCC non renseignées';
                                self::$errors[] = 'MCCC non renseignées pour l\'' . $ec->getFicheMatiere()->getSigle() . ' de l\'' . $ue->display(self::$parcours);
                                self::$structure['global'] = self::INCOMPLET;
                            }

                            $etatBcc = '';
                            foreach ($ec->getFicheMatiere()?->getElementConstitutifs() as $ece) {
                                if ($ece->getEtatBcc(self::$parcours) === 'Complet') {
                                    $etatBcc = 'Complet';
                                }
                            }

                            if ($etatBcc !== 'Complet') {
                                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'][] = 'BCC incomplet ou non renseignés';
                                self::$errors[] = 'BCC incomplet ou non renseignés pour l\'' . $ec->getFicheMatiere()?->getSigle() . ' de l\'' . $ue->display(self::$parcours);
                            }

                            if ($ec->getFicheMatiere()?->etatStructure() !== 'Complet' && $ec->getFicheMatiere()->isSansHeures() === false) {
                                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'][] = 'Volumes horaires incomplet ou non renseignés';
                                self::$errors[] = 'Volumes horaires incomplet ou non renseignés pour l\'' . $ec->getFicheMatiere()?->getSigle() . ' de l\'' . $ue->display(self::$parcours);
                            }

                            if ($ec->getTypeEc() === null) {
                                self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'][] = 'Type EC (ressource ou SAE) non renseignés';
                                self::$errors[] = 'Type EC (ressource ou SAE) non renseignés l\'' . $ec->getFicheMatiere()?->getSigle() . ' de l\'' . $ue->display(self::$parcours);
                            }
                        } elseif ($ec->getFicheMatiere() === null && $ec->getFicheMatiere()->getMcccs()->count() === 0 && $ec->getFicheMatiere()?->etatStructure() === 'À compléter' && $ec->getTypeEc() === null) {
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['global'] = self::VIDE;
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'] = [];
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['global'] = self::INCOMPLET;
                            self::$structure['global'] = self::INCOMPLET;
                            $hasUe = self::INCOMPLET;
                        } else {
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['global'] = self::COMPLET;
                            self::$structure['semestres'][$semestreParcour->getOrdre()]['ues'][$ue->getId()]['ecs'][$ec->getId()]['erreur'] = [];
                        }
                        //  }
                    }
                }

                $nbEctsAttendus = self::$parcours->getTypeDiplome()?->getNbEctsParSemestre();
                $ectsActifs = self::$parcours->getTypeDiplome()?->isHasEcts() ?? true;
                $quotaDepasse = $ectsActifs && $nbEctsAttendus !== null
                    && $sem->isNonDispense() === false
                    && self::totalEctsSemestre($sem) !== (float)$nbEctsAttendus;
                if ($quotaDepasse) {
                    self::$errors[] = 'Le semestre ' . $semestreParcour->getOrdre() . ' doit faire ' . $nbEctsAttendus . ' ECTS';
                    self::$structure['semestres'][$semestreParcour->getOrdre()]['erreur'][] = 'Le semestre doit faire ' . $nbEctsAttendus . ' ECTS';
                    self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = self::ERREUR;
                    self::$structure['global'] = self::INCOMPLET;
                } else {
                    self::$structure['semestres'][$semestreParcour->getOrdre()]['global'] = $hasUe;
                }
            }
        }

        $structure['global'] = $etatGlobal;

        return $structure;
    }

    private static function totalEctsSemestre(Semestre $semestre): float
    {
        $ects = 0.0;
        $typeDiplome = self::$parcours->getTypeDiplome();
        foreach ($semestre->getUes() as $ue) {
            if ($ue->getUeParent() === null) {
                $ects += GetUeEcts::getEcts($ue, self::$parcours, $typeDiplome);
            }
        }

        return $ects;
    }
}
