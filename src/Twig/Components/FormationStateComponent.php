<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/FormationStateComponent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/07/2024 09:58
 */

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Entity\Formation;
use App\Enums\EtatProcessMentionEnum;
use App\Enums\TypeModificationDpeEnum;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('formation_state')]
class FormationStateComponent
{
    public string $class;
    public array $process;
    public Formation $formation;

    public string $mode = 'liste'; //liste mode classique sur le dashboard, process, dans le header

    public function __construct(
        #[Target('dpeParcours')]
        private readonly WorkflowInterface             $dpeParcoursWorkflow,
        #[Target('changeRf')]
        private readonly WorkflowInterface             $changeRfWorkflow,
    ) {
    }

    public function stateProcess(string $etat): EtatProcessMentionEnum
    {
        /*
         * Etat des fiches
            bleu - au moins une fiche en cours de rédaction et non validée
            orange - réserve sur au moins une fiche ou non conforme
            vert - Toutes les fiches sont validées, pas de soucis
         * Etat des parcours
            bleu - au moins un DPE parcours en cours de rédaction et non validée CFVU (en cours de process)
            orange - réserve sur au moins une fiche DPE parcours ou sans PV associé ou non conforme
            vert - Toutes les fiches DPE parcours sont validées CFVU
         * Etat formation
            bleu - au moins un DPE en cours de rédaction et non validée SES (en cours de process) - vérifier qu'il n'y a pas de modifications de textes en cours
            orange - réserves sur fiche DPE ou non conforme
            vert - fiche DPE validées SES
         * Change RF
            bleu - le RF en cours de modification et non validée CFVU (en cours de process)
            orange - réserve sur le RF ou sans PV associé
            vert - RF est validé CFVU avec PV
         * Publication
            bleu - le DPE (formation ou parcours) en cours de modification et non validée à publier (en cours de process)
            orange - réserve (est-ce utile ?)
            vert - tous les DPE parcours sont à l'état publication + formation OK
         */
        return match ($etat) {
            'fiche_matiere' => $this->getEtatFichesMatieres(),
            'parcours' => $this->getEtatParcours(),
            'formation' => $this->getEtatFormation(),
            'change_rf' => $this->getEtatChangeRf(),
            'publication' => $this->getEtatPublication(),
            default => EtatProcessMentionEnum::WIP,
        };
    }

    private function getEtatParcours(): EtatProcessMentionEnum
    {
        // * Etat des parcours
        //            bleu - au moins un DPE parcours en cours de rédaction et non validée CFVU (en cours de process)
        //            orange - réserve sur au moins une fiche DPE parcours ou sans PV associé ou non conforme
        //            vert - Toutes les fiches DPE parcours sont validées CFVU
        $etatsReserves = [
            'valider_reserve_conseil_cfvu',
            'reserver_parcours_rf',
            'reserver_rf',
            'reserver_dpe_composante',
            'reserver_conseil',
            'reserver_central',
            'valider_reserve_cfvu',
            'valider_reserve_conseil_cfvu',
            'valider_reserve_central_cfvu',
        ];
        list($states, $nbParcours) = $this->getEtatsParcours();

        foreach ($states as $etat => $nb) {
            if ($etat === 'autorisation_saisie' && $nb === $nbParcours) {
                return EtatProcessMentionEnum::NON_FAIT;
            }

            if (in_array($etat, $etatsReserves)) {
                return EtatProcessMentionEnum::RESERVE;
            }

            if (($etat === 'valide_cfvu' || $etat==='publie') && $nb === $nbParcours) {
                return EtatProcessMentionEnum::COMPLETE;
            }
        }


        return EtatProcessMentionEnum::WIP;
    }

