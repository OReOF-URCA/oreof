<?php

declare(strict_types=1);

namespace App\Service\Parcours;

use App\DTO\ParcoursDeleteAnalysis;
use App\Entity\Annee;
use App\Entity\BlocCompetence;
use App\Entity\ChangeParcours;
use App\Entity\CommentaireParcours;
use App\Entity\Contact;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\ParcoursRamification;
use App\Entity\ParcoursTabState;
use App\Entity\ParcoursVersioning;
use App\Entity\Semestre;
use App\Entity\SemestreMutualisable;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Entity\UeMutualisable;
use App\Entity\UserProfil;
use App\Entity\VolumeHoraireParcours;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class ParcoursDeleteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    ) {
    }

    public function analyze(Parcours $parcours): ParcoursDeleteAnalysis
    {
        $formation = $parcours->getFormation();
        $isSeulParcours = false;
        if ($formation !== null) {
            $isSeulParcours = $formation->getParcours()->count() <= 1;
        }

        $sharedDependencies = [];
        $internalCounts = [];

        // 1. Semestres mutualisés portés par ce parcours et réutilisés par d'autres parcours
        $smRepo = $this->entityManager->getRepository(SemestreMutualisable::class);
        /** @var list<SemestreMutualisable> $smList */
        $smList = $smRepo->findBy(['parcours' => $parcours]);
        $sharedSemestresDetails = [];
        foreach ($smList as $sm) {
            $countHooked = $sm->getSemestres()->count();
            if ($countHooked > 0) {
                $sharedSemestresDetails[] = sprintf('Semestre %s (mutualisé avec %d semestre(s) d\'autre(s) parcours)', $sm->getSemestre()?->display() ?? '', $countHooked);
            }
        }
        if (!empty($sharedSemestresDetails)) {
            $sharedDependencies[] = [
                'type' => 'semestres_mutualises',
                'titre' => 'Semestres mutualisés utilisés par d\'autres parcours',
                'description' => sprintf('%d semestre(s) mutualisé(s) de ce parcours sont actuellement rattachés à d\'autres parcours.', count($sharedSemestresDetails)),
                'severity' => 'danger',
                'details' => $sharedSemestresDetails,
            ];
        }

        // 2. UEs mutualisées portées par ce parcours et réutilisées par d'autres parcours
        $umRepo = $this->entityManager->getRepository(UeMutualisable::class);
        /** @var list<UeMutualisable> $umList */
        $umList = $umRepo->findBy(['parcours' => $parcours]);
        $sharedUesDetails = [];
        foreach ($umList as $um) {
            $countHooked = $um->getUes()->count();
            if ($countHooked > 0) {
                $sharedUesDetails[] = sprintf('UE %s (rattachée à %d UE(s) d\'autre(s) parcours)', $um->getUe()?->display($parcours) ?? '', $countHooked);
            }
        }
        if (!empty($sharedUesDetails)) {
            $sharedDependencies[] = [
                'type' => 'ues_mutualisees',
                'titre' => 'UEs mutualisées utilisées par d\'autres parcours',
                'description' => sprintf('%d UE(s) de ce parcours sont réutilisées par d\'autres parcours.', count($sharedUesDetails)),
                'severity' => 'danger',
                'details' => $sharedUesDetails,
            ];
        }

        // 3. Fiches matières portées par ce parcours et réutilisées par des ECs d'autres parcours
        $fmRepo = $this->entityManager->getRepository(FicheMatiere::class);
        /** @var list<FicheMatiere> $fiches */
        $fiches = $fmRepo->findBy(['parcours' => $parcours]);
        $sharedFichesDetails = [];
        foreach ($fiches as $fiche) {
            $otherEcsCount = 0;
            foreach ($fiche->getElementConstitutifs() as $ec) {
                if ($ec->getParcours() !== null && $ec->getParcours() !== $parcours) {
                    $otherEcsCount++;
                }
            }
            if ($otherEcsCount > 0) {
                $sharedFichesDetails[] = sprintf('Fiche "%s" (utilisée sur %d élément(s) constitutif(s) d\'autre(s) parcours)', $fiche->getDisplay(), $otherEcsCount);
            }
        }
        if (!empty($sharedFichesDetails)) {
            $sharedDependencies[] = [
                'type' => 'fiches_matieres_partagees',
                'titre' => 'Fiches matières partagées avec d\'autres parcours',
                'description' => sprintf('%d fiche(s) matière(s) créée(s) pour ce parcours sont utilisées dans d\'autres parcours.', count($sharedFichesDetails)),
                'severity' => 'warning',
                'details' => $sharedFichesDetails,
            ];
        }

        // 4. Parcours enfants ou ramifications
        $pRepo = $this->entityManager->getRepository(Parcours::class);
        /** @var list<Parcours> $enfants */
        $enfants = $pRepo->findBy(['parcoursParent' => $parcours]);
        if (!empty($enfants)) {
            $enfantsDetails = array_map(static fn(Parcours $p) => $p->getDisplay(), $enfants);
            $sharedDependencies[] = [
                'type' => 'parcours_enfants',
                'titre' => 'Parcours ou options rattachés (enfants)',
                'description' => sprintf('%d option(s) ou sous-parcours ont ce parcours comme parent.', count($enfants)),
                'severity' => 'danger',
                'details' => $enfantsDetails,
            ];
        }

        // 5. Versions officielles CFVU
        $pvRepo = $this->entityManager->getRepository(ParcoursVersioning::class);
        /** @var list<ParcoursVersioning> $versions */
        $versions = $pvRepo->findBy(['parcours' => $parcours]);
        $versionsCfvu = array_filter($versions, static fn(ParcoursVersioning $v) => $v->isCvfuFlag() === true);
        if (!empty($versionsCfvu)) {
            $sharedDependencies[] = [
                'type' => 'versions_cfvu',
                'titre' => 'Versions officielles CFVU existantes',
                'description' => sprintf('Ce parcours possède %d version(s) validée(s) en CFVU. Il ne s\'agit a priori pas d\'une simple erreur de saisie temporaire.', count($versionsCfvu)),
                'severity' => 'danger',
            ];
        }

        // 6. Demandes DPE
        $demandes = $parcours->getDpeDemandes();
        if ($demandes->count() > 0) {
            $sharedDependencies[] = [
                'type' => 'demandes_dpe',
                'titre' => 'Demandes DPE associées',
                'description' => sprintf('%d demande(s) DPE sont associées à ce parcours.', $demandes->count()),
                'severity' => 'warning',
            ];
        }

        // Comptage des éléments internes qui seront supprimés
        $internalCounts['annees'] = $parcours->getAnnees()->count();
        $internalCounts['semestres_parcours'] = $parcours->getSemestreParcours()->count();
        $internalCounts['fiches_matieres'] = count($fiches);
        $internalCounts['blocs_competences'] = $parcours->getBlocCompetences()->count();
        $internalCounts['contacts'] = $parcours->getContacts()->count();
        $internalCounts['dpe_parcours'] = $parcours->getDpeParcours()->count();
        $internalCounts['versions'] = count($versions);

        $ecsRepo = $this->entityManager->getRepository(ElementConstitutif::class);
        $internalCounts['elements_constitutifs'] = $ecsRepo->count(['parcours' => $parcours]);

        return new ParcoursDeleteAnalysis(
            parcours: $parcours,
            formation: $formation,
            isSeulParcoursDeLaFormation: $isSeulParcours,
            isParcoursDefaut: $parcours->isParcoursDefaut(),
            sharedDependencies: $sharedDependencies,
            internalElementsCount: $internalCounts
        );
    }

    public function delete(Parcours $parcours, bool $force = false): void
    {
        $analysis = $this->analyze($parcours);
        if ($analysis->hasSharedDependencies() && !$force) {
            throw new LogicException(
                'Impossible de supprimer définitivement ce parcours : des dépendances partagées ont été détectées et l\'option de forçage n\'est pas activée.'
            );
        }

        $user = $this->security->getUser();
        $userIdentifier = $user !== null ? $user->getUserIdentifier() : 'cli/system';
        $parcoursId = $parcours->getId();
        $parcoursLibelle = $parcours->getLibelle();

        $this->logger->warning(
            'Suppression définitive du parcours ID {id} ({libelle}) demandée par {user}. Force: {force}',
            [
                'id' => $parcoursId,
                'libelle' => $parcoursLibelle,
                'user' => $userIdentifier,
                'force' => $force,
                'sharedDependenciesCount' => $analysis->getSharedDependenciesCount(),
            ]
        );

        // 1. DpeDemande
        $demandesRepo = $this->entityManager->getRepository(DpeDemande::class);
        /** @var list<DpeDemande> $demandes */
        $demandes = $demandesRepo->findBy(['parcours' => $parcours]);
        foreach ($demandes as $demande) {
            if ($demande->getNiveauDemande() === 'P') {
                $this->entityManager->remove($demande);
            } else {
                $demande->setParcours(null);
            }
        }

        // 2. DpeParcours
        $dpeRepo = $this->entityManager->getRepository(DpeParcours::class);
        /** @var list<DpeParcours> $dpeList */
        $dpeList = $dpeRepo->findBy(['parcours' => $parcours]);
        foreach ($dpeList as $dpe) {
            $this->entityManager->remove($dpe);
        }

        // 3. ParcoursTabState
        $ptsRepo = $this->entityManager->getRepository(ParcoursTabState::class);
        /** @var list<ParcoursTabState> $ptsList */
        $ptsList = $ptsRepo->findBy(['parcours' => $parcours]);
        foreach ($ptsList as $pts) {
            $this->entityManager->remove($pts);
        }

        // 4. CommentaireParcours
        $commRepo = $this->entityManager->getRepository(CommentaireParcours::class);
        /** @var list<CommentaireParcours> $commList */
        $commList = $commRepo->findBy(['parcours' => $parcours]);
        foreach ($commList as $comm) {
            $this->entityManager->remove($comm);
        }

        // 5. HistoriqueParcours
        $histRepo = $this->entityManager->getRepository(HistoriqueParcours::class);
        /** @var list<HistoriqueParcours> $histList */
        $histList = $histRepo->findBy(['parcours' => $parcours]);
        foreach ($histList as $hist) {
            $this->entityManager->remove($hist);
        }

        // 6. VolumeHoraireParcours
        $vhRepo = $this->entityManager->getRepository(VolumeHoraireParcours::class);
        /** @var list<VolumeHoraireParcours> $vhList */
        $vhList = $vhRepo->findBy(['parcours' => $parcours]);
        foreach ($vhList as $vh) {
            $this->entityManager->remove($vh);
        }

        // 7. UserProfil
        $upRepo = $this->entityManager->getRepository(UserProfil::class);
        /** @var list<UserProfil> $upList */
        $upList = $upRepo->findBy(['parcours' => $parcours]);
        foreach ($upList as $up) {
            $this->entityManager->remove($up);
        }

        // 8. ParcoursVersioning
        $pvRepo = $this->entityManager->getRepository(ParcoursVersioning::class);
        /** @var list<ParcoursVersioning> $pvList */
        $pvList = $pvRepo->findBy(['parcours' => $parcours]);
        foreach ($pvList as $pv) {
            $this->entityManager->remove($pv);
        }

        // 9. ChangeParcours
        $cpRepo = $this->entityManager->getRepository(ChangeParcours::class);
        /** @var list<ChangeParcours> $cpList */
        $cpList = $cpRepo->findBy(['parcours' => $parcours]);
        foreach ($cpList as $cp) {
            $this->entityManager->remove($cp);
        }

        // 10. ParcoursRamification
        $prRepo = $this->entityManager->getRepository(ParcoursRamification::class);
        /** @var list<ParcoursRamification> $prOrigines */
        $prOrigines = $prRepo->findBy(['parcoursOrigine' => $parcours]);
        foreach ($prOrigines as $pr) {
            $this->entityManager->remove($pr);
        }
        /** @var list<ParcoursRamification> $prCibles */
        $prCibles = $prRepo->findBy(['parcoursCible' => $parcours]);
        foreach ($prCibles as $pr) {
            $this->entityManager->remove($pr);
        }

        // 11. Détacher les références d'autres parcours
        $pRepo = $this->entityManager->getRepository(Parcours::class);
        /** @var list<Parcours> $enfants */
        $enfants = $pRepo->findBy(['parcoursParent' => $parcours]);
        foreach ($enfants as $enfant) {
            $enfant->setParcoursParent(null);
        }
        /** @var list<Parcours> $originaires */
        $originaires = $pRepo->findBy(['parcoursOrigine' => $parcours]);
        foreach ($originaires as $orig) {
            $orig->setParcoursOrigine(null);
        }
        /** @var list<Parcours> $copies */
        $copies = $pRepo->findBy(['parcoursOrigineCopie' => $parcours]);
        foreach ($copies as $copie) {
            $copie->setParcoursOrigineCopie(null);
        }

        // 12. Contacts
        foreach ($parcours->getContacts() as $contact) {
            $this->entityManager->remove($contact);
        }

        // 13. Blocs de compétences et compétences
        foreach ($parcours->getBlocCompetences() as $bloc) {
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

        // 14. FicheMatiereMutualisable
        $fmmRepo = $this->entityManager->getRepository(FicheMatiereMutualisable::class);
        /** @var list<FicheMatiereMutualisable> $fmmList */
        $fmmList = $fmmRepo->findBy(['parcours' => $parcours]);
        foreach ($fmmList as $fmm) {
            $this->entityManager->remove($fmm);
        }

        // 15. SemestreMutualisable
        $smRepo = $this->entityManager->getRepository(SemestreMutualisable::class);
        /** @var list<SemestreMutualisable> $smList */
        $smList = $smRepo->findBy(['parcours' => $parcours]);
        foreach ($smList as $sm) {
            foreach ($sm->getSemestres() as $hookedSem) {
                $hookedSem->setSemestreRaccroche(null);
            }
            $this->entityManager->remove($sm);
        }

        // 16. UeMutualisable
        $umRepo = $this->entityManager->getRepository(UeMutualisable::class);
        /** @var list<UeMutualisable> $umList */
        $umList = $umRepo->findBy(['parcours' => $parcours]);
        foreach ($umList as $um) {
            foreach ($um->getUes() as $hookedUe) {
                $hookedUe->setUeRaccrochee(null);
            }
            $this->entityManager->remove($um);
        }

        // 17. Éléments constitutifs
        $ecRepo = $this->entityManager->getRepository(ElementConstitutif::class);
        /** @var list<ElementConstitutif> $ecs */
        $ecs = $ecRepo->findBy(['parcours' => $parcours]);
        foreach ($ecs as $ec) {
            foreach ($ec->getEcEnfants() as $ecEnfant) {
                $this->entityManager->remove($ecEnfant);
            }
            $this->entityManager->remove($ec);
        }

        // 18. Fiches matières portées par ce parcours
        $fmRepo = $this->entityManager->getRepository(FicheMatiere::class);
        /** @var list<FicheMatiere> $fiches */
        $fiches = $fmRepo->findBy(['parcours' => $parcours]);
        foreach ($fiches as $fiche) {
            $usedByOthers = false;
            foreach ($fiche->getElementConstitutifs() as $ec) {
                if ($ec->getParcours() !== null && $ec->getParcours() !== $parcours) {
                    $usedByOthers = true;
                    break;
                }
            }
            if ($usedByOthers) {
                // Détacher le parcours propriétaire pour préserver la fiche matière utilisée ailleurs
                $fiche->setParcours(null);
            } else {
                foreach ($fiche->getFicheMatiereParcours() as $fmm) {
                    $this->entityManager->remove($fmm);
                }
                $this->entityManager->remove($fiche);
            }
        }

        // 19. SemestreParcours et Semestres propres
        foreach ($parcours->getSemestreParcours() as $sp) {
            $semestre = $sp->getSemestre();
            $this->entityManager->remove($sp);

            if ($semestre !== null && !$semestre->isTroncCommun()) {
                $otherSpCount = 0;
                foreach ($semestre->getSemestreParcours() as $otherSp) {
                    if ($otherSp->getParcours() !== $parcours) {
                        $otherSpCount++;
                    }
                }
                // Si le semestre n'appartient qu'à ce parcours, on supprime aussi ses UEs et le semestre
                if ($otherSpCount === 0) {
                    foreach ($semestre->getUes() as $ue) {
                        foreach ($ue->getElementConstitutifs() as $ueEc) {
                            $this->entityManager->remove($ueEc);
                        }
                        $this->entityManager->remove($ue);
                    }
                    $this->entityManager->remove($semestre);
                }
            }
        }

        // 20. Années
        foreach ($parcours->getAnnees() as $annee) {
            foreach ($annee->getAdmissionPlateformeParametres() as $param) {
                $this->entityManager->remove($param);
            }
            $this->entityManager->remove($annee);
        }

        // 21. Retrait de la formation et suppression du parcours
        $formation = $parcours->getFormation();
        if ($formation !== null) {
            $formation->getParcours()->removeElement($parcours);
        }

        $this->entityManager->remove($parcours);
        $this->entityManager->flush();

        $this->logger->info(
            'Parcours ID {id} ({libelle}) supprimé définitivement avec succès.',
            ['id' => $parcoursId, 'libelle' => $parcoursLibelle]
        );
    }
}
