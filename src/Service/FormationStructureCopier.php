<?php

namespace App\Service;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Ue;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Copie « en place » le contenu d'une UE d'une version de formation vers son
 * homologue d'une autre année (sens source → cible). Réutilisé par l'outil de
 * comparaison entre années.
 *
 * Périmètre actuel : UE appariées (présentes dans les deux années). Pour chaque
 * EC apparié (via ecOrigineCopie), on copie le libellé, les volumes horaires,
 * les ECTS et les MCCC. Les valeurs portées par une fiche matière partagée entre
 * plusieurs parcours ne sont volontairement pas écrasées (voir copy-on-write,
 * sous-lot suivant) : elles sont reportées dans le rapport pour avertissement.
 */
class FormationStructureCopier
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Copie le contenu de l'UE source dans l'UE cible (EC appariés + UE enfants).
     */
    public function copyUe(Ue $ueSource, Ue $ueCible, CopyStructureReport $report): void
    {
        foreach ($ueCible->getElementConstitutifs() as $ecCible) {
            $ecSource = $this->findCounterpartEc($ecCible, $ueSource);
            if ($ecSource !== null) {
                $this->copyEc($ecSource, $ecCible, $report);
            }
        }

        // UE enfants appariées par ueOrigineCopie
        foreach ($ueCible->getUeEnfants() as $enfantCible) {
            $enfantSource = $this->findCounterpartUe($enfantCible, $ueSource->getUeEnfants()->toArray());
            if ($enfantSource !== null) {
                $this->copyUe($enfantSource, $enfantCible, $report);
            }
        }
    }

    private function copyEc(ElementConstitutif $s, ElementConstitutif $c, CopyStructureReport $report): void
    {
        // Champs portés directement par l'EC
        if ($s->getLibelle() !== null) {
            $c->setLibelle($s->getLibelle());
        }
        $c->setEcts($s->getEcts());
        $c->setVolumeCmPresentiel($s->getVolumeCmPresentiel() ?? 0.0);
        $c->setVolumeTdPresentiel($s->getVolumeTdPresentiel() ?? 0.0);
        $c->setVolumeTpPresentiel($s->getVolumeTpPresentiel() ?? 0.0);
        $c->setVolumeCmDistanciel($s->getVolumeCmDistanciel() ?? 0.0);
        $c->setVolumeTdDistanciel($s->getVolumeTdDistanciel() ?? 0.0);
        $c->setVolumeTpDistanciel($s->getVolumeTpDistanciel() ?? 0.0);
        $c->setVolumeTe($s->getVolumeTe() ?? 0.0);
        $c->setSansHeure($s->isSansHeure());
        $c->setHeuresSpecifiques($s->isHeuresSpecifiques());
        $c->setEctsSpecifiques($s->isEctsSpecifiques());

        $this->copyMcccs($s, $c);

        // Champs portés par la fiche matière (libellé affiché, heures BUT…)
        $ficheCible  = $c->getFicheMatiere();
        $ficheSource = $s->getFicheMatiere();
        if ($ficheCible !== null && $ficheSource !== null) {
            if ($this->ficheEstPartagee($ficheCible, $c)) {
                // Fiche mutualisée : on ne l'écrase pas pour ne pas impacter les
                // autres parcours. Sera traité par copy-on-write (sous-lot suivant).
                $report->fichesPartageesIgnorees[] = $ficheCible->getLibelle() ?? ($c->getLibelle() ?? 'EC');
            } else {
                $this->copyFicheScalars($ficheSource, $ficheCible);
            }
        }

        $report->ecCopies++;
    }

    /**
     * Remplace la collection de MCCC de l'EC cible par des clones de ceux de la
     * source. L'orphanRemoval supprime les anciens à la sauvegarde.
     */
    private function copyMcccs(ElementConstitutif $s, ElementConstitutif $c): void
    {
        foreach ($c->getMcccs()->toArray() as $ancien) {
            $c->removeMccc($ancien);
        }
        foreach ($s->getMcccs() as $mccc) {
            $clone = clone $mccc;
            $clone->setFicheMatiere(null);
            $clone->setEc($c);
            $c->addMccc($clone);
            $this->em->persist($clone);
        }
    }

    private function copyFicheScalars(FicheMatiere $s, FicheMatiere $c): void
    {
        if ($s->getLibelle() !== null) {
            $c->setLibelle($s->getLibelle());
        }
        $c->setLibelleAnglais($s->getLibelleAnglais());
        $c->setEcts($s->getEcts());
        $c->setVolumeCmPresentiel($s->getVolumeCmPresentiel() ?? 0.0);
        $c->setVolumeTdPresentiel($s->getVolumeTdPresentiel() ?? 0.0);
        $c->setVolumeTpPresentiel($s->getVolumeTpPresentiel() ?? 0.0);
        $c->setVolumeCmDistanciel($s->getVolumeCmDistanciel() ?? 0.0);
        $c->setVolumeTdDistanciel($s->getVolumeTdDistanciel() ?? 0.0);
        $c->setVolumeTpDistanciel($s->getVolumeTpDistanciel() ?? 0.0);
        $c->setVolumeTe($s->getVolumeTe() ?? 0.0);
    }

    /**
     * Une fiche est « partagée » si plusieurs EC pointent dessus, si elle est
     * marquée mutualisée, ou si elle n'appartient pas au parcours de l'EC cible.
     */
    private function ficheEstPartagee(FicheMatiere $fiche, ElementConstitutif $ecCible): bool
    {
        return $fiche->getElementConstitutifs()->count() > 1
            || $fiche->isEnseignementMutualise() === true
            || !$ecCible->isFicheFromParcours();
    }

    private function findCounterpartEc(ElementConstitutif $cible, Ue $ueSource): ?ElementConstitutif
    {
        $sourceById = [];
        foreach ($ueSource->getElementConstitutifs() as $ec) {
            $sourceById[$ec->getId()] = $ec;
        }

        $current = $cible->getEcOrigineCopie();
        $guard = 0;
        while ($current !== null && $guard < 20) {
            if (isset($sourceById[$current->getId()])) {
                return $sourceById[$current->getId()];
            }
            $current = $current->getEcOrigineCopie();
            $guard++;
        }

        return null;
    }

    /**
     * @param Ue[] $candidates
     */
    private function findCounterpartUe(Ue $cible, array $candidates): ?Ue
    {
        $byId = [];
        foreach ($candidates as $ue) {
            $byId[$ue->getId()] = $ue;
        }

        $current = $cible->getUeOrigineCopie();
        $guard = 0;
        while ($current !== null && $guard < 20) {
            if (isset($byId[$current->getId()])) {
                return $byId[$current->getId()];
            }
            $current = $current->getUeOrigineCopie();
            $guard++;
        }

        return null;
    }
}
