<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Parcours/ParcoursTabCompletionChecker.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/01/2026 09:13
 */

namespace App\Service\Parcours;

use App\DTO\TabIssue;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Service\AbstractChecker;

class ParcoursTabCompletionChecker extends AbstractChecker
{

    public function isTabComplete(Parcours $p, string $tabKey): bool
    {
        return count($this->getTabIssues($p, $tabKey)) === 0;
    }

    /** @return TabIssue[] */
    public function getTabIssues(Parcours $p, string $tabKey): array
    {
        return match ($tabKey) {
            'presentation' => $this->presentationIssues($p),
            'descriptif' => $this->descriptifIssues($p),
            'maquette' => $this->maquetteIssues($p), //structure semestre début/fin uniquement
            'admission' => $this->admissionIssues($p),
            'et_apres' => $this->etApresIssues($p),
            default => [],
        };
    }

    /** @return TabIssue[] */
    private function presentationIssues(Parcours $p): array
    {
        $issues = [];

        if ($p->getRespParcours() === null) {
            $issues[] = new TabIssue('parcours_step1[respParcours]', 'Responsable du parcours', 'Sélectionne un responsable.');
        }
        if ($p->getLocalisation() === null) {
            $issues[] = new TabIssue('parcours_step1[localisation]', 'Localisation', 'Choisis une ville.');
        }
        if (!$this->filled($p->getObjectifsParcours())) {
            $issues[] = new TabIssue('parcours_step1[objectifsParcours]', 'Objectifs du parcours', 'Renseigne les objectifs.');
        }
        if (!$this->filled($p->getMotsCles())) {
            $issues[] = new TabIssue('parcours_step1[motsCles]', 'Mots clés', 'Renseigne des mots clés.');
        }
        if (!$this->filled($p->getResultatsAttendus())) {
            $issues[] = new TabIssue('parcours_step1[resultatsAttendus]', 'Résultats attendus', 'Renseigne les résultats attendus.');
        }
        if (!$this->filled($p->getContenuFormation())) {
            $issues[] = new TabIssue('parcours_step1[contenuFormation]', 'Contenu de la formation', 'Décris le contenu.');
        }

        $hasRythme = $p->getRythmeFormation() !== null || $this->filled($p->getRythmeFormationTexte());
        if (!$hasRythme) {
            $issues[] = new TabIssue('parcours_step1[rythmeFormation]', 'Rythme de formation', 'Choisis un rythme ou précise le texte.');
        }

        return $issues;
    }

    /** @return TabIssue[] */
    private function descriptifIssues(Parcours $p): array
    {
        $issues = [];
        $typeDiplome = $p->getTypeDiplome();

        if ($p->getModalitesEnseignement() === null) {
            $issues[] = new TabIssue('parcours_step2[modalitesEnseignement]', 'Modalités d’enseignement', 'Choisis une modalité.');
        }
        // Stage
        if ($typeDiplome?->isHasStage() === true) {
            if ($p->isHasStage() === null) {
                $issues[] = new TabIssue('parcours_step2[hasStage]', 'Stage', 'Indique si un stage est prévu.');
            } elseif ($p->isHasStage() === true) {
                if (!$this->filled($p->getStageText())) {
                    $issues[] = new TabIssue('parcours_step2[stageText]', 'Texte du stage', 'Obligatoire si stage = oui.');
                }
                if ($p->getNbHeuresStages() === null || $p->getNbHeuresStages() <= 0) {
                    $issues[] = new TabIssue('parcours_step2[nbHeuresStages]', 'Heures de stage', 'Indique un volume horaire (>= 0).');
                }
            }
        }

        // Projet
        if ($typeDiplome?->isHasProjet()) {
            if ($p->isHasProjet() === null) {
                $issues[] = new TabIssue('parcours_step2[hasProjet]', 'Projet', 'Indique si un projet est prévu.');
            } elseif ($p->isHasProjet() === true) {
                if (!$this->filled($p->getProjetText())) {
                    $issues[] = new TabIssue('parcours_step2[projetText]', 'Texte du projet', 'Obligatoire si projet = oui.');
                }
                if ($p->getNbHeuresProjet() === null || $p->getNbHeuresProjet() <= 0) {
                    $issues[] = new TabIssue('parcours_step2[nbHeuresProjet]', 'Heures de projet', 'Indique un volume horaire.');
                }
            }
        }

        // Mémoire
        if ($typeDiplome?->isHasMemoire()) {
            if ($p->isHasMemoire() === null) {
                $issues[] = new TabIssue('parcours_step2[hasMemoire]', 'Mémoire', 'Indique si un mémoire est prévu.');
            } elseif ($p->isHasMemoire() === true) {
                if (!$this->filled($p->getMemoireText())) {
                    $issues[] = new TabIssue('parcours_step2[memoireText]', 'Texte du mémoire', 'Obligatoire si mémoire = oui.');
                }
            }
        }

        // Situation pro
        if ($typeDiplome?->isHasSituationPro() === true) {
            if ($p->isHasSituationPro() === null) {
                $issues[] = new TabIssue('parcours_step2[hasSituationPro]', 'Situation professionnelle', 'Indique si une situation pro est prévue.');
            } elseif ($p->isHasSituationPro() === true) {
                if (!$this->filled($p->getSituationProText())) {
                    $issues[] = new TabIssue('parcours_step2[situationProText]', 'Texte situation pro', 'Obligatoire si situation pro = oui.');
                }
                if ($p->getNbHeuresSituationPro() === null || $p->getNbHeuresSituationPro() <= 0) {
                    $issues[] = new TabIssue('parcours_step2[nbHeuresSituationPro]', 'Heures situation pro', 'Indique un volume horaire.');
                }
            }
        }

        return $issues;
    }

