<?php

declare(strict_types=1);

namespace App\Service\Parcours;

use App\DTO\ParcoursDuplicationAnalysis;
use App\DTO\ParcoursDuplicationConfig;
use App\DTO\ParcoursDuplicationResult;
use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\Competence;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Entity\User;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\SemestreMutualisableRepository;
use App\Repository\UeMutualisableRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Throwable;

final class ParcoursDuplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly FicheMatiereRepository $ficheMatiereRepository,
        private readonly SemestreMutualisableRepository $semestreMutualisableRepository,
        private readonly UeMutualisableRepository $ueMutualisableRepository,
        private readonly FicheMatiereMutualisableRepository $ficheMatiereMutualisableRepository,
    ) {
    }

    /**
     * Analyse le parcours source avant duplication pour produire le bilan pédagogique
     * et les avertissements nécessaires pour l'utilisateur.
     */
    public function analyze(Parcours $parcours): ParcoursDuplicationAnalysis
    {
        $formation = $parcours->getFormation();

        // 1. Décompte structurel
        $nbSemestres = $parcours->getSemestreParcours()->count();
        $nbUes = 0;
        $nbEcs = 0;
        $fichesMatieresIds = [];
        $nbMcccs = 0;

        foreach ($parcours->getSemestreParcours() as $sp) {
            $semestre = $sp->getSemestre();
            if ($semestre === null) {
                continue;
            }

            foreach ($semestre->getUes() as $ue) {
                $nbUes++;
                $nbMcccs += $this->countMcccForUe($ue, $nbEcs, $fichesMatieresIds);
            }
        }

        $nbBlocs = $parcours->getBlocCompetences()->count();
        $nbCompetences = 0;
        foreach ($parcours->getBlocCompetences() as $bloc) {
            $nbCompetences += $bloc->getCompetences()->count();
        }

        $nbContacts = $parcours->getContacts()->count();

        $counts = [
            'semestres' => $nbSemestres,
            'ues' => $nbUes,
            'ecs' => $nbEcs,
            'fiches_matieres' => count($fichesMatieresIds),
            'mcccs' => $nbMcccs,
            'blocs' => $nbBlocs,
            'competences' => $nbCompetences,
            'contacts' => $nbContacts,
        ];

        // 2. Détection des dépendances et alertes
        $warnings = [];

        // Semestres mutualisés
        $smList = $this->semestreMutualisableRepository->findBy(['parcours' => $parcours]);
        if (!empty($smList)) {
            $warnings[] = [
                'type' => 'semestres_mutualises',
                'titre' => 'Semestres mutualisés avec d\'autres parcours',
                'description' => sprintf('%d semestre(s) mutualisé(s) sont portés par ce parcours. La duplication créera une version autonome dédiée au nouveau parcours.', count($smList)),
                'severity' => 'info',
            ];
        }

        // UEs mutualisées
        $umList = $this->ueMutualisableRepository->findBy(['parcours' => $parcours]);
        if (!empty($umList)) {
            $warnings[] = [
                'type' => 'ues_mutualisees',
                'titre' => 'UEs mutualisées associées',
                'description' => sprintf('%d UE(s) mutualisée(s) sont rattachées à ce parcours.', count($umList)),
                'severity' => 'info',
            ];
        }

        // Fiches matières mutualisées
        $fmList = $this->ficheMatiereMutualisableRepository->findBy(['parcours' => $parcours]);
        if (!empty($fmList)) {
            $warnings[] = [
                'type' => 'fiches_mutualisees',
                'titre' => 'Fiches matières mutualisées',
                'description' => sprintf('%d fiche(s) matière(s) mutualisée(s) sont répertoriées sur ce parcours.', count($fmList)),
                'severity' => 'info',
            ];
        }

        // Options filles
        if ($parcours->getParcoursEnfants()->count() > 0) {
            $warnings[] = [
                'type' => 'options_enfants',
                'titre' => 'Options / sous-parcours détectés',
                'description' => sprintf('Ce parcours possède %d option(s) fille(s). Celles-ci ne seront pas dupliquées automatiquement avec ce parcours.', $parcours->getParcoursEnfants()->count()),
                'severity' => 'warning',
            ];
        }

        // 3. Matrice de recopie explicite pour l'UX
        $elementsRecopies = [
            'Informations de présentation, objectifs, volumes horaires et débouchés',
            sprintf('Structure complète : %d semestre(s), %d UE(s) et %d élément(s) constitutif(s)', $nbSemestres, $nbUes, $nbEcs),
            sprintf('Référentiel de compétences : %d bloc(s) et %d compétence(s)', $nbBlocs, $nbCompetences),
            sprintf('Fiches matières associées : %d fiche(s) avec génération d\'identifiants uniques', count($fichesMatieresIds)),
            sprintf('Modalités de contrôle des connaissances : %d règle(s) MCCC', $nbMcccs),
            sprintf('Contacts et coordonnées : %d contact(s) et adresses', $nbContacts),
        ];

        $elementsReinitialises = [
            'Identifiant interne et slug : attribution de nouveaux identifiants uniques',
            'Workflow et DPE : version remise à 0.1, statut « En cours de rédaction », état « Création »',
            'Étapes de saisie et de complétude : remises à zéro pour relecture',
        ];

        $elementsExclus = [
            'Historique des modifications et traçabilité d\'audit du parcours source',
            'Validations officielles antérieures (décisions CFVU, conseils de composante)',
            'Commentaires et avis de travail déposés sur le parcours source',
            'Fichiers volumineux ou maquettes PDF signées',
        ];

        return new ParcoursDuplicationAnalysis(
            parcours: $parcours,
            formation: $formation,
            counts: $counts,
            warnings: $warnings,
            elementsRecopies: $elementsRecopies,
            elementsReinitialises: $elementsReinitialises,
            elementsExclus: $elementsExclus
        );
    }

    /**
     * Exécute la duplication complète du parcours au sein d'une transaction sécurisée.
     */
    public function duplicate(
        Parcours $parcours,
        ParcoursDuplicationConfig $config,
        User $user,
        CampagneCollecte $campagneCollecte
    ): ParcoursDuplicationResult {
        $this->logger->info('Démarrage de la duplication du parcours {id} (« {libelle} ») vers « {nouveauLibelle} »', [
            'id' => $parcours->getId(),
            'libelle' => $parcours->getLibelle(),
            'nouveauLibelle' => $config->nouveauLibelle,
            'user' => $user->getUserIdentifier(),
        ]);

        try {
            return $this->entityManager->wrapInTransaction(function () use ($parcours, $config, $user, $campagneCollecte): ParcoursDuplicationResult {
                $now = new DateTime();
                $createdCounts = [
                    'parcours' => 1,
                    'dpe' => 1,
                    'contacts' => 0,
                    'blocs' => 0,
                    'competences' => 0,
                    'annees' => 0,
                    'semestres' => 0,
                    'ues' => 0,
                    'ecs' => 0,
                    'fiches_matieres' => 0,
                    'mcccs' => 0,
                ];

                // 1. CLONAGE DU PARCOURS RACINE
                $cloneParcours = clone $parcours;
                $this->resetEntityIdAndCollections($cloneParcours);

                $cloneParcours->setLibelle($config->nouveauLibelle);
                if ($config->nouveauSigle !== null && trim($config->nouveauSigle) !== '') {
                    $cloneParcours->setSigle(trim($config->nouveauSigle));
                }
                $cloneParcours->setParcoursOrigineCopie($parcours);
                $cloneParcours->setMaquettePdf(null);
                $cloneParcours->setMaquettePdfNomOriginal(null);
                $cloneParcours->setCreated($now);
                $cloneParcours->setUpdated($now);

                if (!$config->conserverResponsables) {
                    $cloneParcours->setRespParcours(null);
                    $cloneParcours->setCoResponsable(null);
                }

                $etatSteps = [];
                for ($i = 1; $i <= 6; $i++) {
                    $etatSteps[$i] = false;
                }
                $cloneParcours->setEtatSteps($etatSteps);
                $cloneParcours->setEtatParcours([]);

                $this->entityManager->persist($cloneParcours);

                // 2. DPE ET DEMANDE DPE INITIALE
                $newDpe = new DpeParcours();
                $newDpe->setParcours($cloneParcours);
                $newDpe->setFormation($parcours->getFormation());
                $newDpe->setVersion('0.1');
                $newDpe->setEtatReconduction(TypeModificationDpeEnum::CREATION);
                $newDpe->setCreated($now);
                $newDpe->setCampagneCollecte($campagneCollecte);
                $newDpe->setEtatValidation(['en_cours_redaction' => 1]);
                $this->entityManager->persist($newDpe);

                $newDemande = new DpeDemande();
                $newDemande->setParcours($cloneParcours);
                $newDemande->setFormation($parcours->getFormation());
                $newDemande->setCampagneCollecte($campagneCollecte);
                $newDemande->setAuteur($user);
                $newDemande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
                $newDemande->setNiveauModification(TypeModificationDpeEnum::CREATION);
                $newDemande->setNiveauDemande('P');
                $newDemande->setDateDemande($now);
                $newDemande->setArgumentaireDemande(sprintf('Création par duplication du parcours « %s »', (string)$parcours->getLibelle()));
                $this->entityManager->persist($newDemande);

                // 3. CONTACTS
                if ($config->dupliquerContacts) {
                    foreach ($parcours->getContacts() as $contact) {
                        $newContact = clone $contact;
                        $this->resetEntityIdAndCollections($newContact);
                        $newContact->setParcours($cloneParcours);

                        $adresse = $contact->getAdresse();
                        if ($adresse !== null) {
                            $newAdresse = clone $adresse;
                            $this->resetEntityIdAndCollections($newAdresse);
                            $newAdresse->setAdresseOrigineCopie($adresse);
                            $this->entityManager->persist($newAdresse);
                            $newContact->setAdresse($newAdresse);
                        }

                        $this->entityManager->persist($newContact);
                        $createdCounts['contacts']++;
                    }
                }

                // 4. BLOCS DE COMPÉTENCES & COMPÉTENCES
                /** @var array<string, Competence> $mapCompetences */
                $mapCompetences = [];

                foreach ($parcours->getBlocCompetences() as $bloc) {
                    $newBloc = clone $bloc;
                    $this->resetEntityIdAndCollections($newBloc);
                    $newBloc->setParcours($cloneParcours);
                    $newBloc->setBlocCompetenceOrigineCopie($bloc);
                    $this->entityManager->persist($newBloc);
                    $createdCounts['blocs']++;

                    foreach ($bloc->getCompetences() as $comp) {
                        $newComp = clone $comp;
                        $this->resetEntityIdAndCollections($newComp);
                        $newComp->setBlocCompetence($newBloc);
                        $newComp->setCompetenceOrigineCopie($comp);
                        $this->entityManager->persist($newComp);
                        $createdCounts['competences']++;

                        if ($comp->getCode() !== null) {
                            $mapCompetences[$comp->getCode()] = $newComp;
                        }
                        if ($comp->getId() !== null) {
                            $mapCompetences['id_' . $comp->getId()] = $newComp;
                        }
                    }
                }

                // 5. ANNÉES DE PARCOURS
                /** @var array<int, Annee> $mapAnnees */
                $mapAnnees = [];
                foreach ($parcours->getAnnees() as $annee) {
                    $newAnnee = clone $annee;
                    $this->resetEntityIdAndCollections($newAnnee);
                    $newAnnee->setParcours($cloneParcours);
                    $newAnnee->setAnneeOrigineCopie($annee);
                    $cloneParcours->addAnnee($newAnnee);
                    $this->entityManager->persist($newAnnee);
                    $createdCounts['annees']++;

                    if ($annee->getId() !== null) {
                        $mapAnnees[$annee->getId()] = $newAnnee;
                    }
                }

                // 6. SEMESTRES, UES, ECS, FICHES MATIÈRES ET MCCC
                /** @var array<int, FicheMatiere> $fichesCloneesCache */
                $fichesCloneesCache = [];

                foreach ($parcours->getSemestreParcours() as $sp) {
                    $oldSemestre = $sp->getSemestre();
                    if ($oldSemestre === null) {
                        continue;
                    }

                    $newSemestre = clone $oldSemestre;
                    $this->resetEntityIdAndCollections($newSemestre);
                    $newSemestre->setSemestreOrigineCopie($oldSemestre);
                    $newSemestre->setSemestreRaccroche(null);
                    $this->entityManager->persist($newSemestre);
                    $createdCounts['semestres']++;

                    $newSp = new SemestreParcours($newSemestre, $cloneParcours);
                    $newSp->setOrdre($sp->getOrdre());
                    $newSp->setCodeApogeeEtapeAnnee($sp->getCodeApogeeEtapeAnnee());
                    $newSp->setCodeApogeeEtapeVersion($sp->getCodeApogeeEtapeVersion());

                    if ($sp->getAnnee() !== null && $sp->getAnnee()->getId() !== null && isset($mapAnnees[$sp->getAnnee()->getId()])) {
                        $targetAnnee = $mapAnnees[$sp->getAnnee()->getId()];
                        $newSp->setAnnee($targetAnnee);
                        $targetAnnee->addParcoursSemestre($newSp);
                    }

                    $cloneParcours->addSemestreParcour($newSp);
                    $this->entityManager->persist($newSp);

                    // Recopie récursive des UEs
                    foreach ($oldSemestre->getUes() as $ue) {
                        if ($ue->getUeParent() === null) {
                            $this->duplicateUe(
                                $ue,
                                null,
                                $newSemestre,
                                $cloneParcours,
                                $mapCompetences,
                                $config,
                                $campagneCollecte,
                                $fichesCloneesCache,
                                $createdCounts
                            );
                        }
                    }
                }

                $this->entityManager->flush();

                $this->logger->info('Duplication du parcours {oldId} vers {newId} réussie avec succès', [
                    'oldId' => $parcours->getId(),
                    'newId' => $cloneParcours->getId(),
                    'counts' => $createdCounts,
                ]);

                return ParcoursDuplicationResult::success($cloneParcours, $createdCounts);
            });
        } catch (Throwable $e) {
            $this->logger->error('Erreur lors de la duplication du parcours {id}: {message}', [
                'id' => $parcours->getId(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ParcoursDuplicationResult::failure(
                'Une erreur technique est survenue lors de la duplication : ' . $e->getMessage()
            );
        }
    }

    /**
     * Clone une UE et traite récursivement ses enfants et éléments constitutifs.
     *
     * @param array<string, Competence> $mapCompetences
     * @param array<int, FicheMatiere> $fichesCloneesCache
     * @param array<string, int> $createdCounts
     */
    private function duplicateUe(
        Ue $ue,
        ?Ue $parentUe,
        Semestre $newSemestre,
        Parcours $newParcours,
        array $mapCompetences,
        ParcoursDuplicationConfig $config,
        CampagneCollecte $campagneCollecte,
        array &$fichesCloneesCache,
        array &$createdCounts
    ): void {
        $newUe = clone $ue;
        $this->resetEntityIdAndCollections($newUe);
        $newUe->setSemestre($newSemestre);
        $newUe->setUeParent($parentUe);
        $newUe->setUeOrigineCopie($ue);
        $this->entityManager->persist($newUe);
        $createdCounts['ues']++;

        // Traitement des éléments constitutifs racines de l'UE
        foreach ($ue->getElementConstitutifs() as $ec) {
            if ($ec->getEcParent() === null) {
                $this->duplicateEc(
                    $ec,
                    null,
                    $newUe,
                    $newParcours,
                    $mapCompetences,
                    $config,
                    $campagneCollecte,
                    $fichesCloneesCache,
                    $createdCounts
                );
            }
        }

        // Traitement récursif des UEs enfants
        foreach ($ue->getUeEnfants() as $ueEnfant) {
            $this->duplicateUe(
                $ueEnfant,
                $newUe,
                $newSemestre,
                $newParcours,
                $mapCompetences,
                $config,
                $campagneCollecte,
                $fichesCloneesCache,
                $createdCounts
            );
        }
    }

    /**
     * Clone un élément constitutif et traite récursivement ses sous-éléments et sa fiche matière.
     *
     * @param array<string, Competence> $mapCompetences
     * @param array<int, FicheMatiere> $fichesCloneesCache
     * @param array<string, int> $createdCounts
     */
    private function duplicateEc(
        ElementConstitutif $ec,
        ?ElementConstitutif $parentEc,
        Ue $newUe,
        Parcours $newParcours,
        array $mapCompetences,
        ParcoursDuplicationConfig $config,
        CampagneCollecte $campagneCollecte,
        array &$fichesCloneesCache,
        array &$createdCounts
    ): void {
        $newEc = clone $ec;
        $this->resetEntityIdAndCollections($newEc);
        $newEc->setUe($newUe);
        $newEc->setEcParent($parentEc);
        $newEc->setParcours($newParcours);
        $newEc->setEcOrigineCopie($ec);
        $this->entityManager->persist($newEc);
        $createdCounts['ecs']++;

        // Compétences sur l'EC
        foreach ($ec->getCompetences() as $comp) {
            $compCode = $comp->getCode();
            $compId = $comp->getId();
            if ($compCode !== null && isset($mapCompetences[$compCode])) {
                $newEc->addCompetence($mapCompetences[$compCode]);
            } elseif ($compId !== null && isset($mapCompetences['id_' . $compId])) {
                $newEc->addCompetence($mapCompetences['id_' . $compId]);
            } else {
                $newEc->addCompetence($comp);
            }
        }

        // MCCC sur l'EC
        foreach ($ec->getMcccs() as $mccc) {
            $newMccc = clone $mccc;
            $this->resetEntityIdAndCollections($newMccc);
            $newMccc->setEc($newEc);
            $newMccc->setFicheMatiere(null);
            $newEc->addMccc($newMccc);
            $this->entityManager->persist($newMccc);
            $createdCounts['mcccs']++;
        }

        // Fiche Matière associée
        $oldFiche = $ec->getFicheMatiere();
        if ($oldFiche !== null) {
            $oldFicheId = $oldFiche->getId();

            if ($config->dupliquerFichesMatieres) {
                // Vérifier si cette fiche a déjà été clonée pour un autre EC dans le même parcours
                if ($oldFicheId !== null && isset($fichesCloneesCache[$oldFicheId])) {
                    $newFiche = $fichesCloneesCache[$oldFicheId];
                } else {
                    $newFiche = clone $oldFiche;
                    $this->resetEntityIdAndCollections($newFiche);
                    $newFiche->prepareCloneForNewAnnee();

                    $newSlug = $this->generateUniqueFicheSlug((string)$oldFiche->getSlug());
                    $newFiche->setSlug($newSlug);
                    $newFiche->setParcours($newParcours);
                    $newFiche->setCampagneCollecte($campagneCollecte);
                    $newFiche->setFicheMatiereOrigineCopie($oldFiche);

                    if (!$config->conserverResponsables) {
                        $newFiche->setResponsableFicheMatiere(null);
                    }

                    foreach ($oldFiche->getLangueDispense() as $langue) {
                        $newFiche->addLangueDispense($langue);
                    }

                    foreach ($oldFiche->getLangueSupport() as $langue) {
                        $newFiche->addLangueSupport($langue);
                    }

                    // MCCC de la fiche matière
                    foreach ($oldFiche->getMcccs() as $oldFmMccc) {
                        $newFmMccc = clone $oldFmMccc;
                        $this->resetEntityIdAndCollections($newFmMccc);
                        $newFmMccc->setFicheMatiere($newFiche);
                        $newFmMccc->setEc(null);
                        $newFiche->addMccc($newFmMccc);
                        $this->entityManager->persist($newFmMccc);
                        $createdCounts['mcccs']++;
                    }

                    // Compétences et apprentissages critiques sur la fiche
                    foreach ($oldFiche->getCompetences() as $fComp) {
                        $fCompCode = $fComp->getCode();
                        $fCompId = $fComp->getId();
                        if ($fCompCode !== null && isset($mapCompetences[$fCompCode])) {
                            $newFiche->addCompetence($mapCompetences[$fCompCode]);
                        } elseif ($fCompId !== null && isset($mapCompetences['id_' . $fCompId])) {
                            $newFiche->addCompetence($mapCompetences['id_' . $fCompId]);
                        } else {
                            $newFiche->addCompetence($fComp);
                        }
                    }

                    foreach ($oldFiche->getApprentissagesCritiques() as $appCrit) {
                        $newFiche->addApprentissagesCritique($appCrit);
                    }

                    $this->entityManager->persist($newFiche);
                    $createdCounts['fiches_matieres']++;

                    if ($oldFicheId !== null) {
                        $fichesCloneesCache[$oldFicheId] = $newFiche;
                    }
                }

                $newEc->setFicheMatiere($newFiche);
            } else {
                // Liaison conservée vers la fiche existante (mutualisation)
                $newEc->setFicheMatiere($oldFiche);
            }
        }

        // Traitement récursif des sous-éléments constitutifs
        foreach ($ec->getEcEnfants() as $ecEnfant) {
            $this->duplicateEc(
                $ecEnfant,
                $newEc,
                $newUe,
                $newParcours,
                $mapCompetences,
                $config,
                $campagneCollecte,
                $fichesCloneesCache,
                $createdCounts
            );
        }
    }

    /**
     * Génère un slug unique pour une fiche matière dupliquée.
     */
    private function generateUniqueFicheSlug(string $baseSlug): string
    {
        $cleaned = preg_replace('/-copie-[a-z0-9]+$/', '', $baseSlug) ?? $baseSlug;
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $slug = sprintf('%s-copie-%s', $cleaned, $suffix);

        while ($this->ficheMatiereRepository->findOneBy(['slug' => $slug]) !== null) {
            $suffix = substr(bin2hex(random_bytes(4)), 0, 6);
            $slug = sprintf('%s-copie-%s', $cleaned, $suffix);
        }

        return $slug;
    }

    /**
     * Compte récursivement les MCCC, les ECs et répertorie les fiches matières pour une UE.
     *
     * @param list<int> $fichesMatieresIds
     */
    private function countMcccForUe(Ue $ue, int &$nbEcs, array &$fichesMatieresIds): int
    {
        $mcccCount = 0;

        foreach ($ue->getElementConstitutifs() as $ec) {
            $nbEcs++;
            $mcccCount += $ec->getMcccs()->count();

            $fiche = $ec->getFicheMatiere();
            if ($fiche !== null && $fiche->getId() !== null) {
                if (!in_array($fiche->getId(), $fichesMatieresIds, true)) {
                    $fichesMatieresIds[] = $fiche->getId();
                    $mcccCount += $fiche->getMcccs()->count();
                }
            }
        }

        foreach ($ue->getUeEnfants() as $ueEnfant) {
            $mcccCount += $this->countMcccForUe($ueEnfant, $nbEcs, $fichesMatieresIds);
        }

        return $mcccCount;
    }

    /**
     * Réinitialise via réflexion l'identifiant et toutes les collections Doctrine
     * d'une entité nouvellement clonée pour éviter tout conflit de mémoire ou persistance parasite.
     */
    private function resetEntityIdAndCollections(object $entity): void
    {
        $refClass = new ReflectionClass($entity);

        if ($refClass->hasProperty('id')) {
            $idProp = $refClass->getProperty('id');
            $idProp->setAccessible(true);
            $idProp->setValue($entity, null);
        }

        foreach ($refClass->getProperties() as $prop) {
            $type = $prop->getType();
            if ($type !== null) {
                $typeName = (string)$type;
                if (str_contains($typeName, Collection::class) || is_a($typeName, Collection::class, true)) {
                    $prop->setAccessible(true);
                    $prop->setValue($entity, new ArrayCollection());
                }
            }
        }
    }
}
