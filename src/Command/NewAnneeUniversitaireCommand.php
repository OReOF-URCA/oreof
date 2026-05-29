<?php

namespace App\Command;

use App\Entity\Adresse;
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
use App\Entity\Ue;
use App\Entity\UeMutualisable;
use App\Enums\TypeModificationDpeEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:new-annee-universitaire',
    description: 'Duplique tous les parcours et formations, pour créer une nouvelle année universitaire',
)]
class NewAnneeUniversitaireCommand extends Command
{
    private EntityManagerInterface $entityManager;

    private string $slugSuffix = "-2025";

    private int $idCampagneCollecte = 2;

    private int $stepFormationFlush =  35;

    private int $stepFicheMatiereFlush = 1000;

    private int $stepMutualisations = 1000;

    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'generate-full-database',
            mode: InputOption::VALUE_NONE,
            description: 'Copie tous les parcours disponibles'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '12500M');

        $io = new SymfonyStyle($input, $output);

        $generateFullDatabase = $input->getOption('generate-full-database');

        if($generateFullDatabase){
            $parcoursRepository = $this->entityManager->getRepository(Parcours::class);
            $ficheMatiereRepository = $this->entityManager->getRepository(FicheMatiere::class);

            $nowDate = new DateTime('now');

            $io->writeln("\nCommande pour copier les parcours et formations sur une nouvelle année universitaire.\n");
            $io->writeln("La commande va copier l'année actuelle pour créer l'année 2025-2026\n");
            if($io->ask("Souhaitez-vous poursuivre ? [Y/n]", 'n') === "Y"){
                /**
                 *
                 * FORMATION
                 *
                 */
                $formationArray = $this->entityManager->getRepository(Formation::class)->findBy([], ['id' => 'ASC']);
                $nbFormation = count($formationArray);
                $io->writeln("Copie des formations...");
                $io->progressStart($nbFormation);
                foreach($formationArray as $formation){
                    $formationClone = clone $formation;
                    $formationClone->setSlug($formation->getSlug() . $this->slugSuffix);
                    $formationClone->setFormationOrigineCopie($formation);
                    $formationClone->setCreated($nowDate);
                    $formationClone->setUpdated($nowDate);
                    $this->entityManager->persist($formationClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $formationArray = null;
                /**
                 *
                 * PARCOURS
                 *
                 */
                $io->writeln("Copie des parcours...");
                $parcoursArray = $parcoursRepository->findBy([], ['id' => 'ASC']);
                $nbParcours = count($parcoursArray);
                $io->progressStart($nbParcours);
                foreach($parcoursArray as $parcours){
                    $parcoursClone = clone $parcours;
                    $newCloneFormation = $this->entityManager->getRepository(Formation::class)
                        ->findOneBy(['formationOrigineCopie' => $parcours->getFormation()]);
                    $parcoursClone->setFormation($newCloneFormation);
                    $parcoursClone->setParcoursOrigineCopie($parcours);
                    $parcoursClone->setCreated($nowDate);
                    $parcoursClone->setUpdated($nowDate);
                    $this->entityManager->persist($parcoursClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $parcoursArray = null;
                /**
                 *
                 * DPE PARCOURS
                 *
                 */
                $io->writeln("Copie du DPE Parcours...");
                $dpeArray = $this->entityManager->getRepository(DpeParcours::class)->findBy([], ['id' => 'ASC']);
                $nbDpe = count($dpeArray);
                $io->progressStart($nbDpe);
                $newCampagneCollecte = $this->entityManager
                    ->getRepository(CampagneCollecte::class)
                    ->findOneById($this->idCampagneCollecte);
                foreach($dpeArray as $dpe){
                    $dpeParcoursClone = clone $dpe;
                    $newFormationDpe = $this->entityManager->getRepository(Formation::class)
                        ->findOneBy(['formationOrigineCopie' => $dpe->getFormation()]);
                    $newParcoursDpe = $parcoursRepository
                        ->findOneBy(['parcoursOrigineCopie' => $dpe->getParcours()]);
                    // État reconduction - OUVERT par défaut
                    if($dpeParcoursClone->getEtatReconduction() !== TypeModificationDpeEnum::NON_OUVERTURE){
                        $dpeParcoursClone->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
                    }
                    $dpeParcoursClone->setParcours($newParcoursDpe);
                    $dpeParcoursClone->setFormation($newFormationDpe);
                    $dpeParcoursClone->setCampagneCollecte($newCampagneCollecte);
                    $dpeParcoursClone->setEtatValidation(['soumis_central' => 1]);
                    $dpeParcoursClone->setCreated(new DateTime('now'));
                    $this->entityManager->persist($dpeParcoursClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $dpeArray = null;

                /**
                 *
                 * BLOCS DE COMPÉTENCES
                 *
                 */
                $blocCompArray = $this->entityManager->getRepository(BlocCompetence::class)->findBy([], ['id' => 'ASC']);
                $nbBlocC = count($blocCompArray);
                $io->writeln("Copie des blocs de compétences...");
                $io->progressStart($nbBlocC);
                foreach($blocCompArray as $bc){
                    $blocCompClone = clone $bc;
                    if($bc->getParcours() !== null){
                        $newLinkParcours = $this->entityManager->getRepository(Parcours::class)
                            ->findOneBy(['parcoursOrigineCopie' => $bc->getParcours()]);
                        $blocCompClone->setParcours($newLinkParcours);
                    }
                    if($bc->getFormation() !== null){
                        $newLinkFormation = $this->entityManager->getRepository(Formation::class)
                            ->findOneBy(['formationOrigineCopie' => $bc->getFormation()]);
                        $blocCompClone->setFormation($newLinkFormation);
                    }
                    $blocCompClone->setBlocCompetenceOrigineCopie($bc);
                    $this->entityManager->persist($blocCompClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $blocCompArray = null;

                /**
                 *
                 * COMPÉTENCES
                 *
                 */
                $competencesArray = $this->entityManager->getRepository(Competence::class)->findBy([], ['id' => 'ASC']);
                $nbCompetence = count($competencesArray);
                $io->writeln("Copie des compétences...");
                $io->progressStart($nbCompetence);
                foreach($competencesArray as $comp){
                    $competenceClone = clone $comp;
                    if($comp->getBlocCompetence() !== null){
                        $newLinkBlocComp = $this->entityManager->getRepository(BlocCompetence::class)
                            ->findOneBy(['blocCompetenceOrigineCopie' => $comp->getBlocCompetence()]);
                        $competenceClone->setBlocCompetence($newLinkBlocComp);
                    }
                    $competenceClone->setCompetenceOrigineCopie($comp);
                    $this->entityManager->persist($competenceClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $competencesArray = null;

                /**
                 *
                 * BUT COMPÉTENCES
                 *
                 */
                $butCompetencesArray = $this->entityManager->getRepository(ButCompetence::class)->findBy([], ['id' => 'ASC']);
                $nbButComp = count($butCompetencesArray);
                $io->writeln("Copie des compétences de BUT (but_competence)...");
                $io->progressStart($nbButComp);
                foreach($butCompetencesArray as $butComp){
                    $butCompetenceClone = clone $butComp;
                    $newLinkFormationButComp = $this->entityManager->getRepository(Formation::class)
                        ->findOneBy(['formationOrigineCopie' => $butComp->getFormation()]);

                    $butCompetenceClone->setFormation($newLinkFormationButComp);
                    $butCompetenceClone->setButCompetenceOrigineCopie($butComp);
                    $this->entityManager->persist($butCompetenceClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $butCompetencesArray = null;

                /**
                 *
                 * BUT NIVEAU
                 *
                 */
                $butNiveauArray = $this->entityManager->getRepository(ButNiveau::class)->findBy([], ['id' => 'ASC']);
                $nbButNiveau = count($butNiveauArray);
                $io->writeln("Copie des niveaux de BUT (but_niveau)...");
                $io->progressStart($nbButNiveau);
                foreach($butNiveauArray as $butNiv){
                    $butNiveauClone = clone $butNiv;
                    $newLinkButCompetence = $this->entityManager->getRepository(ButCompetence::class)
                        ->findOneBy(['butCompetenceOrigineCopie' => $butNiv->getCompetence()]);

                    $butNiveauClone->setCompetence($newLinkButCompetence);
                    $butNiveauClone->setButNiveauOrigineCopie($butNiv);
                    $this->entityManager->persist($butNiveauClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $butNiveauArray = null;

                /**
                 *
                 * BUT APPRENTISSAGE CRITIQUE
                 *
                 */
                $butApprentissageCritArray = $this->entityManager->getRepository(ButApprentissageCritique::class)
                    ->findBy([], ['id' => 'ASC']);
                $nbApprentissageCritique = count($butApprentissageCritArray);
                $io->writeln("Copie des apprentissages critiques des BUT...");
                $io->progressStart($nbApprentissageCritique);
                foreach($butApprentissageCritArray as $butAppCrit){
                    $butAppCritClone = clone $butAppCrit;
                    $newLinkButNiveau = $this->entityManager->getRepository(ButNiveau::class)
                        ->findOneBy(['butNiveauOrigineCopie' => $butAppCrit->getNiveau()]);

                    $butAppCritClone->setNiveau($newLinkButNiveau);
                    $butAppCritClone->setButApprentissageCritiqueOrigineCopie($butAppCrit);
                    $this->entityManager->persist($butAppCritClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $butApprentissageCritArray = null;

                /**
                 *
                 * FICHES MATIERES
                 *
                 */
                $nbFicheMatiere = count($this->entityManager->getRepository(FicheMatiere::class)->findBy([]));
                $processedFicheMatiere = 0;
                $io->writeln("Copie des fiches matières...");
                $io->progressStart($nbFicheMatiere);
                // Pagination de la table des fiches matières
                for($i = 0; $processedFicheMatiere < $nbFicheMatiere; $i++){
                    $ficheMatiereArray = array_slice(
                        $this->entityManager->getRepository(FicheMatiere::class)
                            ->findBy([], ['id' => 'ASC']),
                        ($i * $this->stepFicheMatiereFlush),
                        $this->stepFicheMatiereFlush
                    );
                    foreach($ficheMatiereArray as $ficheMatiere){
                        $ficheMatiereClone = clone $ficheMatiere;
                        $ficheMatiereClone->prepareCloneForNewAnnee();
                        // Le slug est unique, donc on le préfixe
                        $ficheMatiereClone->setSlug($ficheMatiereClone->getSlug() . $this->slugSuffix);
                        // Le parcours devient le nouveau
                        if($ficheMatiere->getParcours() !== null){
                            $ficheMatiereClone->setParcours(
                                $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $ficheMatiere->getParcours()])
                            );
                        }
                        /**
                         *
                         * Gestion des fiche_matiere_competence
                         *
                         */
                        foreach($ficheMatiere->getCompetences() as $compFM){
                            $newLinkCompFM = $this->entityManager->getRepository(Competence::class)
                                ->findOneBy(['competenceOrigineCopie' => $compFM]);
                            $ficheMatiereClone->addCompetence($newLinkCompFM);
                        }
                        /**
                         * Gestion des fiche_matiere_but_apprentissage_critique
                         *
                         */
                        foreach($ficheMatiere->getApprentissagesCritiques() as $appCrit){
                            $newLinkAppCrit = $this->entityManager->getRepository(ButApprentissageCritique::class)
                                ->findOneBy(['butApprentissageCritiqueOrigineCopie' => $appCrit]);
                            $ficheMatiereClone->addApprentissagesCritique($newLinkAppCrit);
                        }

                        $ficheMatiereClone->setFicheMatiereOrigineCopie($ficheMatiere);
                        $ficheMatiereClone->setCampagneCollecte($newCampagneCollecte);
                        // Sauvegarde en BD
                        $this->entityManager->persist($ficheMatiereClone);
                        ++$processedFicheMatiere;
                        $io->progressAdvance();
                    }
                }
                // Libération de la mémoire
                $ficheMatiereArray = null;
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                /**
                 *
                 * FICHES MATIERES MUTUALISABLES
                 *
                 */
                $nbMutualisations = count($this->entityManager->getRepository(FicheMatiereMutualisable::class)->findBy([]));
                $processedMutualisations = 0;
                $io->writeln("Copie des mutualisations des fiches matières...");
                $io->progressStart($nbMutualisations);
                for($i = 0; $processedMutualisations < $nbMutualisations; $i++){
                    $mutualisationArray = array_slice(
                        $this->entityManager->getRepository(FicheMatiereMutualisable::class)
                            ->findBy([], ['id' => 'ASC']),
                        ($i * $this->stepMutualisations),
                        $this->stepMutualisations
                    );
                    foreach($mutualisationArray as $mutualisationFM){
                        $mutualisationFMClone = clone $mutualisationFM;
                        if($mutualisationFM->getParcours() !== null){
                            $mutualisationFMClone->setParcours(
                                $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $mutualisationFM->getParcours()])
                            );
                        }
                        if($mutualisationFM->getFicheMatiere() !== null){
                            $mutualisationFMClone->setFicheMatiere(
                                $ficheMatiereRepository->findOneBy(['ficheMatiereOrigineCopie' => $mutualisationFM->getFicheMatiere()])
                            );
                        }
                        $this->entityManager->persist($mutualisationFMClone);
                        ++$processedMutualisations;
                        $io->progressAdvance();
                    }
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();

                /**
                 *
                 * SEMESTRES
                 *
                 */
                $nbSemestres = count($this->entityManager->getRepository(Semestre::class)->findBy([]));
                $semestreArray = $this->entityManager->getRepository(Semestre::class)->findBy([], ['id' => 'ASC']);
                $io->writeln('Copie des semestres...');
                $io->progressStart($nbSemestres);
                foreach($semestreArray as $semestre) {
                    $cloneSemestre = clone $semestre;
                    $cloneSemestre->setSemestreRaccroche(null);
                    $cloneSemestre->setSemestreOrigineCopie($semestre);
                    $this->entityManager->persist($cloneSemestre);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $semestreArray = null;
                /**
                 *
                 * SEMESTRES PARCOURS
                 *
                 */
                $nbSemestreParcours = count($this->entityManager->getRepository(SemestreParcours::class)->findBy([]));
                $semestreParcoursArray = $this->entityManager->getRepository(SemestreParcours::class)->findBy([], ['id' => 'ASC']);
                $io->writeln("Copie des données 'semestre_parcours'");
                $io->progressStart($nbSemestreParcours);
                foreach($semestreParcoursArray as $sp){
                    $newSemestreClone = $this->entityManager->getRepository(Semestre::class)
                        ->findOneBy(['semestreOrigineCopie' => $sp->getSemestre()]);
                    $newParcoursClone = $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $sp->getParcours()]);
                    $cloneSemestreParcours = clone $sp;
                    $cloneSemestreParcours->setParcours($newParcoursClone);
                    $cloneSemestreParcours->setSemestre($newSemestreClone);
                    $this->entityManager->persist($cloneSemestreParcours);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $semestreParcoursArray = null;
                /**
                 *
                 * SEMESTRES MUTUALISABLES et Semestre Raccroché
                 *
                 */
                $nbSemestreMutualisable = count($this->entityManager->getRepository(SemestreMutualisable::class)->findBy([]));
                $semestreMutualisableArray = $this->entityManager->getRepository(SemestreMutualisable::class)
                    ->findBy([], ['id' => 'ASC']);
                $io->writeln("Copie des semestres mutualisables...");
                $io->progressStart($nbSemestreMutualisable);
                foreach($semestreMutualisableArray as $semMutu){
                    // On clone la mutualisation, avec les nouvelles valeurs
                    $newCloneSemestreMutualise = clone $semMutu;
                    $parcoursCloneMutualise = $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $semMutu->getParcours()]);
                    $semestreCloneMutualise = $this->entityManager->getRepository(Semestre::class)
                        ->findOneBy(['semestreOrigineCopie' => $semMutu->getSemestre()]);
                    $newCloneSemestreMutualise->setSemestre($semestreCloneMutualise);
                    $newCloneSemestreMutualise->setParcours($parcoursCloneMutualise);

                    // On récupère le semestre qui avait la mutualisation en semestre raccroché
                    $semestreARaccrocherArray = $this->entityManager->getRepository(Semestre::class)->findBy(['semestreRaccroche' => $semMutu]);
                    foreach($semestreARaccrocherArray as $semestreARaccrocher){
                        $raccrochageSemestre = $this->entityManager->getRepository(Semestre::class)
                            ->findOneBy(['semestreOrigineCopie' => $semestreARaccrocher]);

                        $raccrochageSemestre->setSemestreRaccroche($newCloneSemestreMutualise);
                        $this->entityManager->persist($raccrochageSemestre);
                    }
                    // Et on enregistre
                    $this->entityManager->persist($newCloneSemestreMutualise);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $semestreMutualisableArray = null;

                /**
                 *
                 * UE
                 *
                 */
                $nbUe = count($this->entityManager->getRepository(Ue::class)->findBy([]));
                $ueArray = $this->entityManager->getRepository(Ue::class)->findBy([], ['id' => 'ASC']);
                $io->writeln('Copie des UE...');
                $io->progressStart($nbUe);
                foreach($ueArray as $ue){
                    $ueClone = clone $ue;
                    $ueClone->setUeParent(null);
                    $ueClone->setUeRaccrochee(null);
                    if($ue->getSemestre() !== null){
                        $semestreUe = $this->entityManager->getRepository(Semestre::class)
                            ->findOneBy(['semestreOrigineCopie' => $ue->getSemestre()]);
                        $ueClone->setSemestre($semestreUe);
                    }
                    $ueClone->setUeOrigineCopie($ue);
                    $this->entityManager->persist($ueClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $ueArray = null;
                /**
                 *
                 * UE PARENT
                 *
                 */
                $io->writeln("Copie des UE parents...");
                $ueDataArray = $this->entityManager->getRepository(Ue::class)->findBy([], ['id' => 'ASC']);
                $nbUeFromDb = count($ueDataArray);
                $io->progressStart($nbUeFromDb);
                foreach($ueDataArray as $ueData){
                    if($ueData->getUeParent() !== null && $ueData->getUeOrigineCopie() === null){
                        $newClonedUe = $this->entityManager->getRepository(Ue::class)
                            ->findOneBy(['ueOrigineCopie' => $ueData]);
                        $newClonedParent = $this->entityManager->getRepository(Ue::class)
                            ->findOneBy(['ueOrigineCopie' => $ueData->getUeParent()]);
                        $newClonedUe->setUeParent($newClonedParent);
                        $this->entityManager->persist($newClonedUe);
                    }
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $ueDataArray = null;
                /**
                 *
                 * UE MUTUALISEES et Ue Raccrochées
                 *
                 */
                $io->writeln("Copie des UE mutualisées...");
                $ueMutualiseesArray = $this->entityManager->getRepository(UeMutualisable::class)->findBy([], ['id' => 'ASC']);
                $nbUeMutualisees = count($ueMutualiseesArray);
                $io->progressStart($nbUeMutualisees);
                foreach($ueMutualiseesArray as $ueMutu){
                    // On clone l'UE mutualisable
                    $ueMutuClone = clone $ueMutu;
                    $newParcours = $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $ueMutu->getParcours()]);
                    $newUe = $this->entityManager->getRepository(Ue::class)->findOneBy(['ueOrigineCopie' => $ueMutu->getUe()]);
                    $ueMutuClone->setParcours($newParcours);
                    $ueMutuClone->setUe($newUe);
                    // Et on crée le lien dans ue (ue_raccroche_id)
                    $ueRaccrocherArray = $this->entityManager->getRepository(Ue::class)->findBy(['ueRaccrochee' => $ueMutu]);
                    foreach($ueRaccrocherArray as $ueRaccrocher){
                        $raccrochageUe = $this->entityManager->getRepository(Ue::class)
                            ->findOneBy(['ueOrigineCopie' => $ueRaccrocher]);
                        $raccrochageUe->setUeRaccrochee($ueMutuClone);
                        $this->entityManager->persist($raccrochageUe);
                    }
                    $this->entityManager->persist($ueMutuClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $ueMutualiseesArray = null;

                /**
                 *
                 * ELEMENT CONSTITUTIF
                 *
                 */
                $elementConstitutifArray = $this->entityManager->getRepository(ElementConstitutif::class)->findBy([], ['id' => 'ASC']);
                $nbEc = count($elementConstitutifArray);
                $io->writeln("Copie des éléments constitutifs...");
                $io->progressStart($nbEc);
                foreach($elementConstitutifArray as $ec){
                    $ecClone = clone $ec;
                    $ecClone->prepareCloneForNewAnnee();
                    // Liens vers les nouveaux éléments
                    $newEcFm = null;
                    $newEcUe = null;
                    $newEcParcours = null;
                    if($ec->getFicheMatiere() !== null){
                        $newEcFm = $this->entityManager->getRepository(FicheMatiere::class)
                            ->findOneBy(['ficheMatiereOrigineCopie' => $ec->getFicheMatiere()]);
                    }
                    if($ec->getUe() !== null){
                        $newEcUe = $this->entityManager->getRepository(Ue::class)
                            ->findOneBy(['ueOrigineCopie' => $ec->getUe()]);
                    }
                    if($ec->getParcours() !== null){
                        $newEcParcours = $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $ec->getParcours()]);
                    }
                    if($newEcFm !== null){
                        $ecClone->setFicheMatiere($newEcFm);
                    }
                    if($newEcUe !== null){
                        $ecClone->setUe($newEcUe);
                    }
                    if($newEcParcours !== null){
                        $ecClone->setParcours($newEcParcours);
                    }
                    // EC competence
                    foreach($ec->getCompetences() as $ecCompetence){
                        $newEcCompetence = $this->entityManager->getRepository(Competence::class)
                            ->findOneBy(['competenceOrigineCopie' => $ecCompetence]);
                        $ecClone->addCompetence($newEcCompetence);
                    }

                    $ecClone->setEcOrigineCopie($ec);
                    $this->entityManager->persist($ecClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $elementConstitutifArray = null;

                /**
                 *
                 * EC PARENT
                 *
                 */
                $ecArray = $this->entityManager->getRepository(ElementConstitutif::class)->findBy([], ['id' => 'ASC']);
                $nbEcForParent = count($ecArray);
                $io->writeln("Copie des EC parents...");
                $io->progressStart($nbEcForParent);
                foreach($ecArray as $elemConstitutif){
                    if($elemConstitutif->getEcParent() !== null && $elemConstitutif->getEcOrigineCopie() === null){
                        $ecParentCopie = $this->entityManager->getRepository(ElementConstitutif::class)
                            ->findOneBy(['ecOrigineCopie' => $elemConstitutif->getEcParent()]);
                        $ecEnfantCopie = $this->entityManager->getRepository(ElementConstitutif::class)
                            ->findOneBy(['ecOrigineCopie' => $elemConstitutif]);
                        $ecEnfantCopie->setEcParent($ecParentCopie);
                        $this->entityManager->persist($ecEnfantCopie);
                    }
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $ecArray = null;

                /**
                 *
                 * ADRESSES
                 *
                 * Doivent être dupliquées car il y a une contrainte 'UNIQUE'
                 * sur la table contact
                 *
                 */
                $adresseArray = $this->entityManager->getRepository(Adresse::class)->findBy([], ['id' => 'ASC']);
                $nbAdresse = count($adresseArray);
                $io->writeln("Copie des adresses de contacts...");
                $io->progressStart($nbAdresse);
                foreach($adresseArray as $adresse){
                    $adresseClone = clone $adresse;
                    $adresseClone->setAdresseOrigineCopie($adresse);
                    $this->entityManager->persist($adresseClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $adresseArray = null;
                /**
                 *
                 * CONTACTS DU PARCOURS
                 *
                 */
                $contactsArray = $this->entityManager->getRepository(Contact::class)->findBy([], ['id' => 'ASC']);
                $nbContact = count($contactsArray);
                $io->writeln("Copie des contacts des parcours...");
                $io->progressStart($nbContact);
                foreach($contactsArray as $contact){
                    $contactClone = clone $contact;
                    $newParcoursContact = $parcoursRepository->findOneBy(['parcoursOrigineCopie' => $contact->getParcours()]);
                    $newAdresseContact = $this->entityManager->getRepository(Adresse::class)
                        ->findOneBy(['adresseOrigineCopie' => $contact->getAdresse()]);
                    $contactClone->setParcours($newParcoursContact);
                    $contactClone->setAdresse($newAdresseContact);
                    $this->entityManager->persist($contactClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $contactsArray = null;

                /**
                 *
                 * MCCC
                 *
                 */
                $mcccArray = $this->entityManager->getRepository(Mccc::class)->findBy([], ['id' => 'ASC']);
                $nbMccc = count($mcccArray);
                $io->writeln("Copie des MCCC...");
                $io->progressStart($nbMccc);
                foreach($mcccArray as $mccc){
                    $mcccClone = clone $mccc;
                    $mcccClone->setEc(null);
                    $mcccClone->setFicheMatiere(null);
                    // S'il y a une fiche matière à copier
                    if($mccc->getFicheMatiere() !== null){
                        $newLinkFm = $this->entityManager->getRepository(FicheMatiere::class)
                            ->findOneBy(['ficheMatiereOrigineCopie' => $mccc->getFicheMatiere()]);
                        $mcccClone->setFicheMatiere($newLinkFm);
                    }
                    // S'il y a un EC à copier
                    if($mccc->getEc() !== null){
                        $newLinkEc = $this->entityManager->getRepository(ElementConstitutif::class)
                            ->findOneBy(['ecOrigineCopie' => $mccc->getEc()]);
                        $mcccClone->setEc($newLinkEc);
                    }
                    $this->entityManager->persist($mcccClone);
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->writeln("Application des changements...");
                $this->entityManager->flush();
                $mcccArray = null;

                $io->success("Copie réussie !");
                return Command::SUCCESS;
            }

            $io->writeln("Commande annulée");
            return Command::SUCCESS;
        }

        $io->warning("Option non reconnue. Doit être parmi ['generate-full-database']");
        return Command::SUCCESS;
    }

}