    /** @return TabIssue[] */
    private function admissionIssues(Parcours $p): array
    {
        $issues = [];

        if ($p->getNiveauFrancais() === null) {
            $issues[] = new TabIssue('parcours_step5[niveauFrancais]', 'Niveau de français', 'Choisis un niveau.');
        }
        if (!$this->filled($p->getPrerequis())) {
            $issues[] = new TabIssue('parcours_step5[prerequis]', 'Prérequis', 'Renseigne les prérequis.');
        }
        if ($p->getComposanteInscription() === null) {
            $issues[] = new TabIssue('parcours_step5[composanteInscription]', 'Composante d’inscription', 'Choisis une composante.');
        }

        if ($this->filled($p->getModalitesAlternance()) === false && (in_array(RegimeInscriptionEnum::FC_CONTRAT_PRO, $p->getRegimeInscription(), true) || in_array(RegimeInscriptionEnum::FI_APPRENTISSAGE, $p->getRegimeInscription(), true))) {
            $issues[] = new TabIssue('parcours_step5[modalitesAlternance]', 'Modalités d\'alternance', 'Vous avez indiquez FC en contrat pro ou FI en apprentissage, vous devez préciser les modalités.');
        }

        if ($p->getContacts()->count() === 0) {
            $issues[] = new TabIssue('parcours_step5[coordSecretariat]', 'Coordonnées secrétariat', 'Renseigne les coordonnées.');
        }
        if (!$this->filled($p->getModalitesAdmission())) {
            $issues[] = new TabIssue('parcours_step5[modalitesAdmission]', 'Modalités d’admission', 'Renseigne les modalités.');
        }

        return $issues;
    }

    /** @return TabIssue[] */
    private function etApresIssues(Parcours $p): array
    {
        $issues = [];

        if (!$this->filled($p->getPoursuitesEtudes())) {
            $issues[] = new TabIssue('parcours_step6[poursuitesEtudes]', 'Poursuites d’études', 'Renseigne les poursuites d’études.');
        }
        if (!$this->filled($p->getDebouches())) {
            $issues[] = new TabIssue('parcours_step6[debouches]', 'Débouchés', 'Renseigne les débouchés.');
        }

        return $issues;
    }

    private function maquetteIssues(Parcours $p): array
    {
        $issues = [];

        if (!$this->filled($p->getSemestreDebut())) {
            $issues[] = new TabIssue('parcours_step3[semestreDebut]', 'Semestre de départ', 'Renseignez le semestre de départ du parcours (ex: S1 => 1, ...).');
        }
        if (!$this->filled($p->getSemestreFin())) {
            $issues[] = new TabIssue('parcours_step3[semestreFin]', 'Semestre de fin', 'Renseignez le semestre de fin du parcours (ex: S6 => 6, ...).');
        }

        return $issues;
    }

}
