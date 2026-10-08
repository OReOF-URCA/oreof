<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/FicheMatiere/FicheMatiereTabCompletionChecker.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/02/2026 22:01
 */

namespace App\Service\FicheMatiere;

use App\DTO\TabIssue;
use App\Entity\FicheMatiere;
use App\Enums\RegimeInscriptionEnum;
use App\Service\AbstractChecker;

class FicheMatiereTabCompletionChecker extends AbstractChecker
{
    public function isTabComplete(FicheMatiere $p, string $tabKey): bool
    {
        return count($this->getTabIssues($p, $tabKey)) === 0;
    }

    /** @return TabIssue[] */
    public function getTabIssues(FicheMatiere $p, string $tabKey): array
    {
        return match ($tabKey) {
            'identite' => $this->identiteIssues($p),
            'presentation' => $this->presentationIssues($p),
            'mutualisation' => $this->mutualisationIssues($p),
            'competences' => $this->competencesIssues($p),
            'volumes_horaires' => $this->volumesHorairesIssues($p),
            'mccc' => $this->mcccIssues($p),
            default => [],
        };
    }

    private function identiteIssues(FicheMatiere $p): array
    {
        $issues = [];

        //        if (!$this->filled($p->getSigle())) {
        //            $issues[] = new TabIssue('fiche_matiere_step1[sigle]', 'Sigle de la fiche matière', 'Vous devez renseigner un sigle pour la fiche matière.');
        //        }

        if (!$this->filled($p->getLibelle())) {
            $issues[] = new TabIssue('fiche_matiere_step1[libelle]', 'Libellé', 'Vous devez renseigner un libellé pour la fiche matière.');
        }
        if (!$this->filled($p->getLibelleAnglais())) {
            $issues[] = new TabIssue('fiche_matiere_step1[libelleAnglais]', 'Libellé en anglais', 'Vous devez décrire le libellé en anglais.');
        }

        return $issues;
    }

    private function presentationIssues(FicheMatiere $p): array
    {
        $issues = [];

        if (!$this->filled($p->getDescription())) {
            $issues[] = new TabIssue('fiche_matiere_step2[description]', 'Description de la fiche matière', 'Vous devez renseigner une description.');
        }
        if (!$this->filled($p->getObjectifs())) {
            $issues[] = new TabIssue('fiche_matiere_step2[objectifs]', 'Objectifs de la fiche matière', 'Vous devez renseigner les objectifs.');
        }

        if ($p->getLangueSupport()->count() === 0) {
            $issues[] = new TabIssue('fiche_matiere_step2[langueSupport][]', 'Langue des supports', 'Vous devez renseigner au moins une langue pour les supports.');
        }

        if ($p->getLangueDispense()->count() === 0) {
            $issues[] = new TabIssue('fiche_matiere_step2[langueDispense][]', 'Langue des cours', 'Vous devez renseigner au moins une langue pour les cours.');
        }

        return $issues;
    }

    public function mutualisationIssues(FicheMatiere $p): array
    {
        $issues = [];

        if ($p->isEnseignementMutualise() === null) {
            $issues[] = new TabIssue('fiche_matiere_step1b[enseignementMutualise]', 'Enseignement mutualisé', 'Vous devez indiquer si l\'enseignement est mutualisé ou non.');
        }

        return $issues;
    }

    private function competencesIssues(FicheMatiere $p): array
    {
        $issues = [];
        $isBut = $p->getParcours()?->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT';

        if ($isBut) {
            if ($p->getApprentissagesCritiques()->count() === 0) {
                $issues[] = new TabIssue('fiche_matiere_step3[apprentissagesCritiques]', 'Apprentissages critiques', 'Vous devez renseigner au moins un apprentissage critique.');
            }
        } else {
            if ($p->getCompetences()->count() === 0) {
                $issues[] = new TabIssue('fiche_matiere_step3[competences]', 'Compétences', 'Vous devez renseigner au moins une compétence.');
            }
        }

        return $issues;
    }

    private function volumesHorairesIssues(FicheMatiere $f): array
    {
        $issues = [];

        if ($f->isHorsDiplome() || $f->getParcours()?->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT') {
            if ($f->isSansHeures() === null) {
                $issues[] = new TabIssue('fiche_matiere_step4[horaires]', 'Volume horaire avec/sans heure', 'Vous devez préciser si la fiche a des heures ou non.');
            }

            // vérifier que la somme des heures de la fiche est > 0
            if ($f->getTotalHeures() <= 0 && $f->isSansHeures() === false) {
                $issues[] = new TabIssue('fiche_matiere_step4[horaires]', 'Volume horaire', 'Vous devez préciser un volume horaire > 0 au total.');
            }

            if ($f->getTotalHeures() > 0 && $f->isSansHeures() === true) {
                $issues[] = new TabIssue('fiche_matiere_step4[horaires]', 'Volume horaire incohérent', 'Vous avez indiqué une fiche matière sans heures, mais avec un volume horaire > 0.');
            }
        }

        return $issues;
    }

    /** @return TabIssue[] */
    private function mcccIssues(FicheMatiere $p): array
    {
        $issues = [];

        return $issues;
    }
}
