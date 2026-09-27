<?php

declare(strict_types=1);

namespace App\Service\Campagne;

use App\DTO\Campagne\CampagneDuplicationAuditDTO;
use App\DTO\Campagne\CampagneDuplicationDTO;
use App\DTO\Campagne\CampagneDuplicationResultDTO;
use App\Entity\Adresse;
use App\Entity\Annee;
use App\Entity\AnneeUniversitaire;
use App\Entity\BlocCompetence;
use App\Entity\ButApprentissageCritique;
use App\Entity\ButCompetence;
use App\Entity\ButNiveau;
use App\Entity\CampagneCollecte;
use App\Entity\Competence;
use App\Entity\Contact;
use App\Entity\DpeParcours;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreMutualisable;
use App\Entity\SemestreParcours;
use App\Entity\TimelineDate;
use App\Entity\Ue;
use App\Entity\UeMutualisable;
use App\Entity\UserProfil;
use App\Enums\TimelineDateFlagEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\AnneeUniversitaireRepository;
use App\Repository\BlocCompetenceRepository;
use App\Repository\ButApprentissageCritiqueRepository;
use App\Repository\ButCompetenceRepository;
use App\Repository\ButNiveauxRepository;
use App\Repository\CampagneCollecteRepository;
use App\Repository\CompetenceRepository;
use App\Repository\ContactRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FormationRepository;
use App\Repository\McccRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use App\Repository\SemestreMutualisableRepository;
use App\Repository\SemestreParcoursRepository;
use App\Repository\SemestreRepository;
use App\Repository\UeMutualisableRepository;
use App\Repository\UeRepository;
use App\Repository\UserProfilRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CampagneDuplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly FormationRepository $formationRepository,
        private readonly ParcoursRepository $parcoursRepository,
        private readonly DpeParcoursRepository $dpeParcoursRepository,
        private readonly BlocCompetenceRepository $blocCompetenceRepository,
        private readonly CompetenceRepository $competenceRepository,
        private readonly ButCompetenceRepository $butCompetenceRepository,
        private readonly ButNiveauxRepository $butNiveauxRepository,
        private readonly ButApprentissageCritiqueRepository $butApprentissageCritiqueRepository,
        private readonly FicheMatiereRepository $ficheMatiereRepository,
        private readonly FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
        private readonly SemestreRepository $semestreRepository,
        private readonly SemestreParcoursRepository $semestreParcoursRepository,
        private readonly SemestreMutualisableRepository $semestreMutualisableRepository,
        private readonly UeRepository $ueRepository,
        private readonly UeMutualisableRepository $ueMutualisableRepository,
        private readonly ElementConstitutifRepository $elementConstitutifRepository,
        private readonly ContactRepository $contactRepository,
        private readonly McccRepository $mcccRepository,
        private readonly AnneeUniversitaireRepository $anneeUniversitaireRepository,
        private readonly ProfilRepository $profilRepository,
        private readonly UserProfilRepository $userProfilRepository,
    ) {
    }

    /**
     * Effectue un audit / pré-contrôle complet d'une campagne source avant duplication.
     */
    public function audit(CampagneCollecte $sourceCampagne): CampagneDuplicationAuditDTO
    {
        $idSource = (int) $sourceCampagne->getId();

        $audit = new CampagneDuplicationAuditDTO(
            sourceCampagne: $sourceCampagne,
            nbFormations: count($this->formationRepository->findFromAnneeUniversitaire($idSource)),
            nbParcours: count($this->parcoursRepository->findFromAnneeUniversitaire($idSource)),
            nbDpeParcours: count($this->dpeParcoursRepository->findFromAnneeUniversitaire($idSource)),
            nbBlocsCompetences: count($this->blocCompetenceRepository->findFromAnneeUniversitaire($idSource)),
            nbCompetences: count($this->competenceRepository->findFromAnneeUniversitaire($idSource)),
            nbButCompetences: count($this->butCompetenceRepository->findFromAnneeUniversitaire($idSource)),
            nbButNiveaux: count($this->butNiveauxRepository->findFromAnneeUniversitaire($idSource)),
            nbButApprentissagesCritiques: count($this->butApprentissageCritiqueRepository->findFromAnneeUniversitaire($idSource)),
            nbFichesMatieres: count($this->ficheMatiereRepository->findFromAnneeUniversitaire($idSource)),
            nbFichesMatieresMutualisables: count($this->ficheMatiereMutualisableRepository->findFromAnneeUniversitaire($idSource)),
            nbSemestres: count($this->semestreRepository->findFromAnneeUniversitaire($idSource)),
            nbSemestresParcours: count($this->semestreParcoursRepository->findFromAnneeUniversitaire($idSource)),
            nbSemestresMutualisables: count($this->semestreMutualisableRepository->findFromAnneeUniversitaire($idSource)),
            nbUes: count($this->ueRepository->findFromAnneeUniversitaire($idSource)),
            nbUesMutualisables: count($this->ueMutualisableRepository->findFromAnneeUniversitaire($idSource)),
            nbElementsConstitutifs: count($this->elementConstitutifRepository->findFromAnneeUniversitaire($idSource)),
            nbContacts: count($this->contactRepository->findFromAnneeUniversitaire($idSource)),
            nbMccc: count($this->mcccRepository->findFromAnneeUniversitaire($idSource)),
            nbDroits: count($this->userProfilRepository->findBy(['campagneCollecte' => $sourceCampagne])),
        );

        // Analyse des avertissements
        $warnings = [];
        if ($audit->nbFormations === 0) {
            $warnings[] = "Aucune formation trouvée rattachée à cette campagne source.";
        }
        if ($audit->nbParcours === 0) {
            $warnings[] = "Aucun parcours trouvé rattaché à cette campagne source.";
        }
        if ($audit->nbFichesMatieres === 0) {
            $warnings[] = "Aucune fiche matière trouvée rattachée à cette campagne source.";
        }

        $audit->warnings = $warnings;

        return $audit;
    }

    /**
     * Exécute l'ensemble du processus de duplication pour créer la nouvelle campagne et son offre de formation.
     */
    public function duplicate(CampagneDuplicationDTO $dto): CampagneDuplicationResultDTO
    {
        $startTime = microtime(true);
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $sourceCampagne = $dto->sourceCampagne;
        if ($sourceCampagne === null) {
            return new CampagneDuplicationResultDTO(
                success: false,
                errors: ['Campagne source non spécifiée.'],
            );
        }

        $idSource = (int) $sourceCampagne->getId();
        $slugSuffix = $dto->slugSuffix ?? '-2026';
        $now = new DateTime('now');

        $createdCounts = [];
        $logs = [];

        try {
            // 1. NOUVELLE ANNÉE UNIVERSITAIRE & CAMPAGNE DE COLLECTE
            $logs[] = "Initialisation de l'Année Universitaire et de la Campagne de Collecte...";

            $anneeUniv = $this->anneeUniversitaireRepository->findOneBy([
                'libelle' => $dto->libelleAnneeUniversitaire,
            ]);
            if ($anneeUniv === null) {
                $anneeUniv = new AnneeUniversitaire();
                $anneeUniv->setLibelle((string) $dto->libelleAnneeUniversitaire);
                $anneeUniv->setAnnee((int) $dto->anneeUniversitaire);
                $this->entityManager->persist($anneeUniv);
            }

            $newCampagne = new CampagneCollecte();
            $newCampagne->setLibelle((string) $dto->libelleCampagne);
            $newCampagne->setAnnee((int) $dto->anneeCampagne);
            $newCampagne->setDefaut($dto->setCampagneDefaut);
            $newCampagne->setCouleur($dto->couleur);
            $newCampagne->setCodeApogee($dto->codeApogeeCampagne ?? '6');
            $newCampagne->setAnneeUniversitaire($anneeUniv);

            $this->entityManager->persist($newCampagne);

            // Timeline Dates
            $this->setupTimelineDate($newCampagne, TimelineDateFlagEnum::OUVERTURE_COLLECTE, $dto->dateOuvertureDpe, 'Ouverture de la collecte', 'fa-solid fa-play');
            $this->setupTimelineDate($newCampagne, TimelineDateFlagEnum::CLOTURE_COLLECTE, $dto->dateClotureDpe, 'Clôture de la collecte', 'fa-solid fa-stop');
            $this->setupTimelineDate($newCampagne, TimelineDateFlagEnum::TRANSMISSION_SES, $dto->dateTransmissionSes, 'Transmission SES', 'fa-solid fa-paper-plane');
            $this->setupTimelineDate($newCampagne, TimelineDateFlagEnum::CFVU, $dto->dateCfvu, 'Passage CFVU', 'fa-solid fa-gavel');
            $this->setupTimelineDate($newCampagne, TimelineDateFlagEnum::PUBLICATION, $dto->datePublication, 'Publication de l’offre', 'fa-solid fa-globe');

            $this->entityManager->flush();
            $createdCounts['campagne'] = 1;
            $logs[] = "Campagne '{$newCampagne->getLibelle()}' créée avec succès (ID: {$newCampagne->getId()}).";

            // 2. FORMATIONS
            $formationsIds = $this->formationRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($formationsIds) . " Formations...";
            $nbFormationsCreated = 0;
            foreach ($formationsIds as $formationRow) {
                $initialFormation = $this->formationRepository->find($formationRow['id'] ?? $formationRow);
                if (!$initialFormation instanceof Formation) {
                    continue;
                }
                $cloneFormation = clone $initialFormation;
                $cloneFormation->setSlug((string) $initialFormation->getSlug() . $slugSuffix);
                $cloneFormation->setFormationOrigineCopie($initialFormation);
                $cloneFormation->setDpe($newCampagne);
                $cloneFormation->setCreated($now);
                $cloneFormation->setUpdated($now);

                $this->entityManager->persist($cloneFormation);
                $nbFormationsCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['formations'] = $nbFormationsCreated;

            // 3. PARCOURS
            $parcoursIds = $this->parcoursRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($parcoursIds) . " Parcours...";
            $nbParcoursCreated = 0;
            foreach ($parcoursIds as $parcoursRow) {
                $initialParcours = $this->parcoursRepository->find($parcoursRow['id'] ?? $parcoursRow);
                if (!$initialParcours instanceof Parcours) {
                    continue;
                }
                $linkFormation = $this->formationRepository->findOneBy(['formationOrigineCopie' => $initialParcours->getFormation()]);

                $cloneParcours = clone $initialParcours;
                $cloneParcours->setParcoursOrigineCopie($initialParcours);
                $cloneParcours->setFormation($linkFormation);
                $cloneParcours->setCreated($now);
                $cloneParcours->setUpdated($now);

                $this->entityManager->persist($cloneParcours);
                $nbParcoursCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['parcours'] = $nbParcoursCreated;

            // 4. DPE PARCOURS
            $dpeIds = $this->dpeParcoursRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($dpeIds) . " DPE Parcours...";
            $nbDpeCreated = 0;
            foreach ($dpeIds as $dpeRow) {
                $initialDpe = $this->dpeParcoursRepository->find($dpeRow['id'] ?? $dpeRow);
                if (!$initialDpe instanceof DpeParcours) {
                    continue;
                }
                $linkFormationDpe = $this->formationRepository->findOneBy(['formationOrigineCopie' => $initialDpe->getFormation()]);
                $linkParcoursDpe = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialDpe->getParcours()]);

                $cloneDpe = clone $initialDpe;
                if ($cloneDpe->getEtatReconduction() !== TypeModificationDpeEnum::NON_OUVERTURE) {
                    $cloneDpe->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
                }
                $cloneDpe->setParcours($linkParcoursDpe);
                $cloneDpe->setFormation($linkFormationDpe);
                $cloneDpe->setCampagneCollecte($newCampagne);
                $cloneDpe->setEtatValidation(['tacite_reconduction' => 1]);
                $cloneDpe->setCreated($now);

                $this->entityManager->persist($cloneDpe);
                $nbDpeCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['dpe_parcours'] = $nbDpeCreated;

            // 5. BLOCS DE COMPÉTENCES & BUT COMPÉTENCES
            if ($dto->duplicateCompetences) {
                $logs[] = "Duplication des Blocs de compétences et Référentiels BUT...";
                $blocsIds = $this->blocCompetenceRepository->findFromAnneeUniversitaire($idSource);
                $nbBlocsCreated = 0;
                foreach ($blocsIds as $blocRow) {
                    $initialBloc = $this->blocCompetenceRepository->find($blocRow['id'] ?? $blocRow);
                    if (!$initialBloc instanceof BlocCompetence) {
                        continue;
                    }
                    $cloneBloc = clone $initialBloc;
                    if ($initialBloc->getParcours() !== null) {
                        $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialBloc->getParcours()]);
                        $cloneBloc->setParcours($linkParcours);
                    }
                    if ($initialBloc->getFormation() !== null) {
                        $linkFormation = $this->formationRepository->findOneBy(['formationOrigineCopie' => $initialBloc->getFormation()]);
                        $cloneBloc->setFormation($linkFormation);
                    }
                    $cloneBloc->setBlocCompetenceOrigineCopie($initialBloc);

                    $this->entityManager->persist($cloneBloc);
                    $nbBlocsCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['blocs_competences'] = $nbBlocsCreated;

                // Compétences
                $compIds = $this->competenceRepository->findFromAnneeUniversitaire($idSource);
                $nbCompCreated = 0;
                foreach ($compIds as $compRow) {
                    $initialComp = $this->competenceRepository->find($compRow['id'] ?? $compRow);
                    if (!$initialComp instanceof Competence) {
                        continue;
                    }
                    $cloneComp = clone $initialComp;
                    if ($initialComp->getBlocCompetence() !== null) {
                        $linkBloc = $this->blocCompetenceRepository->findOneBy(['blocCompetenceOrigineCopie' => $initialComp->getBlocCompetence()]);
                        $cloneComp->setBlocCompetence($linkBloc);
                    }
                    $cloneComp->setCompetenceOrigineCopie($initialComp);

                    $this->entityManager->persist($cloneComp);
                    $nbCompCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['competences'] = $nbCompCreated;

                // BUT Compétences
                $butCompIds = $this->butCompetenceRepository->findFromAnneeUniversitaire($idSource);
                $nbButCompCreated = 0;
                foreach ($butCompIds as $butCompRow) {
                    $initialButComp = $this->butCompetenceRepository->find($butCompRow['id'] ?? $butCompRow);
                    if (!$initialButComp instanceof ButCompetence) {
                        continue;
                    }
                    $cloneButComp = clone $initialButComp;
                    if ($initialButComp->getFormation() !== null) {
                        $linkFormation = $this->formationRepository->findOneBy(['formationOrigineCopie' => $initialButComp->getFormation()]);
                        $cloneButComp->setFormation($linkFormation);
                    }
                    $cloneButComp->setButCompetenceOrigineCopie($initialButComp);
                    $cloneButComp->setCampagneCollecte($newCampagne);

                    $this->entityManager->persist($cloneButComp);
                    $nbButCompCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['but_competences'] = $nbButCompCreated;

                // BUT Niveaux
                $butNiveauIds = $this->butNiveauxRepository->findFromAnneeUniversitaire($idSource);
                $nbButNiveauCreated = 0;
                foreach ($butNiveauIds as $butNiveauRow) {
                    $initialButNiveau = $this->butNiveauxRepository->find($butNiveauRow['id'] ?? $butNiveauRow);
                    if (!$initialButNiveau instanceof ButNiveau) {
                        continue;
                    }
                    $cloneButNiveau = clone $initialButNiveau;
                    $linkButComp = $this->butCompetenceRepository->findOneBy(['butCompetenceOrigineCopie' => $initialButNiveau->getCompetence()]);
                    $cloneButNiveau->setCompetence($linkButComp);
                    $cloneButNiveau->setButNiveauOrigineCopie($initialButNiveau);

                    $this->entityManager->persist($cloneButNiveau);
                    $nbButNiveauCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['but_niveaux'] = $nbButNiveauCreated;

                // BUT Apprentissages Critiques
                $butAppCritIds = $this->butApprentissageCritiqueRepository->findFromAnneeUniversitaire($idSource);
                $nbButAppCritCreated = 0;
                foreach ($butAppCritIds as $butAppCritRow) {
                    $initialButAppCrit = $this->butApprentissageCritiqueRepository->find($butAppCritRow['id'] ?? $butAppCritRow);
                    if (!$initialButAppCrit instanceof ButApprentissageCritique) {
                        continue;
                    }
                    $cloneButAppCrit = clone $initialButAppCrit;
                    $linkButNiveau = $this->butNiveauxRepository->findOneBy(['butNiveauOrigineCopie' => $initialButAppCrit->getNiveau()]);
                    $cloneButAppCrit->setNiveau($linkButNiveau);
                    $cloneButAppCrit->setButApprentissageCritiqueOrigineCopie($initialButAppCrit);

                    $this->entityManager->persist($cloneButAppCrit);
                    $nbButAppCritCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['but_apprentissages_critiques'] = $nbButAppCritCreated;
            }

            // 6. FICHES MATIÈRES
            $fichesIds = $this->ficheMatiereRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($fichesIds) . " Fiches Matières...";
            $nbFichesCreated = 0;
            foreach ($fichesIds as $ficheRow) {
                $initialFiche = $this->ficheMatiereRepository->find($ficheRow['id'] ?? $ficheRow);
                if (!$initialFiche instanceof FicheMatiere) {
                    continue;
                }
                $cloneFiche = clone $initialFiche;
                $cloneFiche->prepareCloneForNewAnnee();
                $cloneFiche->setSlug($this->formatNewSlug((string) $initialFiche->getSlug(), $slugSuffix));

                if ($initialFiche->getParcours() !== null) {
                    $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialFiche->getParcours()]);
                    $cloneFiche->setParcours($linkParcours);
                }

                if ($dto->duplicateCompetences) {
                    foreach ($initialFiche->getCompetences() as $initialFMCompetence) {
                        $linkComp = $this->competenceRepository->findOneBy(['competenceOrigineCopie' => $initialFMCompetence]);
                        if ($linkComp !== null) {
                            $cloneFiche->addCompetence($linkComp);
                        }
                    }
                    foreach ($initialFiche->getApprentissagesCritiques() as $initialFMAppCrit) {
                        $linkAppCrit = $this->butApprentissageCritiqueRepository->findOneBy(['butApprentissageCritiqueOrigineCopie' => $initialFMAppCrit]);
                        if ($linkAppCrit !== null) {
                            $cloneFiche->addApprentissagesCritique($linkAppCrit);
                        }
                    }
                }

                $cloneFiche->setFicheMatiereOrigineCopie($initialFiche);
                $cloneFiche->setCampagneCollecte($newCampagne);

                $this->entityManager->persist($cloneFiche);
                $nbFichesCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['fiches_matieres'] = $nbFichesCreated;

            // 7. FICHES MATIÈRES MUTUALISABLES
            if ($dto->duplicateMutualisations) {
                $ficheMutuIds = $this->ficheMatiereMutualisableRepository->findFromAnneeUniversitaire($idSource);
                $nbFicheMutuCreated = 0;
                foreach ($ficheMutuIds as $ficheMutuRow) {
                    $initialFicheMutu = $this->ficheMatiereMutualisableRepository->find($ficheMutuRow['id'] ?? $ficheMutuRow);
                    if (!$initialFicheMutu instanceof FicheMatiereMutualisable) {
                        continue;
                    }
                    $cloneFicheMutu = clone $initialFicheMutu;
                    if ($initialFicheMutu->getParcours() !== null) {
                        $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialFicheMutu->getParcours()]);
                        $cloneFicheMutu->setParcours($linkParcours);
                    }
                    if ($initialFicheMutu->getFicheMatiere() !== null) {
                        $linkFicheMatiere = $this->ficheMatiereRepository->findOneBy(['ficheMatiereOrigineCopie' => $initialFicheMutu->getFicheMatiere()]);
                        $cloneFicheMutu->setFicheMatiere($linkFicheMatiere);
                    }

                    $this->entityManager->persist($cloneFicheMutu);
                    $nbFicheMutuCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['fiches_matieres_mutualisables'] = $nbFicheMutuCreated;
            }

            // 8. SEMESTRES
            $semestresIds = $this->semestreRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($semestresIds) . " Semestres...";
            $nbSemestresCreated = 0;
            foreach ($semestresIds as $semestreRow) {
                $initialSemestre = $this->semestreRepository->find($semestreRow['id'] ?? $semestreRow);
                if (!$initialSemestre instanceof Semestre) {
                    continue;
                }
                $cloneSemestre = clone $initialSemestre;
                $cloneSemestre->setSemestreRaccroche(null);
                $cloneSemestre->setSemestreOrigineCopie($initialSemestre);

                $this->entityManager->persist($cloneSemestre);
                $nbSemestresCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['semestres'] = $nbSemestresCreated;

            // 9. SEMESTRES PARCOURS & ANNÉES
            $semestreParcoursIds = $this->semestreParcoursRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($semestreParcoursIds) . " Semestres Parcours...";
            /** @var array<int, array<int, Annee>> $tabAnnee */
            $tabAnnee = [];
            $nbSemestreParcoursCreated = 0;
            foreach ($semestreParcoursIds as $spRow) {
                $initialSp = $this->semestreParcoursRepository->find($spRow['id'] ?? $spRow);
                if (!$initialSp instanceof SemestreParcours) {
                    continue;
                }
                $linkSemestre = $this->semestreRepository->findOneBy(['semestreOrigineCopie' => $initialSp->getSemestre()]);
                $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialSp->getParcours()]);

                $cloneSp = clone $initialSp;
                $cloneSp->setSemestre($linkSemestre);
                $cloneSp->setParcours($linkParcours);

                $ordreSemestre = (int) $cloneSp->getOrdre();
                $ordreAnnee = intdiv($ordreSemestre + 1, 2);
                $parcoursId = $cloneSp->getParcours()?->getId();

                if ($ordreSemestre % 2 !== 0) {
                    $annee = new Annee();
                    $annee->setParcours($cloneSp->getParcours());
                    $annee->setOrdre($ordreAnnee);
                    $annee->setCodeApogeeEtapeAnnee($cloneSp->getCodeApogeeEtapeAnnee());
                    $annee->setCodeApogeeEtapeVersion($cloneSp->getCodeApogeeEtapeVersion());
                    if ($parcoursId !== null) {
                        $tabAnnee[$parcoursId][$ordreSemestre] = $annee;
                    }
                    $this->entityManager->persist($annee);
                } else {
                    $annee = ($parcoursId !== null && isset($tabAnnee[$parcoursId][$ordreSemestre - 1]))
                        ? $tabAnnee[$parcoursId][$ordreSemestre - 1]
                        : null;
                }

                if ($annee !== null) {
                    $annee->addParcoursSemestre($cloneSp);
                }

                $this->entityManager->persist($cloneSp);
                $nbSemestreParcoursCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['semestres_parcours'] = $nbSemestreParcoursCreated;

            // 10. SEMESTRES MUTUALISABLES
            if ($dto->duplicateMutualisations) {
                $semMutuIds = $this->semestreMutualisableRepository->findFromAnneeUniversitaire($idSource);
                $nbSemMutuCreated = 0;
                foreach ($semMutuIds as $semMutuRow) {
                    $initialSemMutu = $this->semestreMutualisableRepository->find($semMutuRow['id'] ?? $semMutuRow);
                    if (!$initialSemMutu instanceof SemestreMutualisable) {
                        continue;
                    }
                    $linkSemestre = $this->semestreRepository->findOneBy(['semestreOrigineCopie' => $initialSemMutu->getSemestre()]);
                    $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialSemMutu->getParcours()]);

                    $cloneSemMutu = clone $initialSemMutu;
                    $cloneSemMutu->setSemestre($linkSemestre);
                    $cloneSemMutu->setParcours($linkParcours);

                    // Raccrochage semestres
                    $semestreARaccrocherArray = $this->semestreRepository->findBy(['semestreRaccroche' => $initialSemMutu]);
                    foreach ($semestreARaccrocherArray as $semestreARaccrocher) {
                        $raccrochageSemestre = $this->semestreRepository->findOneBy(['semestreOrigineCopie' => $semestreARaccrocher]);
                        if ($raccrochageSemestre !== null) {
                            $raccrochageSemestre->setSemestreRaccroche($cloneSemMutu);
                            $this->entityManager->persist($raccrochageSemestre);
                        }
                    }

                    $this->entityManager->persist($cloneSemMutu);
                    $nbSemMutuCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['semestres_mutualisables'] = $nbSemMutuCreated;
            }

            // 11. UES
            $ueIds = $this->ueRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($ueIds) . " UEs...";
            $nbUeCreated = 0;
            foreach ($ueIds as $ueRow) {
                $initialUe = $this->ueRepository->find($ueRow['id'] ?? $ueRow);
                if (!$initialUe instanceof Ue) {
                    continue;
                }
                $cloneUe = clone $initialUe;
                $cloneUe->setUeParent(null);
                $cloneUe->setUeRaccrochee(null);
                if ($initialUe->getSemestre() !== null) {
                    $linkSemestre = $this->semestreRepository->findOneBy(['semestreOrigineCopie' => $initialUe->getSemestre()]);
                    $cloneUe->setSemestre($linkSemestre);
                }
                $cloneUe->setUeOrigineCopie($initialUe);

                $this->entityManager->persist($cloneUe);
                $nbUeCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['ues'] = $nbUeCreated;

            // 12. UES PARENTS
            foreach ($ueIds as $ueRow) {
                $initialUe = $this->ueRepository->find($ueRow['id'] ?? $ueRow);
                if ($initialUe instanceof Ue && $initialUe->getUeParent() !== null) {
                    $clonedUe = $this->ueRepository->findOneBy(['ueOrigineCopie' => $initialUe]);
                    $newParent = $this->ueRepository->findOneBy(['ueOrigineCopie' => $initialUe->getUeParent()]);
                    if ($clonedUe !== null && $newParent !== null) {
                        $clonedUe->setUeParent($newParent);
                        $this->entityManager->persist($clonedUe);
                    }
                }
            }
            $this->entityManager->flush();

            // 13. UES MUTUALISABLES
            if ($dto->duplicateMutualisations) {
                $ueMutuIds = $this->ueMutualisableRepository->findFromAnneeUniversitaire($idSource);
                $nbUeMutuCreated = 0;
                foreach ($ueMutuIds as $ueMutuRow) {
                    $initialUeMutu = $this->ueMutualisableRepository->find($ueMutuRow['id'] ?? $ueMutuRow);
                    if (!$initialUeMutu instanceof UeMutualisable) {
                        continue;
                    }
                    $cloneUeMutu = clone $initialUeMutu;
                    if ($initialUeMutu->getUe() !== null) {
                        $linkUe = $this->ueRepository->findOneBy(['ueOrigineCopie' => $initialUeMutu->getUe()]);
                        $cloneUeMutu->setUe($linkUe);
                    }
                    if ($initialUeMutu->getParcours() !== null) {
                        $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialUeMutu->getParcours()]);
                        $cloneUeMutu->setParcours($linkParcours);
                    }

                    // Raccrochage UEs
                    $ueARaccrocherArray = $this->ueRepository->findBy(['ueRaccrochee' => $initialUeMutu]);
                    foreach ($ueARaccrocherArray as $ueRaccroche) {
                        $raccrochageUe = $this->ueRepository->findOneBy(['ueOrigineCopie' => $ueRaccroche]);
                        if ($raccrochageUe !== null) {
                            $raccrochageUe->setUeRaccrochee($cloneUeMutu);
                            $this->entityManager->persist($raccrochageUe);
                        }
                    }

                    $this->entityManager->persist($cloneUeMutu);
                    $nbUeMutuCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['ues_mutualisables'] = $nbUeMutuCreated;
            }

            // 14. ÉLÉMENTS CONSTITUTIFS
            $ecIds = $this->elementConstitutifRepository->findFromAnneeUniversitaire($idSource);
            $logs[] = "Duplication de " . count($ecIds) . " Éléments Constitutifs...";
            $nbEcCreated = 0;
            foreach ($ecIds as $ecRow) {
                $initialEc = $this->elementConstitutifRepository->find($ecRow['id'] ?? $ecRow);
                if (!$initialEc instanceof ElementConstitutif) {
                    continue;
                }
                $cloneEc = clone $initialEc;
                $cloneEc->prepareCloneForNewAnnee();

                if ($initialEc->getFicheMatiere() !== null) {
                    $linkFicheM = $this->ficheMatiereRepository->findOneBy(['ficheMatiereOrigineCopie' => $initialEc->getFicheMatiere()]);
                    $cloneEc->setFicheMatiere($linkFicheM);
                }
                if ($initialEc->getUe() !== null) {
                    $linkUe = $this->ueRepository->findOneBy(['ueOrigineCopie' => $initialEc->getUe()]);
                    $cloneEc->setUe($linkUe);
                }
                if ($initialEc->getParcours() !== null) {
                    $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialEc->getParcours()]);
                    $cloneEc->setParcours($linkParcours);
                }

                if ($dto->duplicateCompetences) {
                    foreach ($initialEc->getCompetences() as $ecCompetence) {
                        $linkComp = $this->competenceRepository->findOneBy(['competenceOrigineCopie' => $ecCompetence]);
                        if ($linkComp !== null) {
                            $cloneEc->addCompetence($linkComp);
                        }
                    }
                }

                $cloneEc->setEcParent(null);
                $cloneEc->setEcOrigineCopie($initialEc);

                $this->entityManager->persist($cloneEc);
                $nbEcCreated++;
            }
            $this->entityManager->flush();
            $createdCounts['elements_constitutifs'] = $nbEcCreated;

            // 15. EC PARENTS
            foreach ($ecIds as $ecRow) {
                $initialEc = $this->elementConstitutifRepository->find($ecRow['id'] ?? $ecRow);
                if ($initialEc instanceof ElementConstitutif && $initialEc->getEcParent() !== null) {
                    $clonedChild = $this->elementConstitutifRepository->findOneBy(['ecOrigineCopie' => $initialEc]);
                    $clonedParent = $this->elementConstitutifRepository->findOneBy(['ecOrigineCopie' => $initialEc->getEcParent()]);
                    if ($clonedChild !== null && $clonedParent !== null) {
                        $clonedChild->setEcParent($clonedParent);
                        $this->entityManager->persist($clonedChild);
                    }
                }
            }
            $this->entityManager->flush();

            // 16. CONTACTS & ADRESSES
            if ($dto->duplicateContacts) {
                $contactsIds = $this->contactRepository->findFromAnneeUniversitaire($idSource);
                $logs[] = "Duplication de " . count($contactsIds) . " Contacts et Adresses...";
                $nbContactsCreated = 0;
                foreach ($contactsIds as $contactRow) {
                    $initialContact = $this->contactRepository->find($contactRow['id'] ?? $contactRow);
                    if (!$initialContact instanceof Contact) {
                        continue;
                    }
                    $linkParcours = $this->parcoursRepository->findOneBy(['parcoursOrigineCopie' => $initialContact->getParcours()]);

                    $cloneContact = clone $initialContact;
                    if ($initialContact->getAdresse() !== null) {
                        $cloneAdresse = clone $initialContact->getAdresse();
                        $cloneAdresse->setAdresseOrigineCopie($initialContact->getAdresse());
                        $cloneContact->setAdresse($cloneAdresse);
                        $this->entityManager->persist($cloneAdresse);
                    }
                    $cloneContact->setParcours($linkParcours);

                    $this->entityManager->persist($cloneContact);
                    $nbContactsCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['contacts'] = $nbContactsCreated;
            }

            // 17. MCCC
            if ($dto->duplicateMccc) {
                $mcccIds = $this->mcccRepository->findFromAnneeUniversitaire($idSource);
                $logs[] = "Duplication de " . count($mcccIds) . " MCCC...";
                $nbMcccCreated = 0;
                foreach ($mcccIds as $mcccRow) {
                    $initialMccc = $this->mcccRepository->find($mcccRow['id'] ?? $mcccRow);
                    if (!$initialMccc instanceof Mccc) {
                        continue;
                    }
                    $cloneMccc = clone $initialMccc;
                    $cloneMccc->setEc(null);
                    $cloneMccc->setFicheMatiere(null);

                    if ($initialMccc->getFicheMatiere() !== null) {
                        $linkFicheM = $this->ficheMatiereRepository->findOneBy(['ficheMatiereOrigineCopie' => $initialMccc->getFicheMatiere()]);
                        $cloneMccc->setFicheMatiere($linkFicheM);
                    }
                    if ($initialMccc->getEc() !== null) {
                        $linkEc = $this->elementConstitutifRepository->findOneBy(['ecOrigineCopie' => $initialMccc->getEc()]);
                        $cloneMccc->setEc($linkEc);
                    }

                    $this->entityManager->persist($cloneMccc);
                    $nbMcccCreated++;
                }
                $this->entityManager->flush();
                $createdCounts['mccc'] = $nbMcccCreated;
            }

            // 18. DROITS & PROFILS UTILISATEURS (RF, co-RF, RP, co-RP)
            if ($dto->duplicateDroits) {
                $logs[] = "Affectation des droits et profils utilisateurs (RF, co-RF, RP, co-RP) sur la nouvelle campagne...";
                $profilRf = $this->profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
                $profilCoRf = $this->profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_FORMATION']);
                $profilRp = $this->profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
                $profilCoRp = $this->profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_PARCOURS']);

                $nbDroitsCreated = 0;

                // Droits sur les Parcours
                $dpesCreated = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $newCampagne]);
                foreach ($dpesCreated as $dpe) {
                    $parcours = $dpe->getParcours();
                    if ($parcours !== null) {
                        if ($parcours->getRespParcours() !== null && $profilRp !== null) {
                            $prRp = new UserProfil();
                            $prRp->setParcours($parcours);
                            $prRp->setProfil($profilRp);
                            $prRp->setCampagneCollecte($newCampagne);
                            $prRp->setUser($parcours->getRespParcours());
                            $this->entityManager->persist($prRp);
                            $nbDroitsCreated++;
                        }
                        if ($parcours->getCoResponsable() !== null && $profilCoRp !== null) {
                            $prCoRp = new UserProfil();
                            $prCoRp->setParcours($parcours);
                            $prCoRp->setProfil($profilCoRp);
                            $prCoRp->setCampagneCollecte($newCampagne);
                            $prCoRp->setUser($parcours->getCoResponsable());
                            $this->entityManager->persist($prCoRp);
                            $nbDroitsCreated++;
                        }
                    }
                }

                // Droits sur les Formations
                $formationsCreated = $this->formationRepository->findBy(['dpe' => $newCampagne]);
                foreach ($formationsCreated as $formation) {
                    if ($formation->getResponsableMention() !== null && $profilRf !== null) {
                        $prRf = new UserProfil();
                        $prRf->setFormation($formation);
                        $prRf->setProfil($profilRf);
                        $prRf->setCampagneCollecte($newCampagne);
                        $prRf->setUser($formation->getResponsableMention());
                        $this->entityManager->persist($prRf);
                        $nbDroitsCreated++;
                    }
                    if ($formation->getCoResponsable() !== null && $profilCoRf !== null) {
                        $prCoRf = new UserProfil();
                        $prCoRf->setFormation($formation);
                        $prCoRf->setProfil($profilCoRf);
                        $prCoRf->setCampagneCollecte($newCampagne);
                        $prCoRf->setUser($formation->getCoResponsable());
                        $this->entityManager->persist($prCoRf);
                        $nbDroitsCreated++;
                    }
                }

                $this->entityManager->flush();
                $createdCounts['droits_profils'] = $nbDroitsCreated;
                $logs[] = "Affectation de {$nbDroitsCreated} profils utilisateurs.";
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $logs[] = "Duplication terminée avec succès en {$executionTime} secondes.";

            return new CampagneDuplicationResultDTO(
                success: true,
                targetCampagne: $newCampagne,
                targetAnneeUniversitaire: $anneeUniv,
                createdCounts: $createdCounts,
                logs: $logs,
                executionTimeSeconds: $executionTime,
            );
        } catch (\Throwable $e) {
            $this->logger->error("Erreur lors de la duplication de la campagne : " . $e->getMessage(), [
                'exception' => $e,
            ]);
            $executionTime = round(microtime(true) - $startTime, 2);

            return new CampagneDuplicationResultDTO(
                success: false,
                errors: [$e->getMessage()],
                logs: $logs,
                createdCounts: $createdCounts,
                executionTimeSeconds: $executionTime,
            );
        }
    }

    private function setupTimelineDate(
        CampagneCollecte $campagne,
        TimelineDateFlagEnum $flag,
        ?DateTimeInterface $date,
        string $libelle,
        string $icone,
    ): void {
        if ($date === null) {
            return;
        }

        $time = new TimelineDate();
        $time->setCampagneCollecte($campagne);
        $time->setLibelle($libelle);
        $time->setIcone($icone);
        $dt = $date instanceof \DateTime ? $date : new \DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
        $time->setDate($dt);
        $time->setFlag($flag);

        $campagne->addTimelineDate($time);
        $this->entityManager->persist($time);
    }

    private function formatNewSlug(string $initialSlug, string $suffix): string
    {
        $cleaned = preg_replace('/-[0-9]{4}$/', '', $initialSlug) ?? $initialSlug;
        return $cleaned . $suffix;
    }
}
