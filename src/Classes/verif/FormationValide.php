<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/verif/FormationValide.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/08/2023 13:53
 */

namespace App\Classes\verif;

use App\DTO\Remplissage;
use App\Entity\Formation;
use App\Enums\RegimeInscriptionEnum;

class FormationValide extends AbstractValide
{

    public array $etat = [];

    public function __construct(
        protected Formation $formation)
    {
    }


    public function valideParcours(array $process): array
    {
        $tParcours = [];
        //vérifier que les parcours sont validés
        foreach ($this->formation->getParcours() as $parcours) {
            $tParcours[$parcours->getId()]['parcours'] = $parcours;
            $tParcours[$parcours->getId()]['etat'] = $parcours->getValide();
        }

        return $tParcours;
    }

    public function valideFormation(): FormationValide
    {
       $this->valideOnlyFormation();

        if ($this->formation->isHasParcours() === false && $this->formation->getParcours()->count() === 1) {
            $parcours = $this->formation->getParcours()->first();
            if ($parcours !== false && $parcours->isParcoursDefaut() === true) {
                $this->etat['erreurHasParcours'] = self::COMPLET;
                //validation du parcours
                $parcoursValide = new ParcoursValide($parcours, $this->formation->getTypeDiplome());
                $this->etat['etatParcoursDefaut'] = $parcoursValide->valideParcours();
                $this->etat['valideParcours'] = $parcoursValide->isParcoursValide() === true ? self::COMPLET : self::INCOMPLET;

                //données de la formation si pas de parcours
                $this->etat['objectifsFormation'] = $this->nonVide($this->formation->getObjectifsFormation());
                $this->etat['resultatsAttendus'] = $this->nonVide($this->formation->getResultatsAttendus());
                $this->etat['contenuFormation'] = $this->nonVide($this->formation->getContenuFormation());
                $this->etat['rythmeFormation'] = $this->formation->getRythmeFormation() !== null || $this->nonVide($this->formation->getRythmeFormationTexte()) ? self::COMPLET : self::VIDE;
            } else {
                $this->etat['erreurHasParcours'] = self::ERREUR;
                $this->etat['valideParcours'] = self::INCOMPLET;
                $this->etat['objectifsFormation'] = self::VIDE;
                $this->etat['resultatsAttendus'] = self::VIDE;
                $this->etat['contenuFormation'] = self::VIDE;
                $this->etat['rythmeFormation'] = self::VIDE;
            }
        }

        if ($this->formation->getParcours()->count() === 0) {
            $this->etat['erreurHasParcours'] = self::ERREUR;
            $this->etat['valideParcours'] = self::INCOMPLET;
        }

        return $this;
    }

    public function isFormationValide(): bool
    {
        foreach ($this->etat as $etat) {
            if ($etat === self::VIDE || $etat === self::INCOMPLET) {
                return false;
            }
        }

        if ($this->formation->isHasParcours() === true) {
            foreach ($this->formation->getParcours() as $parcours)
            {
                $parcoursValide = new ParcoursValide($parcours, $this->formation->getTypeDiplome());
                if ($parcoursValide->isParcoursValide() === false) {
                    return false;
                }

//                if (!array_key_exists('valide_parcours_rf', $parcours->getEtatParcours())) {
//                    return false;
//                }
            }
        }

        return true;
    }

    public function calcul(): Remplissage
    {
        $remplissage = new Remplissage();
        return $this->calculRemplissageFromEtat($this->etat, $remplissage);
    }

    function calculRemplissageFromEtat(array $etat, Remplissage $remplissage): Remplissage
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

    public function valideOnlyFormation(): FormationValide
    {
        $this->etat['respFormation'] = $this->formation->getResponsableMention() !== null ? self::COMPLET : self::VIDE;
        $this->etat['localisations'] = $this->formation->getLocalisationMention()->count() > 0 ? self::COMPLET : self::VIDE;
        $this->etat['composantesInscriptions'] = $this->formation->getComposantesInscription()->count() > 0 ? self::COMPLET : self::VIDE;
        $this->etat['regimeInscription'] = count($this->formation->getRegimeInscription()) > 0 ? self::COMPLET : self::VIDE;
        $this->etat['modaliteAlternance'] = self::NON_CONCERNE;
        foreach ($this->formation->getRegimeInscription() as $regimeInscription) {
            if ($regimeInscription !== RegimeInscriptionEnum::FI && $regimeInscription !== RegimeInscriptionEnum::FC) {
                $this->etat['modaliteAlternance'] = $this->nonVide($this->formation->getModalitesAlternance());
                $this->etat['regimeInscription'] = $this->etat['modaliteAlternance'] === self::COMPLET ? self::COMPLET : self::INCOMPLET;
            }
        }
        $this->etat['objectifsFormation'] = $this->nonVide($this->formation->getObjectifsFormation());
        $this->etat['hasParcours'] = $this->formation->isHasParcours() === null ? self::VIDE : self::COMPLET;
        $this->etat['erreurHasParcours'] = $this->formation->isHasParcours() === null ? self::VIDE : self::COMPLET;

        if ($this->formation->isHasParcours() === false and $this->formation->getParcours()->count() > 1) {
            $this->etat['erreurHasParcours'] = self::ERREUR;
            $this->etat['valideParcours'] = self::INCOMPLET;
        }

        return $this;
    }

    public function allSubmitted(): bool
    {
        foreach ($this->formation->getParcours() as $parcours) {
            if (!(array_key_exists('soumis_parcours', $parcours->getEtatParcours()) || array_key_exists('soumis_parcours_rf', $parcours->getEtatParcours()) || array_key_exists('valide_parcours_rf', $parcours->getEtatParcours()))) {
                return false;
            }
        }

        return true;
    }

    public function allValidated(): bool
    {
        foreach ($this->formation->getParcours() as $parcours) {
            if (!(array_key_exists('valide_parcours_rf', $parcours->getEtatParcours()))) {
                return false;
            }
        }

        return true;
    }
}