    private function getEtatFichesMatieres(): EtatProcessMentionEnum
    {
        //* Etat des fiches
        //            bleu - au moins une fiche en cours de rédaction et non validée
        //            orange - réserve sur au moins une fiche ou non conforme
        //            vert - Toutes les fiches sont validées, pas de soucis
        $nbFiches = 0;
        $nbFichesPubliees = 0;


        foreach ($this->formation->getParcours() as $parcours) {
            //compter le nombre de fiches matières validées
            $etatsFiche = $parcours->getEtatsFichesMatieres();
            $nbFiches += $etatsFiche->nbFiches;
            $nbFichesPubliees += $etatsFiche->nbFichesPubliees;
        }

        if ($nbFiches === 0) {
            return EtatProcessMentionEnum::NON_FAIT;
        }

        if ($nbFiches === $nbFichesPubliees) {
            return EtatProcessMentionEnum::COMPLETE;
        }

        return EtatProcessMentionEnum::WIP;
    }

    private function getEtatFormation(): EtatProcessMentionEnum
    {
        //* Etat formation
        //            bleu - au moins un DPE en cours de rédaction et non validée SES (en cours de process) - vérifier qu'il n'y a pas de modifications de textes en cours
        //            orange - réserves sur fiche DPE ou non conforme
        //            vert - fiche DPE validées SES

        if ($this->getEtatParcours() !== EtatProcessMentionEnum::COMPLETE) {
            return $this->getEtatParcours();
        }

        if ($this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION || $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_TEXTE || $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE || $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_PARCOURS) {
            return EtatProcessMentionEnum::WIP;
        }

        return $this->formation->getRemplissage()->isFull() ? EtatProcessMentionEnum::COMPLETE : EtatProcessMentionEnum::WIP;
    }

    private function getEtatChangeRf(): EtatProcessMentionEnum
    {
        //* Change RF
        //            bleu - le RF en cours de modification et non validée CFVU (en cours de process)
        //            orange - réserve sur le RF ou sans PV associé
        //            vert - RF est validé CFVU avec PV
        foreach ($this->formation->getChangeRves() as $changeRf) {
            $place = array_keys($this->changeRfWorkflow->getMarking($changeRf)->getPlaces())[0];

            if ($place === 'reserve_cfvu' || $place === 'attente_pv') {
                //voir si PV passe dans l'historique
                return EtatProcessMentionEnum::RESERVE;
            }

            if ($place !== 'effectuee') {
                return EtatProcessMentionEnum::WIP;
            }


        }

        return EtatProcessMentionEnum::COMPLETE;
    }

    private function getEtatPublication(): EtatProcessMentionEnum
    {
        // * Publication
        //            bleu - le DPE (formation ou parcours) en cours de modification et non validée à publier (en cours de process)
        //            orange - réserve (est-ce utile ?)
        //            vert - tous les DPE parcours sont à l'état publication + formation OK
        list($states, $nbParcours) = $this->getEtatsParcours();

        foreach ($states as $etat => $nb) {
            if ($etat === 'autorisation_saisie') {
                return EtatProcessMentionEnum::NON_FAIT;
            }

            if ($etat !== 'publie') {
                return EtatProcessMentionEnum::WIP;
            }

            if ($nb === $nbParcours) {
                //todo: vérifier formation
                return EtatProcessMentionEnum::COMPLETE;
            }
        }

        return EtatProcessMentionEnum::WIP;
    }

    private function getEtatsParcours(): array
    {
        $states = [];
        $nbParcours = $this->formation->getParcours()->count();
        //parcours l'ensemble des parcours de la formation et regarde selon l'état
        foreach ($this->formation->getParcours() as $parcours) {
            $objet = GetDpeParcours::getFromParcours($parcours);
            if (null !== $objet) {
                try {
                    $etat = array_keys($this->dpeParcoursWorkflow->getMarking($objet)->getPlaces())[0];
                } catch (Exception $e) {
                    $etat = 'autorisation_saisie';
                }

                if (!array_key_exists($etat, $states)) {
                    $states[$etat] = 0;
                }

                $states[$etat]++;

            }
        }

        ksort($states);
        return array($states, $nbParcours);
    }
}
