<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\DTO\FormationDeleteAnalysis;
use App\Entity\BlocCompetence;
use App\Entity\ChangeParcours;
use App\Entity\ChangeRf;
use App\Entity\CommentaireFormation;
use App\Entity\DpeDemande;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\FormationTabState;
use App\Entity\FormationVersioning;
use App\Entity\HistoriqueFormation;
use App\Entity\Parcours;
use App\Entity\UserProfil;
use App\Service\Parcours\ParcoursDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class FormationDeleteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
        private readonly ParcoursDeleteService $parcoursDeleteService,
    ) {
    }

    public function analyze(Formation $formation): FormationDeleteAnalysis
    {
        $parcoursList = $formation->getParcours();
        $parcoursCount = $parcoursList->count();
        $parcoursDefaut = null;
        $blockingReasons = [];
        $parcoursAnalysis = null;
        $sharedDependencies = [];
        $internalCounts = [];

        // Règle métier : n'autoriser la suppression que si plus de parcours ou uniquement le parcours par défaut
        if ($parcoursCount > 1) {
            $noms = [];
            foreach ($parcoursList as $p) {
                $noms[] = $p->getLibelle() ?? 'Sans libellé';
            }
            $blockingReasons[] = sprintf(
                'Cette formation contient %d parcours (%s). Vous devez d\'abord supprimer ou détacher les parcours secondaires avant de pouvoir supprimer la formation.',
                $parcoursCount,
                implode(', ', $noms)
            );
        } elseif ($parcoursCount === 1) {
            /** @var Parcours $onlyParcours */
            $onlyParcours = $parcoursList->first();
            if ($onlyParcours->isParcoursDefaut()) {
                $parcoursDefaut = $onlyParcours;
            } else {
                $blockingReasons[] = sprintf(
                    'Cette formation contient un parcours spécifique (« %s ») qui n\'est pas le parcours par défaut. La suppression n\'est autorisée que s\'il n\'y a plus de parcours ou uniquement le parcours par défaut.',
                    $onlyParcours->getLibelle() ?? 'Sans libellé'
                );
            }
        }

        // Si le parcours par défaut est présent, on applique l'analyse complète de suppression parcours
        if ($parcoursDefaut !== null) {
            $parcoursAnalysis = $this->parcoursDeleteService->analyze($parcoursDefaut);
            foreach ($parcoursAnalysis->sharedDependencies as $dep) {
                $sharedDependencies[] = $dep;
            }
            foreach ($parcoursAnalysis->internalElementsCount as $key => $count) {
                $internalCounts['parcours_' . $key] = $count;
            }
        }

        // 1. Versions officielles CFVU au niveau formation
        $fvRepo = $this->entityManager->getRepository(FormationVersioning::class);
        /** @var list<FormationVersioning> $versions */
        $versions = $fvRepo->findBy(['formation' => $formation]);
        $versionsCfvu = array_filter($versions, static fn (FormationVersioning $v) => $v->isCfvuFlag() === true);
        if (!empty($versionsCfvu)) {
            $sharedDependencies[] = [
                'type' => 'versions_cfvu_formation',
                'titre' => 'Versions officielles CFVU de la formation existantes',
                'description' => sprintf(
                    'Cette formation possède %d version(s) validée(s) en CFVU. Il ne s\'agit a priori pas d\'une simple erreur de saisie temporaire.',
                    count($versionsCfvu)
                ),
                'severity' => 'danger',
            ];
        }

        // 2. Demandes DPE associées à la formation
        $demandes = $formation->getDpeDemandes();
        if ($demandes->count() > 0) {
            $sharedDependencies[] = [
                'type' => 'demandes_dpe_formation',
                'titre' => 'Demandes DPE associées à la formation',
                'description' => sprintf('%d demande(s) DPE sont associées à cette formation.', $demandes->count()),
                'severity' => 'warning',
            ];
        }

        // Comptage des éléments internes propres à la formation
        $internalCounts['blocs_competences_formation'] = $formation->getBlocCompetences()->count();
        $internalCounts['versions_formation'] = count($versions);
        $internalCounts['commentaires'] = $formation->getCommentaires()->count();
        $internalCounts['historique'] = $formation->getHistoriqueFormations()->count();
        $internalCounts['profils_utilisateurs'] = $formation->getUserProfils()->count();
        $internalCounts['dpe_formation'] = $formation->getDpeFormations()->count();
        $internalCounts['type_ecs'] = $formation->getTypeEcs()->count();
        $internalCounts['but_competences'] = $formation->getButCompetences()->count();
        $internalCounts['tab_states'] = $formation->getFormationTabStates()->count();

        return new FormationDeleteAnalysis(
            formation: $formation,
            parcoursDefaut: $parcoursDefaut,
            parcoursCount: $parcoursCount,
            blockingReasons: $blockingReasons,
            sharedDependencies: $sharedDependencies,
            internalElementsCount: $internalCounts,
            parcoursDefautAnalysis: $parcoursAnalysis,
        );
    }

    public function delete(Formation $formation, bool $force = false): void
    {
        $analysis = $this->analyze($formation);
        if (!$analysis->canDelete()) {
            throw new LogicException(implode(' ', $analysis->blockingReasons));
        }

        if ($analysis->hasSharedDependencies() && !$force) {
            throw new LogicException(
                'Impossible de supprimer définitivement cette formation : des dépendances partagées ont été détectées et l\'option de forçage n\'est pas activée.'
            );
        }

        $user = $this->security->getUser();
        $userIdentifier = $user !== null ? $user->getUserIdentifier() : 'cli/system';
        $formationId = $formation->getId();
        $formationLibelle = $formation->getDisplay();

        $this->logger->warning(
            'Suppression définitive de la formation ID {id} ({libelle}) demandée par {user}. Force: {force}',
            [
                'id' => $formationId,
                'libelle' => $formationLibelle,
                'user' => $userIdentifier,
                'force' => $force,
                'sharedDependenciesCount' => $analysis->getSharedDependenciesCount(),
            ]
        );

        // 1. Si le parcours par défaut existe, on le supprime via le ParcoursDeleteService
        if ($analysis->parcoursDefaut !== null) {
            $this->parcoursDeleteService->delete($analysis->parcoursDefaut, $force);
        }

        // 2. DpeDemande associées à la formation
        $demandesRepo = $this->entityManager->getRepository(DpeDemande::class);
        /** @var list<DpeDemande> $demandes */
        $demandes = $demandesRepo->findBy(['formation' => $formation]);
        foreach ($demandes as $demande) {
            if ($demande->getNiveauDemande() === 'F') {
                $this->entityManager->remove($demande);
            } else {
                $demande->setFormation(null);
            }
        }

        // 3. DpeParcours rattachés à la formation (s'il en reste d'orphelins)
        $dpePRepo = $this->entityManager->getRepository(DpeParcours::class);
        /** @var list<DpeParcours> $dpePList */
        $dpePList = $dpePRepo->findBy(['formation' => $formation]);
        foreach ($dpePList as $dp) {
            $this->entityManager->remove($dp);
        }

        // 4. DpeFormation
        $dfRepo = $this->entityManager->getRepository(DpeFormation::class);
        /** @var list<DpeFormation> $dfList */
        $dfList = $dfRepo->findBy(['formation' => $formation]);
        foreach ($dfList as $df) {
            $this->entityManager->remove($df);
        }

        // 5. FormationTabState
        foreach ($formation->getFormationTabStates() as $state) {
            $this->entityManager->remove($state);
        }

        // 6. CommentaireFormation
        $cfRepo = $this->entityManager->getRepository(CommentaireFormation::class);
        /** @var list<CommentaireFormation> $cfList */
        $cfList = $cfRepo->findBy(['formation' => $formation]);
        foreach ($cfList as $comm) {
            $this->entityManager->remove($comm);
        }

        // 7. ChangeRf
        $crfRepo = $this->entityManager->getRepository(ChangeRf::class);
        /** @var list<ChangeRf> $crfList */
        $crfList = $crfRepo->findBy(['formation' => $formation]);
        foreach ($crfList as $crf) {
            $this->entityManager->remove($crf);
        }

        // 8. ChangeParcours
        $cpRepo = $this->entityManager->getRepository(ChangeParcours::class);
        /** @var list<ChangeParcours> $cpList */
        $cpList = $cpRepo->findBy(['formation' => $formation]);
        foreach ($cpList as $cp) {
            $this->entityManager->remove($cp);
        }

        // 9. HistoriqueFormation
        $hfRepo = $this->entityManager->getRepository(HistoriqueFormation::class);
        /** @var list<HistoriqueFormation> $hfList */
        $hfList = $hfRepo->findBy(['formation' => $formation]);
        foreach ($hfList as $hf) {
            $this->entityManager->remove($hf);
        }

        // 10. FormationVersioning
        $fvRepo = $this->entityManager->getRepository(FormationVersioning::class);
        /** @var list<FormationVersioning> $fvList */
        $fvList = $fvRepo->findBy(['formation' => $formation]);
        foreach ($fvList as $fv) {
            $this->entityManager->remove($fv);
        }

        // 11. UserProfil
        $upRepo = $this->entityManager->getRepository(UserProfil::class);
        /** @var list<UserProfil> $upList */
        $upList = $upRepo->findBy(['formation' => $formation]);
        foreach ($upList as $up) {
            $this->entityManager->remove($up);
        }

        // 12. TypeEc
        foreach ($formation->getTypeEcs() as $typeEc) {
            foreach ($typeEc->getElementConstitutifs() as $ec) {
                $ec->setTypeEc(null);
            }
            $typeEc->getTypeDiplomes()->clear();
            $this->entityManager->remove($typeEc);
        }

        // 13. ButCompetence
        foreach ($formation->getButCompetences() as $butComp) {
            foreach ($butComp->getButNiveaux() as $niveau) {
                $this->entityManager->remove($niveau);
            }
            $this->entityManager->remove($butComp);
        }

        // 14. Blocs de compétences propres à la formation
        $bcRepo = $this->entityManager->getRepository(BlocCompetence::class);
        foreach ($formation->getBlocCompetences() as $bloc) {
            $bloc->setBlocCompetenceOrigineCopie(null);
            /** @var list<BlocCompetence> $copiesBloc */
            $copiesBloc = $bcRepo->findBy(['blocCompetenceOrigineCopie' => $bloc]);
            foreach ($copiesBloc as $copieBloc) {
                $copieBloc->setBlocCompetenceOrigineCopie(null);
            }
            foreach ($bloc->getCompetences() as $comp) {
                foreach ($comp->getFicheMatieres() as $fiche) {
                    $fiche->removeCompetence($comp);
                }
                foreach ($comp->getElementConstitutifs() as $ec) {
                    $ec->removeCompetence($comp);
                }
                $this->entityManager->remove($comp);
            }
            $this->entityManager->remove($bloc);
        }

        // 15. Détachement des ManyToMany
        foreach ($formation->getDocumentConseils() as $doc) {
            $doc->removeFormation($formation);
        }
        $formation->getDocumentConseils()->clear();
        $formation->getComposantesInscription()->clear();
        $formation->getLocalisationMention()->clear();

        // 16. Suppression de la formation
        $formation->suspendreRecalculRemplissage();
        $this->entityManager->remove($formation);
        $this->entityManager->flush();

        $this->logger->info(
            'Formation ID {id} ({libelle}) supprimée définitivement avec succès.',
            ['id' => $formationId, 'libelle' => $formationLibelle]
        );
    }
}
