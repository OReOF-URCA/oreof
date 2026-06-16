<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/verif/ParcoursValide.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/08/2023 14:55
 */

namespace App\Classes\verif;

use App\DTO\Remplissage;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Enums\RegimeInscriptionEnum;

class ParcoursValide extends AbstractValide
{
    public array $etat = [];
    public array $bccs = [];

    public function __construct(
        protected Parcours $parcours,
        protected TypeDiplome $typeDiplome
    ) {
    }

    public function valideParcours(): ParcoursValide
    {
        if (!$this->parcours->isParcoursDefaut()) {
            //onglet 1
            $this->etat['respParcours'] = $this->parcours->getRespParcours() ? self::COMPLET : self::VIDE;
            $this->etat['objectifsParcours'] = $this->nonVide($this->parcours->getObjectifsParcours());
            $this->etat['resultatsAttendus'] = $this->nonVide($this->parcours->getResultatsAttendus());
            $this->etat['contenuParcours'] = $this->nonVide($this->parcours->getContenuFormation());
            $this->etat['motsCles'] = $this->nonVide($this->parcours->getMotsCles());
            $this->etat['rythmeParcours'] = $this->parcours->getRythmeFormation() !== null || $this->nonVide($this->parcours->getRythmeFormationTexte()) ? self::COMPLET : self::VIDE;
            $this->etat['localisation'] = $this->parcours->getLocalisation() ? self::COMPLET : self::VIDE;
        }


        //onglet 2
        $this->etat['modalitesEnseignement'] = $this->parcours->getModalitesEnseignement() ? self::COMPLET : self::VIDE;
        if (($this->parcours->isHasStage() === null || $this->parcours->isHasStage() === false) && $this->typeDiplome->isHasStage() === true) {
            $this->etat['stage'] = self::INCOMPLET;
            $this->etat['stageModalite'] = self::VIDE;
            $this->etat['stageHeures'] = self::VIDE;
        } elseif ($this->parcours->isHasStage() === true) {
            if ($this->parcours->getStageText() === null || trim($this->parcours->getStageText()) === '') {
                $this->etat['stageModalite'] = self::INCOMPLET;
                $this->etat['stage'] = self::INCOMPLET;
            } else {
                $this->etat['stageModalite'] = self::COMPLET;
            }
            if ($this->parcours->getNbHeuresStages() === 0.0 || $this->parcours->getNbHeuresStages() === null) {
                $this->etat['stageHeures'] = self::INCOMPLET;
                $this->etat['stage'] = self::INCOMPLET;
            } else {
                $this->etat['stageHeures'] = self::COMPLET;
            }

            if ($this->etat['stageHeures'] === self::COMPLET && $this->etat['stageModalite'] === self::COMPLET) {
                $this->etat['stage'] = self::COMPLET;
            }
        } else {
            $this->etat['stage'] = self::NON_CONCERNE;
        }

        if (($this->parcours->isHasProjet() === null || $this->parcours->isHasProjet() === false) && $this->typeDiplome->isHasProjet() === true) {
            $this->etat['projet'] = self::INCOMPLET;
            $this->etat['projetModalite'] = self::VIDE;
            $this->etat['projetHeures'] = self::VIDE;
        } elseif ($this->parcours->isHasProjet() === true) {
            if ($this->parcours->getProjetText() === null || trim($this->parcours->getProjetText()) === '') {
                $this->etat['projetModalite'] = self::INCOMPLET;
                $this->etat['projet'] = self::INCOMPLET;
            } else {
                $this->etat['projetModalite'] = self::COMPLET;
            }
            if ($this->parcours->getNbHeuresProjet() === 0.0 || $this->parcours->getNbHeuresProjet() === null) {
                $this->etat['projetHeures'] = self::INCOMPLET;
                $this->etat['projet'] = self::INCOMPLET;
            } else {
                $this->etat['projetHeures'] = self::COMPLET;
            }

            if ($this->etat['projetHeures'] === self::COMPLET && $this->etat['projetModalite'] === self::COMPLET) {
                $this->etat['projet'] = self::COMPLET;
            }
        } else {
            $this->etat['projet'] = self::NON_CONCERNE;
        }

        if (($this->parcours->isHasSituationPro() === null || $this->parcours->isHasSituationPro() === false)) {
            if ($this->typeDiplome->isHasSituationPro() === true) {
                $this->etat['situationPro'] = self::INCOMPLET;
                $this->etat['situationProModalite'] = self::VIDE;
                $this->etat['situationProHeures'] = self::VIDE;
            } else {
                $this->etat['situationPro'] = self::NON_CONCERNE;
            }
        } elseif ($this->parcours->isHasSituationPro() === true) {
            if ($this->parcours->getSituationProText() === null || trim($this->parcours->getSituationProText()) === '') {
                $this->etat['situationProModalite'] = self::INCOMPLET;
                $this->etat['situationPro'] = self::INCOMPLET;
            } else {
                $this->etat['situationProModalite'] = self::COMPLET;
            }
            if ($this->parcours->getNbHeuresSituationPro() === 0.0 || $this->parcours->getNbHeuresSituationPro() === null) {
                $this->etat['situationProHeures'] = self::INCOMPLET;
                $this->etat['situationPro'] = self::INCOMPLET;
            } else {
                $this->etat['situationProHeures'] = self::COMPLET;
            }

            if ($this->etat['situationProModalite'] === self::COMPLET && $this->etat['situationProHeures'] === self::COMPLET) {
                $this->etat['situationPro'] = self::COMPLET;
            }
        } else {
            $this->etat['situationPro'] = self::NON_CONCERNE;
        }


        if (($this->parcours->isHasMemoire() === null || $this->parcours->isHasMemoire() === false)) {
            if ($this->typeDiplome->isHasMemoire() === true) {
                $this->etat['memoire'] = self::INCOMPLET;
                $this->etat['memoireModalite'] = self::VIDE;
            } else {
                $this->etat['memoire'] = self::NON_CONCERNE;
            }
        } elseif ($this->parcours->isHasMemoire() === true) {
            if ($this->parcours->getMemoireText() === null || trim($this->parcours->getMemoireText()) === '') {
                $this->etat['memoireModalite'] = self::INCOMPLET;
                $this->etat['memoire'] = self::INCOMPLET;
            } else {
                $this->etat['memoireModalite'] = self::COMPLET;
                $this->etat['memoire'] = self::COMPLET;
            }
        } else {
            $this->etat['memoire'] = self::NON_CONCERNE;
        }

        //onglet 3
        if ($this->typeDiplome->isClassique() === false) {
            $this->etat['competences'] = self::NON_CONCERNE;
            $this->etat['structure'] = self::NON_CONCERNE;
            // Formation non classique : la structure est portée par le PDF de maquette.
            $this->etat['maquette'] = $this->parcours->getMaquettePdf() !== null ? self::COMPLET : self::VIDE;
        } elseif ($this->typeDiplome->getLibelleCourt() !== 'BUT') {
            $this->etat['competences'] = $this->parcours->getBlocCompetences()->count() > 0 ? self::COMPLET : self::VIDE;

            foreach ($this->parcours->getBlocCompetences() as $blocCompetence) {
                $this->bccs[$blocCompetence->getId()]['texte'] = $blocCompetence->display();
                $this->bccs[$blocCompetence->getId()]['etat'] = $blocCompetence->getCompetences()->count() > 0 ? self::COMPLET : self::VIDE;
                if ($this->bccs[$blocCompetence->getId()]['etat'] === self::VIDE) {
                    $this->etat['competences'] = self::INCOMPLET;
                }
            }

            // onglet 4
            ValideStructure::valideStructure($this->parcours);
            $this->etat['structure'] = ValideStructure::getStructure();
        } else {
            $this->etat['competences'] = $this->parcours->getFormation()?->getButCompetences()->count() > 0 ? self::COMPLET : self::VIDE;
            ValideStructure::valideStructureBut($this->parcours);
            $this->etat['structure'] = ValideStructure::getStructure();
        }
        $this->etat['structure'] = ValideStructure::getStructure();

        // onglet 5
        $this->etat['preRequis'] = $this->nonVide($this->parcours->getPrerequis());
        $this->etat['coordSecretariat'] = $this->parcours->getContacts()->count() > 0 ? self::COMPLET : self::VIDE;


        if ($this->parcours->isParcoursDefaut() === false) {
            $this->etat['composanteInscription'] = $this->parcours->getComposanteInscription() !== null ? self::COMPLET : self::VIDE;
            $this->etat['regimeInscription'] = count($this->parcours->getRegimeInscription()) > 0 ? self::COMPLET : self::VIDE;
            $this->etat['modaliteAlternance'] = self::NON_CONCERNE;
            foreach ($this->parcours->getRegimeInscription() as $regimeInscription) {
                if ($regimeInscription !== RegimeInscriptionEnum::FI && $regimeInscription !== RegimeInscriptionEnum::FC) {
                    $this->etat['modaliteAlternance'] = $this->nonVide($this->parcours->getModalitesAlternance());
                    $this->etat['regimeInscription'] = $this->etat['modaliteAlternance'] === self::COMPLET ? self::COMPLET : self::INCOMPLET;
                }
            }
        }

        // onglet 6

        $this->etat['poursuitesEtudes'] = $this->nonVide($this->parcours->getPoursuitesEtudes());
        $this->etat['debouches'] = $this->nonVide($this->parcours->getDebouches());
        // Le code ROME n'est pas obligatoire pour les formations non classiques (le
        // LHÉO fournit R0000 par défaut) : on ne pénalise donc pas leur complétude.
        if (($this->typeDiplome->isClassique() ?? true) === false) {
            $this->etat['codeRome'] = self::NON_CONCERNE;
        } else {
            $this->etat['codeRome'] = count($this->parcours->getCodesRome()) > 0 ? self::COMPLET : self::VIDE;
        }


        return $this;
    }


