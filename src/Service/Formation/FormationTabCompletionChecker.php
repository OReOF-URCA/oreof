<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Formation/FormationTabCompletionChecker.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/02/2026 16:19
 */

namespace App\Service\Formation;

use App\DTO\TabIssue;
use App\Entity\Formation;
use App\Enums\RegimeInscriptionEnum;
use App\Service\AbstractChecker;

class FormationTabCompletionChecker extends AbstractChecker
{
    public function isTabComplete(Formation $p, string $tabKey): bool
    {
        return count($this->getTabIssues($p, $tabKey)) === 0;
    }

    /** @return TabIssue[] */
    public function getTabIssues(Formation $p, string $tabKey): array
    {
        return match ($tabKey) {
            'presentation' => $this->presentationIssues($p),
            'localisation' => $this->localisationIssues($p),
            'structure' => $this->structureIssues($p),
            default => [],
        };
    }

    /** @return TabIssue[] */
    private function presentationIssues(Formation $p): array
    {
        $issues = [];

        if (!$this->filled($p->getObjectifsFormation())) {
            $issues[] = new TabIssue('formation_step2[objectifsFormation]', 'Objectif de la formation', 'Vous devez renseigner les objectifs de la formation.');
        }

        if (!$this->filled($p->getResultatsAttendus())) {
            $issues[] = new TabIssue('formation_step2[resultatsAttendus]', 'Résultats attendus', 'Vous devez renseigner les résultats attendus pour la formation.');
        }
        if (!$this->filled($p->getContenuFormation())) {
            $issues[] = new TabIssue('formation_step2[contenuFormation]', 'Contenu de la formation', 'Vous devez décrire le contenu de la formation et sa structuration.');
        }

        $hasRythme = $p->getRythmeFormation() !== null || $this->filled($p->getRythmeFormationTexte());
        if (!$hasRythme) {
            $issues[] = new TabIssue('formation_step2[rythmeFormationTexte]', 'Rythme de formation', 'Vous devez choisir un rythme de formation ou préciser les modalités dans le texte libre.');
        }
//todo: sauvegarder les issues dans le tabState ?
        return $issues;
    }

    /** @return TabIssue[] */
    private function localisationIssues(Formation $f): array
    {
        $issues = [];

        if ($f->getResponsableMention() === null) {
            $issues[] = new TabIssue('formation_step1[responsableMention]', 'Responsable de la mention', 'Vous devez renseigner un responsable pour la mention est obligatoire.');
        }

        if ($f->getLocalisationMention()->count() === 0) {
            $issues[] = new TabIssue('formation_step1[localisationMention]', 'Localisation de la mention', 'Vous devez renseigner uu moins une localisation pour la mention.');
        }

        if ($f->getComposantesInscription()->count() === 0) {
            $issues[] = new TabIssue('formation_step1[composantesInscription]', 'Composante d\'inscription', 'Vous devez renseigner au moins une composante d\'inscription.');
        }

        if (count($f->getRegimeInscription()) === 0) {
            $issues[] = new TabIssue('formation_step1[regimeInscription]', 'Régime d\'inscription', 'Au moins un régime d\'inscription est obligatoire.');
        }

        //selon le type de régime, texte obligatoire

        if ($this->filled($f->getModalitesAlternance()) === false && (in_array(RegimeInscriptionEnum::FC_CONTRAT_PRO, $f->getRegimeInscription(), true) || in_array(RegimeInscriptionEnum::FI_APPRENTISSAGE, $f->getRegimeInscription(), true))) {
            $issues[] = new TabIssue('formation_step1[modalitesAlternance]', 'Modalités d\'alternance', 'Vous avez indiquez FC en contrat pro ou FI en apprentissage, vous devez préciser les modalités.');
        }

        return $issues;
    }

    /** @return TabIssue[] */
    private function structureIssues(Formation $f): array
    {
        $issues = [];

        // vérifier si hasParcours est oui ou non
        // si oui, alors au moins un parcours de saisie, si non alors "parcours par défaut" doit exister, si aucun des deux message d'erreur

        if ($f->isHasParcours() === null) {
            $issues[] = new TabIssue('formation_step3[hasParcours]', 'Parcours', 'Vous devez indiquer si la formation comporte des parcours ou pas. Si elle n\'en comporte pas, indiquez non, un parcours par défaut sera créé.');
        }

        if ($f->isHasParcours() && $f->getParcours()->count() === 0) {
            $issues[] = new TabIssue('formation_step3[hasParcours]', 'Parcours', 'Vous avez indiqué que la formation comporte des parcours, vous devez en créer au moins un.');
        }

        if (!$f->isHasParcours() && $f->getParcours()->count() === 0) {
            $issues[] = new TabIssue('formation_step3[hasParcours]', 'Parcours', 'Vous devez indiquer si la formation comporte des parcours ou pas. Si elle n\'en comporte pas, un parcours par défaut sera créé.');
        }

        return $issues;
    }
}
