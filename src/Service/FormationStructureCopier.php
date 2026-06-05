<?php

namespace App\Service;

use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\Ue;
use DateTime;
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
     * Copie le contenu de l'UE source dans l'UE cible :
     *  - EC appariés : mise à jour en place ;
     *  - EC présents uniquement dans la source : recréés (en cascade avec leurs
     *    enfants, MCCC et fiche) ;
     *  - UE enfants appariées : récursion.
     */
    public function copyUe(Ue $ueSource, Ue $ueCible, Parcours $parcoursCible, CopyStructureReport $report): void
    {
        $ctx = $this->buildContext($parcoursCible);

        $matchedSourceIds = [];
        foreach ($ueCible->getElementConstitutifs() as $ecCible) {
            $ecSource = $this->findCounterpartEc($ecCible, $ueSource);
            if ($ecSource !== null) {
                $matchedSourceIds[$ecSource->getId()] = true;
                $this->copyEc($ecSource, $ecCible, $report);
            }
        }

        // EC de premier niveau présents uniquement dans la source → recréation
        foreach ($ueSource->getElementConstitutifs() as $ecSource) {
            if ($ecSource->getEcParent() !== null) {
                continue; // les enfants sont recréés via leur parent
            }
            if (!isset($matchedSourceIds[$ecSource->getId()])) {
                $this->recreateEc($ecSource, $ueCible, $ctx, $report);
            }
        }

        // UE enfants appariées par ueOrigineCopie
        foreach ($ueCible->getUeEnfants() as $enfantCible) {
            $enfantSource = $this->findCounterpartUe($enfantCible, $ueSource->getUeEnfants()->toArray());
            if ($enfantSource !== null) {
                $this->copyUe($enfantSource, $enfantCible, $parcoursCible, $report);
            }
        }
    }

    /**
     * Recrée dans la formation cible un parcours présent uniquement dans la
     * source (non répercuté). Clone la coquille (parcours, DPE, blocs/compétences,
     * semestres) puis les UE en cascade (EC + MCCC + fiche). Les entités de
     * niveau formation (ButCompétence/Niveau/ApprentissageCritique) sont reliées
     * à celles existantes de la formation cible. Mutualisations semestre/UE et
     * contacts exclus (v1).
     */
    public function recreateParcours(Parcours $source, Formation $formationCible, CopyStructureReport $report): void
    {
        $now = new DateTime('now');
        $campagne = $this->campagneDeLaFormation($formationCible);

        // 1. Parcours
        $newParcours = clone $source;
        $newParcours->setFormation($formationCible);
        $newParcours->setParcoursOrigineCopie($this->boutDeLignee(Parcours::class, 'parcoursOrigineCopie', $source));
        $newParcours->setCreated($now);
        $newParcours->setUpdated($now);
        $this->em->persist($newParcours);

        // 2. DPE (pour que le parcours apparaisse dans la campagne cible)
        foreach ($source->getDpeParcours() as $dpe) {
            $newDpe = clone $dpe;
            $newDpe->setParcours($newParcours);
            $newDpe->setFormation($formationCible);
            $newDpe->setCampagneCollecte($campagne);
            $newDpe->setCreated($now);
            $this->em->persist($newDpe);
        }

        // 3. Blocs de compétences + compétences (niveau parcours)
        $newCompetences = [];
        foreach ($source->getBlocCompetences() as $bloc) {
            $newBloc = clone $bloc;
            if ($bloc->getParcours() !== null) {
                $newBloc->setParcours($newParcours);
            }
            if ($bloc->getFormation() !== null) {
                $newBloc->setFormation($formationCible);
            }
            $newBloc->setBlocCompetenceOrigineCopie($bloc);
            $this->em->persist($newBloc);

            foreach ($bloc->getCompetences() as $comp) {
                $newComp = clone $comp;
                $newComp->setBlocCompetence($newBloc);
                $newComp->setCompetenceOrigineCopie($comp);
                $this->em->persist($newComp);
                $newCompetences[] = $newComp;
            }
        }

        // 4. Contexte : compétences = celles qu'on vient de créer ; apprentissages
        //    critiques = ceux (niveau formation) déjà présents dans la cible.
        $ctx = new CloneContext();
        $ctx->parcoursCible = $newParcours;
        $ctx->campagne = $campagne;
        $ctx->compMap = $this->buildOrigineMap($newCompetences, fn($c) => $c->getCompetenceOrigineCopie());
        $cibleAppCrits = [];
        foreach ($formationCible->getButCompetences() as $butComp) {
            foreach ($butComp->getButNiveaux() as $niveau) {
                foreach ($niveau->getButApprentissageCritiques() as $appCrit) {
                    $cibleAppCrits[] = $appCrit;
                }
            }
        }
        $ctx->appCritMap = $this->buildOrigineMap($cibleAppCrits, fn($a) => $a->getButApprentissageCritiqueOrigineCopie());

        // 5. Semestres + SemestreParcours
        $semestreMap = [];
        foreach ($source->getSemestreParcours() as $sp) {
            $srcSem = $sp->getSemestre();
            if ($srcSem === null) {
                continue;
            }
            if (!isset($semestreMap[$srcSem->getId()])) {
                $newSem = clone $srcSem;
                $newSem->setSemestreRaccroche(null);
                $newSem->setSemestreOrigineCopie($this->boutDeLignee(Semestre::class, 'semestreOrigineCopie', $srcSem));
                $this->em->persist($newSem);
                $semestreMap[$srcSem->getId()] = $newSem;
            }
            $newSp = clone $sp;
            $newSp->setParcours($newParcours);
            $newSp->setSemestre($semestreMap[$srcSem->getId()]);
            $this->em->persist($newSp);
        }

        // 6. UE de premier niveau, en cascade (EC + MCCC + fiche + UE enfants)
        $semestresTraites = [];
        foreach ($source->getSemestreParcours() as $sp) {
            $srcSem = $sp->getSemestre();
            if ($srcSem === null || isset($semestresTraites[$srcSem->getId()])) {
                continue;
            }
            $semestresTraites[$srcSem->getId()] = true;
            $newSem = $semestreMap[$srcSem->getId()];
            foreach ($srcSem->getUes() as $ue) {
                if ($ue->getUeParent() !== null) {
                    continue;
                }
                $this->cloneUe($ue, $newParcours, $newSem, null, $ctx, $report);
            }
        }

        $report->parcoursCrees++;
    }

    private function campagneDeLaFormation(Formation $formation): ?CampagneCollecte
    {
        foreach ($formation->getParcours() as $parcours) {
            $dpe = $parcours->getDpeParcours()->first();
            if ($dpe && $dpe->getCampagneCollecte() !== null) {
                return $dpe->getCampagneCollecte();
            }
        }

        return null;
    }

    /**
     * Recrée dans la cible une UE présente uniquement dans la source (non
     * répercutée), en la plaçant dans le semestre cible apparié. Clone en
     * cascade : EC (+ enfants, MCCC, fiche) et UE enfants.
     */
    public function recreateUe(Ue $ueSource, Parcours $parcoursCible, CopyStructureReport $report): void
    {
        $semestreCible = $this->findSemestreCible($ueSource->getSemestre(), $parcoursCible);
        if ($semestreCible === null) {
            // Le semestre n'existe pas dans la cible : recréation de semestre non
            // gérée en v1 (nécessiterait SemestreParcours/mutualisations).
            $report->uesNonRecreees[] = $ueSource->getLibelle() ?? 'UE';
            return;
        }

        $ctx = $this->buildContext($parcoursCible);
        $this->cloneUe($ueSource, $parcoursCible, $semestreCible, null, $ctx, $report);
    }

    private function cloneUe(Ue $source, Parcours $parcoursCible, Semestre $semestreCible, ?Ue $ueParent, CloneContext $ctx, CopyStructureReport $report): Ue
    {
        $clone = clone $source;
        $clone->setUeParent($ueParent);
        $clone->setUeRaccrochee(null);
        $clone->setSemestre($semestreCible);
        $clone->setUeOrigineCopie($this->boutDeLignee(Ue::class, 'ueOrigineCopie', $source));
        $this->em->persist($clone);

        // EC de premier niveau (avec leurs enfants, MCCC, fiche)
        foreach ($source->getElementConstitutifs() as $ec) {
            if ($ec->getEcParent() !== null) {
                continue;
            }
            $this->recreateEc($ec, $clone, $ctx, $report);
        }

        // UE enfants en cascade
        foreach ($source->getUeEnfants() as $enfantSource) {
            $this->cloneUe($enfantSource, $parcoursCible, $semestreCible, $clone, $ctx, $report);
        }

        $report->uesCreees++;

        return $clone;
    }

    /**
     * Trouve, parmi les semestres de la cible, celui apparié au semestre source
     * (via la chaîne semestreOrigineCopie).
     */
    private function findSemestreCible(?Semestre $semestreSource, Parcours $parcoursCible): ?Semestre
    {
        if ($semestreSource === null) {
            return null;
        }

        foreach ($parcoursCible->getSemestreParcours() as $sp) {
            $cibleSem = $sp->getSemestre();
            $current = $cibleSem;
            $guard = 0;
            while ($current !== null && $guard < 20) {
                if ($current->getId() === $semestreSource->getId()) {
                    return $cibleSem;
                }
                $current = $current->getSemestreOrigineCopie();
                $guard++;
            }
        }

        return null;
    }

    // ── Recréation (clonage) ─────────────────────────────────────────────────

    /**
     * Recrée un EC source-only dans l'UE cible : clone l'EC, sa fiche (clone
     * fidèle), ses MCCC et ses EC enfants (cascade). Relinke les compétences
     * vers l'année cible. Mutualisations exclues (v1).
     */
    private function recreateEc(ElementConstitutif $source, Ue $ueCible, CloneContext $ctx, CopyStructureReport $report): ElementConstitutif
    {
        $clone = clone $source;
        $clone->prepareCloneForNewAnnee(); // réinitialise les compétences
        $clone->setEcParent(null);
        $clone->setUe($ueCible);
        $clone->setParcours($ctx->parcoursCible);
        $clone->setEcOrigineCopie($this->boutDeLignee(ElementConstitutif::class, 'ecOrigineCopie', $source));

        if ($source->getFicheMatiere() !== null) {
            $clone->setFicheMatiere($this->resolveFiche($source->getFicheMatiere(), $ctx, $report));
        }

        foreach ($source->getCompetences() as $competence) {
            $cibleComp = $ctx->compMap[$competence->getId()] ?? null;
            if ($cibleComp !== null) {
                $clone->addCompetence($cibleComp);
            }
        }

        $this->em->persist($clone);

        // MCCC portés par l'EC (côté propriétaire = Mccc::ec)
        foreach ($source->getMcccs() as $mccc) {
            $mcccClone = clone $mccc;
            $mcccClone->setFicheMatiere(null);
            $mcccClone->setEc($clone);
            $this->em->persist($mcccClone);
        }

        // EC enfants (cas « choix ») en cascade
        foreach ($source->getEcEnfants() as $enfant) {
            $enfantClone = $this->recreateEc($enfant, $ueCible, $ctx, $report);
            $enfantClone->setEcParent($clone);
            $this->em->persist($enfantClone);
        }

        $report->ecCrees++;

        return $clone;
    }

    /**
     * Résout la fiche d'un EC recréé : si une fiche issue de la même origine
     * existe déjà dans l'année cible (cas mutualisé), on la **réutilise** plutôt
     * que d'en cloner une nouvelle (et on crée le lien de mutualisation si elle
     * appartient à un autre parcours). Sinon, clone fidèle.
     */
    private function resolveFiche(FicheMatiere $source, CloneContext $ctx, CopyStructureReport $report): FicheMatiere
    {
        $existante = $this->findFicheCibleExistante($source, $ctx);
        if ($existante !== null) {
            if ($existante->getParcours()?->getId() !== $ctx->parcoursCible->getId()) {
                $this->ensureMutualisation($existante, $ctx->parcoursCible);
                $existante->setEnseignementMutualise(true);
            }
            $report->fichesReutilisees++;

            return $existante;
        }

        return $this->cloneFiche($source, $ctx, $report);
    }

    /**
     * Suit le pointeur de copie (forward) de la fiche source jusqu'à trouver,
     * le cas échéant, sa version déjà présente dans la campagne cible.
     */
    private function findFicheCibleExistante(FicheMatiere $source, CloneContext $ctx): ?FicheMatiere
    {
        if ($ctx->campagne === null) {
            return null;
        }

        $current = $source;
        $guard = 0;
        while ($current !== null && $guard < 20) {
            if ($current->getCampagneCollecte()?->getId() === $ctx->campagne->getId()) {
                return $current;
            }
            $current = $current->getFicheMatiereCopieAnneeUniversitaire();
            $guard++;
        }

        return null;
    }

    private function ensureMutualisation(FicheMatiere $fiche, Parcours $parcours): void
    {
        foreach ($fiche->getFicheMatiereParcours() as $mutu) {
            if ($mutu->getParcours()?->getId() === $parcours->getId()) {
                return;
            }
        }

        $mutu = new FicheMatiereMutualisable();
        $mutu->setFicheMatiere($fiche);
        $mutu->setParcours($parcours);
        $this->em->persist($mutu);
    }

    /**
     * Clone fidèle d'une fiche matière vers l'année cible : nouveau slug,
     * parcours/campagne cibles, et relink des compétences / apprentissages
     * critiques vers les entités de l'année cible (via les maps du contexte).
     */
    private function cloneFiche(FicheMatiere $source, CloneContext $ctx, CopyStructureReport $report): FicheMatiere
    {
        $clone = clone $source;
        $clone->prepareCloneForNewAnnee(); // réinitialise compétences + apprentissages critiques
        $clone->setSlug($source->getSlug() . '-rec-' . uniqid());
        $clone->setParcours($ctx->parcoursCible);
        $clone->setCampagneCollecte($ctx->campagne);
        $clone->setFicheMatiereOrigineCopie($this->boutDeLignee(FicheMatiere::class, 'ficheMatiereOrigineCopie', $source));
        $clone->setEnseignementMutualise(false);

        foreach ($source->getCompetences() as $competence) {
            $cibleComp = $ctx->compMap[$competence->getId()] ?? null;
            if ($cibleComp !== null) {
                $clone->addCompetence($cibleComp);
            }
        }
        foreach ($source->getApprentissagesCritiques() as $appCrit) {
            $cibleAppCrit = $ctx->appCritMap[$appCrit->getId()] ?? null;
            if ($cibleAppCrit !== null) {
                $clone->addApprentissagesCritique($cibleAppCrit);
            }
        }

        $this->em->persist($clone);
        $report->fichesCreees++;

        return $clone;
    }

    /**
     * Prépare le contexte de copie : campagne de la cible et tables de
     * correspondance origine → cible pour compétences et apprentissages critiques.
     */
    private function buildContext(Parcours $parcoursCible): CloneContext
    {
        $ctx = new CloneContext();
        $ctx->parcoursCible = $parcoursCible;

        $dpe = $parcoursCible->getDpeParcours()->first();
        $ctx->campagne = $dpe ? $dpe->getCampagneCollecte() : null;

        $cibleComps = [];
        foreach ($parcoursCible->getBlocCompetences() as $bloc) {
            foreach ($bloc->getCompetences() as $competence) {
                $cibleComps[] = $competence;
            }
        }
        $ctx->compMap = $this->buildOrigineMap($cibleComps, fn($c) => $c->getCompetenceOrigineCopie());

        $cibleAppCrits = [];
        $formationCible = $parcoursCible->getFormation();
        if ($formationCible !== null) {
            foreach ($formationCible->getButCompetences() as $butComp) {
                foreach ($butComp->getButNiveaux() as $niveau) {
                    foreach ($niveau->getButApprentissageCritiques() as $appCrit) {
                        $cibleAppCrits[] = $appCrit;
                    }
                }
            }
        }
        $ctx->appCritMap = $this->buildOrigineMap($cibleAppCrits, fn($a) => $a->getButApprentissageCritiqueOrigineCopie());

        return $ctx;
    }

    /**
     * Construit une map « id de chaque ancêtre (chaîne origineCopie) → entité cible ».
     * Une entité source de n'importe quelle génération retrouve ainsi son
     * homologue cible.
     *
     * @param object[] $cibleEntities
     */
    /**
     * Renvoie le « bout » de la lignée de copie de l'entité source : le dernier
     * descendant qui n'a pas encore de copie. Les liens *OrigineCopie sont des
     * OneToOne (colonne FK unique) ; rattacher le clone directement à la source
     * violerait cette contrainte si la source a déjà une copie (années non
     * adjacentes). On rattache donc au bout libre de la chaîne, ce qui est
     * unique-safe et prolonge correctement la lignée.
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T
     */
    private function boutDeLignee(string $entityClass, string $champOrigine, object $source): object
    {
        $repo = $this->em->getRepository($entityClass);
        $current = $source;
        $guard = 0;
        while ($guard < 20) {
            $copie = $repo->findOneBy([$champOrigine => $current]);
            if ($copie === null) {
                return $current;
            }
            $current = $copie;
            $guard++;
        }

        return $current;
    }

    private function buildOrigineMap(array $cibleEntities, callable $getOrigine): array
    {
        $map = [];
        foreach ($cibleEntities as $entity) {
            $current = $getOrigine($entity);
            $guard = 0;
            while ($current !== null && $guard < 20) {
                $map[$current->getId()] = $entity;
                $current = $getOrigine($current);
                $guard++;
            }
        }

        return $map;
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
            if ($c->isFichePartagee()) {
                // Fiche mutualisée : on ne touche pas à son contenu commun
                // (libellé, heures non spécifiques) pour préserver la
                // mutualisation. Les champs propres à l'EC (ECTS, MCCC, volumes
                // spécifiques) ont déjà été copiés ci-dessus.
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