    public function verifierEtat($etat): bool
    {
        foreach ($etat as $key => $element) {
            if (is_array($element)) {
                if (!$this->verifierEtat($element)) {
                    return false;
                }
            } elseif ($element === self::INCOMPLET || $element === self::VIDE || $element === self::ERREUR) {
                return false;
            }
        }

        return true;
    }

    public function isParcoursValide(): bool
    {
        return $this->verifierEtat($this->etat);
    }

    public function calculPourcentage(): float
    {
        return $this->calcul()->calcul();
    }

    public function calcul(): Remplissage
    {
        $remplissage = new Remplissage();
        return $this->calculRemplissageFromEtat($this->etat, $remplissage);
    }

    public function calculRemplissageFromEtat(array $etat, Remplissage $remplissage): Remplissage
    {
        foreach ($etat as $element) {
            if (is_array($element)) {
                $this->calculRemplissageFromEtat($element, $remplissage);
            } elseif (
                $element === self::COMPLET ||
                $element === self::INCOMPLET ||
                $element === self::VIDE ||
                $element === self::ERREUR
            ) {
                $remplissage->add($element === self::COMPLET ? 1 : 0);
            }
        }

        return $remplissage;
    }

    public function valideFichesParcours(array $process): array
    {
        $tFiches = [];
        //vérifier que les parcours sont validés
        foreach ($this->parcours->getSemestreParcours() as $semestreParcour) {
            if ($semestreParcour->getSemestre()?->getSemestreRaccroche() !== null) {
                $sem = $semestreParcour->getSemestre()?->getSemestreRaccroche()->getSemestre();
            } else {
                $sem = $semestreParcour->getSemestre();
            }
            if ($sem !== null) {
                foreach ($sem->getUes() as $ue) {
                    if ($ue->getUeRaccrochee() !== null) {
                        $ue = $ue->getUeRaccrochee()?->getUe();
                    }

                    foreach ($ue->getElementConstitutifs() as $ec) {
                       if ($ec->getEcEnfants()->count() === 0 && $ec->getNatureUeEc()?->isLibre() === false) {
                            $tFiches[$ec->getId()]['ec'] = $ec;
                            $tFiches[$ec->getId()]['fiche'] = $ec->getFicheMatiere();
                        }
                    }
                }
            }
        }

        return $tFiches;
    }
}
