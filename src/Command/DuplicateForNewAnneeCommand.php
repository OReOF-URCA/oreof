<?php

namespace App\Command;

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
    name: 'app:duplicate-for-new-annee',
    description: "Duplique les données d'une année universitaire, pour créer la suivante.",
)]
class DuplicateForNewAnneeCommand extends Command
{
    /**
     * CONFIGURATION
     */
    private const EXECUTION_MEMORY_LIMIT = '12500M';

    private const CODE_APOGEE_CAMPAGNE_COLLECTE = '6';

    private const SLUG_YEAR_SUFFIX = '-2026';

    /* ********** */

    private EntityManagerInterface $entityManager;

    private array $initialisationErrorValue = [];

    private array $entitiesArray = [];

    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'annee-source',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Identifiant (PK) de la campagne de collecte à dupliquer.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', self::EXECUTION_MEMORY_LIMIT);

        $io = new SymfonyStyle($input, $output);

        $anneeSource = $input->getOption('annee-source');

        // Sélection de l'année à dupliquer
        if(isset($anneeSource) === false) {
            $io->error("Il faut définir l'année à copier (PK : Campagne Collecte) !");
            return Command::INVALID;
        }

        $campagneCollecteSource = $this->entityManager
            ->getRepository(CampagneCollecte::class)
            ->findOneById($anneeSource);

        // Vérification si la campagne de collecte sélectionnée
        // existe en base de données
        if(!$campagneCollecteSource) {
            $io->error("Aucune campagne de collecte n'a été trouvée pour cet identifiant ({$anneeSource}).");
            return Command::INVALID;
        }

        $promptLibelle = $this->getInitialisationLibelleArray();

        $io->writeln("Initialisation de la nouvelle année universitaire (BD) à créer...");
        $newAnneeUniversitaire = new AnneeUniversitaire();
        $libelleNewAnnee = $io->ask($promptLibelle['libelleNewAnnee']['askPrompt']);
        $anneeNewAnnee = $io->ask($promptLibelle['anneeNewAnnee']['askPrompt']);

        $io->writeln("Initialisation de la nouvelle campagne de collecte à créer (BD)...");
        $newCampagneCollecte = new CampagneCollecte();
        $libelleNewCampagne = $io->ask($promptLibelle['libelleNewCampagne']['askPrompt']);
        $anneeNewCampagne = $io->ask($promptLibelle['anneeNewCampagne']['askPrompt']);
        $dateOuvertureNewCampagne = $io->ask($promptLibelle['dateOuvertureNewCampagne']['askPrompt']);
        $dateClotureNewCampagne = $io->ask($promptLibelle['dateClotureNewCampagne']['askPrompt']);
        $dateTransmissionSesNewCampagne = $io->ask($promptLibelle['dateTransmissionSesNewCampagne']['askPrompt']);
        $dateCfvuNewCampagne = $io->ask($promptLibelle['dateCfvuNewCampagne']['askPrompt']);
        $datePublicationNewCampagne = $io->ask($promptLibelle['datePublicationNewCampagne']['askPrompt']);

        // Liens entre les données saisies dans la commande,
        // les libellés, messages d'erreurs, et les futurs objets
        $testInitialisationStructure = [
            [
                'type' => 'doubleYear',
                'libelle' => 'libelleNewAnnee',
                'value' => $libelleNewAnnee
            ],
            [
                'type' => 'year',
                'libelle' => 'anneeNewAnnee',
                'value' => $anneeNewAnnee
            ],
            [
                'type' => 'doubleYear',
                'libelle' => 'libelleNewCampagne',
                'value' => $libelleNewCampagne
            ],
            [
                'type' => 'year',
                'libelle' => 'anneeNewCampagne',
                'value' => $anneeNewCampagne
            ],
            [
                'type' => 'fullDate',
                'libelle' => 'dateOuvertureNewCampagne',
                'value' => $dateOuvertureNewCampagne
            ],
            [
                'type' => 'fullDate',
                'libelle' => 'dateClotureNewCampagne',
                'value' => $dateClotureNewCampagne
            ],
            [
                'type' => 'fullDate',
                'libelle' => 'dateTransmissionSesNewCampagne',
                'value' => $dateTransmissionSesNewCampagne
            ],
            [
                'type' => 'fullDate',
                'libelle' => 'dateCfvuNewCampagne',
                'value' => $dateCfvuNewCampagne
            ],
            [
                'type' => 'fullDate',
                'libelle' => 'datePublicationNewCampagne',
                'value' => $datePublicationNewCampagne
            ],
        ];

        $this->testInitialisationInput($testInitialisationStructure);

        // Vérification des formats des données, pour alimenter les objets ORM.
        if(count($this->initialisationErrorValue) > 0) {
            foreach($this->initialisationErrorValue as $errorToDisplay) {
                $errorTxt = $promptLibelle[$errorToDisplay['name']]['errorMessage'] . " ({$errorToDisplay['value']}) ";
                $io->writeln($errorTxt);
            }

            $io->warning("Certaines données rentrées sont invalides !");
            return Command::INVALID;
        }

        // S'il n'y a pas d'erreur, on poursuit en alimentant les objets
        // créés, et on les enregistre en base de données.

        // Nouvelle Année Universitaire
        $newAnneeUniversitaire->setLibelle($libelleNewAnnee);
        $newAnneeUniversitaire->setAnnee($anneeNewAnnee);

        // Préparation de l'enregistrement en base de données
        $this->entityManager->persist($newAnneeUniversitaire);

        // Nouvelle Campagne de Collecte
        $newCampagneCollecte->setLibelle($libelleNewCampagne);
        $newCampagneCollecte->setAnnee($anneeNewCampagne);
        $newCampagneCollecte->setDefaut(false);
        $newCampagneCollecte->setDateOuvertureDpe($this->createDateOrEmpty($dateOuvertureNewCampagne));
        $newCampagneCollecte->setDateClotureDpe($this->createDateOrEmpty($dateClotureNewCampagne));
        $newCampagneCollecte->setDateTransmissionSes($this->createDateOrEmpty($dateTransmissionSesNewCampagne));
        $newCampagneCollecte->setDateCfvu($this->createDateOrEmpty($dateCfvuNewCampagne));
        $newCampagneCollecte->setDatePublication($this->createDateOrEmpty($datePublicationNewCampagne));
        $newCampagneCollecte->setAnneeUniversitaire($newAnneeUniversitaire);
        // Code APOGEE
        $newCampagneCollecte->setCodeApogee(self::CODE_APOGEE_CAMPAGNE_COLLECTE);

        // Préparation de l'enregistrement en base de données
        $this->entityManager->persist($newCampagneCollecte);

        /**
         * 
         * FORMATION
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Formation::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Formations'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $formation) {
            $nowDate = new DateTime('now');
            $initialFormation = $this->entityManager->getRepository(Formation::class)
                ->findOneById($formation);
            $cloneFormation = clone $initialFormation;
            $cloneFormation->setSlug($initialFormation->getSlug() . self::SLUG_YEAR_SUFFIX);
            $cloneFormation->setFormationOrigineCopie($initialFormation);
            $cloneFormation->setDpe($newCampagneCollecte);
            $cloneFormation->setCreated($nowDate);
            $cloneFormation->setUpdated($nowDate);

            $this->entityManager->persist($cloneFormation);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);
        
        /**
         * 
         * PARCOURS
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Parcours::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Parcours'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $parcours) {
            $nowDate = new DateTime('now');
            $initialParcours = $this->entityManager->getRepository(Parcours::class)
                ->findOneById($parcours);
            $linkFormationParcours = $this->entityManager->getRepository(Formation::class)
                ->findOneBy(['formationOrigineCopie' => $initialParcours->getFormation()]);
            $cloneParcours = clone $initialParcours;
            $cloneParcours->setParcoursOrigineCopie($initialParcours);
            $cloneParcours->setFormation($linkFormationParcours);
            $cloneParcours->setCreated($nowDate);
            $cloneParcours->setUpdated($nowDate);

            $this->entityManager->persist($cloneParcours);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * DPE PARCOURS 
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(DpeParcours::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'DPE Parcours'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $dpeParcours) {
            $nowDate = new DateTime('now');
            $initialDpeParcours = $this->entityManager->getRepository(DpeParcours::class)
                ->findOneById($dpeParcours);
            $linkFormationDpe = $this->entityManager->getRepository(Formation::class)
                ->findOneBy(['formationOrigineCopie' => $initialDpeParcours->getFormation()]);
            $linkParcoursDpe = $this->entityManager->getRepository(Parcours::class)
                ->findOneBy(['parcoursOrigineCopie' => $initialDpeParcours->getParcours()]);
            $cloneDpeParcours = clone $initialDpeParcours;
            if($cloneDpeParcours->getEtatReconduction() !== TypeModificationDpeEnum::NON_OUVERTURE){
                $cloneDpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            }
            $cloneDpeParcours->setParcours($linkParcoursDpe);
            $cloneDpeParcours->setFormation($linkFormationDpe);
            $cloneDpeParcours->setCampagneCollecte($newCampagneCollecte);
            $cloneDpeParcours->setEtatValidation(['tacite_reconduction' => 1]);
            $cloneDpeParcours->setCreated($nowDate);

            $this->entityManager->persist($cloneDpeParcours);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * BLOCS DE COMPÉTENCES
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(BlocCompetence::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Blocs de Compétences'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $blocCompetence) {
            $initialBlocCompetence = $this->entityManager->getRepository(BlocCompetence::class)
                ->findOneById($blocCompetence);
            $cloneBlocCompetence = clone $initialBlocCompetence;
            if($initialBlocCompetence->getParcours() !== null){
                $linkBlocCompetenceParcours = $this->entityManager->getRepository(Parcours::class)
                    ->findOneBy(['parcoursOrigineCopie' => $initialBlocCompetence->getParcours()]);
                $cloneBlocCompetence->setParcours($linkBlocCompetenceParcours);
            }
            if($initialBlocCompetence->getFormation() !== null){
                $linkBlocCompetenceFormation = $this->entityManager->getRepository(Formation::class)
                    ->findOneBy(['formationOrigineCopie' => $initialBlocCompetence->getFormation()]);
                $cloneBlocCompetence->setFormation($linkBlocCompetenceFormation);
            }
            $cloneBlocCompetence->setBlocCompetenceOrigineCopie($initialBlocCompetence);

            $this->entityManager->persist($cloneBlocCompetence);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * COMPÉTENCES
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Competence::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Compétences'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $competence) {
            $initialCompetence = $this->entityManager->getRepository(Competence::class)
                ->findOneById($competence);
            $cloneCompetence = clone $initialCompetence;
            if($initialCompetence->getBlocCompetence() !== null){
                $linkCompetenceBlocCompetence = $this->entityManager->getRepository(BlocCompetence::class)
                    ->findOneBy(['blocCompetenceOrigineCopie' => $initialCompetence->getBlocCompetence()]);
                $cloneCompetence->setBlocCompetence($linkCompetenceBlocCompetence);
            }
            $cloneCompetence->setCompetenceOrigineCopie($initialCompetence);

            $this->entityManager->persist($cloneCompetence);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * BUT COMPÉTENCES
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(ButCompetence::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'BUT Compétences'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $butCompetence) {
            $initialButCompetence = $this->entityManager->getRepository(ButCompetence::class)
                ->findOneById($butCompetence);
            $cloneButCompetence = clone $initialButCompetence;
            if($initialButCompetence->getFormation() !== null) {
                $linkButCompetenceFormation = $this->entityManager->getRepository(Formation::class)
                    ->findOneBy(['formationOrigineCopie' => $initialButCompetence->getFormation()]);
                $cloneButCompetence->setFormation($linkButCompetenceFormation);
            }
            $cloneButCompetence->setButCompetenceOrigineCopie($initialButCompetence);
            $cloneButCompetence->setCampagneCollecte($newCampagneCollecte);

            $this->entityManager->persist($cloneButCompetence);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * BUT NIVEAU
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(ButNiveau::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'BUT Niveaux'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $butNiveau) {
            $initialButNiveau = $this->entityManager->getRepository(ButNiveau::class)
                ->findOneById($butNiveau);
            $cloneButNiveau = clone $initialButNiveau;
            $linkButNiveauButCompetence = $this->entityManager->getRepository(ButCompetence::class)
                ->findOneBy(['butCompetenceOrigineCopie' => $initialButNiveau->getCompetence()]);
            $cloneButNiveau->setCompetence($linkButNiveauButCompetence);
            $cloneButNiveau->setButNiveauOrigineCopie($initialButNiveau);

            $this->entityManager->persist($cloneButNiveau);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * BUT APPRENTISSAGE CRITIQUE
         * 
         */
        $this->entitiesArray = $this->entityManager
            ->getRepository(ButApprentissageCritique::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'BUT Apprentissage Critique'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $butAppCrit) {
            $initialButApprentissageCrit = $this->entityManager
                ->getRepository(ButApprentissageCritique::class)
                ->findOneById($butAppCrit);
            $cloneButApprentissageCrit = clone $initialButApprentissageCrit;
            $linkButAppCritButNiveau = $this->entityManager->getRepository(ButNiveau::class)
                ->findOneBy(['butNiveauOrigineCopie' => $initialButApprentissageCrit->getNiveau()]);
            $cloneButApprentissageCrit->setNiveau($linkButAppCritButNiveau);
            $cloneButApprentissageCrit->setButApprentissageCritiqueOrigineCopie($initialButApprentissageCrit);

            $this->entityManager->persist($cloneButApprentissageCrit);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * FICHE MATIERE
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(FicheMatiere::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Fiches Matières'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ficheMatiere) {
            $initialFicheMatiere = $this->entityManager->getRepository(FicheMatiere::class)
                ->findOneById($ficheMatiere);
            $cloneFicheMatiere = clone $initialFicheMatiere;
            $cloneFicheMatiere->prepareCloneForNewAnnee();
            // fiche_matiere
            $cloneFicheMatiere->setSlug($this->getNewSlugForFicheMatiere($initialFicheMatiere->getSlug()));
            if($initialFicheMatiere->getParcours() !== null){
                $linkFicheMParcours = $this->entityManager->getRepository(Parcours::class)
                    ->findOneBy(['parcoursOrigineCopie' => $initialFicheMatiere->getParcours()]);
                $cloneFicheMatiere->setParcours($linkFicheMParcours);
            }
            // fiche_matiere_competence
            foreach($initialFicheMatiere->getCompetences() as $initialFMCompetence) {
                $linkCompetenceFM = $this->entityManager->getRepository(Competence::class)
                    ->findOneBy(['competenceOrigineCopie' => $initialFMCompetence]);
                if($linkCompetenceFM !== null){
                    $cloneFicheMatiere->addCompetence($linkCompetenceFM);
                }
            }
            // fiche_matiere_but_apprentissage_critique
            foreach($initialFicheMatiere->getApprentissagesCritiques() as $initialFMAppCrit) {
                $linkAppCritFM = $this->entityManager->getRepository(ButApprentissageCritique::class)
                    ->findOneBy(['butApprentissageCritiqueOrigineCopie' => $initialFMAppCrit]);
                if($linkAppCritFM !== null){
                    $cloneFicheMatiere->addApprentissagesCritique($linkAppCritFM);
                }
            }

            $cloneFicheMatiere->setFicheMatiereOrigineCopie($initialFicheMatiere);
            $cloneFicheMatiere->setCampagneCollecte($newCampagneCollecte);

            $this->entityManager->persist($cloneFicheMatiere);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * FICHE MATIERE MUTUALISABLE
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(FicheMatiereMutualisable::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Fiches Matières Mutualisables'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ficheMutu) {
            $initialFicheMutu = $this->entityManager->getRepository(FicheMatiereMutualisable::class)
                ->findOneById($ficheMutu);
            $cloneFicheMutu = clone $initialFicheMutu;
            if($initialFicheMutu->getParcours() !== null){
                $linkFicheMutuParcours = $this->entityManager->getRepository(Parcours::class)
                    ->findOneBy(['parcoursOrigineCopie' => $initialFicheMutu->getParcours()]);
                $cloneFicheMutu->setParcours($linkFicheMutuParcours);
            }
            if($initialFicheMutu->getFicheMatiere() !== null){
                $linkFicheMutuFicheMatiere = $this->entityManager->getRepository(FicheMatiere::class)
                    ->findOneBy(['ficheMatiereOrigineCopie' => $initialFicheMutu->getFicheMatiere()]);
                $cloneFicheMutu->setFicheMatiere($linkFicheMutuFicheMatiere);
            }

            $this->entityManager->persist($cloneFicheMutu);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * SEMESTRE
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Semestre::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Semestres'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $semestre) {
            $initialSemestre = $this->entityManager->getRepository(Semestre::class)
                ->findOneById($semestre);
            $cloneSemestre = clone $initialSemestre;
            // Le semestre raccroché est pris en compte après
            $cloneSemestre->setSemestreRaccroche(null);
            $cloneSemestre->setSemestreOrigineCopie($initialSemestre);

            $this->entityManager->persist($cloneSemestre);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * SEMESTRES PARCOURS & ANNÉES
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(SemestreParcours::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Semestres Parcours'...");
        $io->progressStart(count($this->entitiesArray));
        $tabAnnee = [];
        foreach($this->entitiesArray as $semestreParcours) {
            $initialSemestreParcours = $this->entityManager->getRepository(SemestreParcours::class)
                ->findOneById($semestreParcours);
            $linkSemestreParcoursSemestre = $this->entityManager->getRepository(Semestre::class)
                ->findOneBy(['semestreOrigineCopie' => $initialSemestreParcours->getSemestre()]);
            $linkSemestreParcoursParcours = $this->entityManager->getRepository(Parcours::class)
                ->findOneBy(['parcoursOrigineCopie' => $initialSemestreParcours->getParcours()]);
            $cloneSemestreParcours = clone $initialSemestreParcours;
            $cloneSemestreParcours->setSemestre($linkSemestreParcoursSemestre);
            $cloneSemestreParcours->setParcours($linkSemestreParcoursParcours);

            //si ordre impair on ajoute l'année, si ordre pair on récupère l'année n-1. Il faut stocker les années dans le parcours
            $ordreSemestre = (int)$cloneSemestreParcours->getOrdre();
            // calcul de l'ordre de l'année : (1|2)->1, (3|4)->2, ...
            $ordreAnnee = intdiv($ordreSemestre + 1, 2);
            if ($cloneSemestreParcours->getOrdre() % 2 !== 0) {
                $annee = new Annee();
                $annee->setParcours($cloneSemestreParcours->getParcours());
                $annee->setOrdre($ordreAnnee);
                $annee->setCodeApogeeEtapeAnnee($cloneSemestreParcours->getCodeApogeeEtapeAnnee());
                $annee->setCodeApogeeEtapeVersion($cloneSemestreParcours->getCodeApogeeEtapeVersion());
                $tabAnnee[$cloneSemestreParcours->getParcours()?->getId()][$cloneSemestreParcours->getOrdre()] = $annee;
                $this->entityManager->persist($annee);
            } else {
                $annee = $tabAnnee[$cloneSemestreParcours->getParcours()?->getId()][$cloneSemestreParcours->getOrdre() - 1];
            }
            $annee->addParcoursSemestre($cloneSemestreParcours);

            $this->entityManager->persist($cloneSemestreParcours);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $tabAnnee = [];
        $this->saveAndCleanUp($io);

        /**
         * 
         * SEMESTRES MUTUALISABLES & Semestres Raccrochés
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(SemestreMutualisable::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Semestres Mutualisables'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $semestreMutualisable) {
            $initialSemestreMutu = $this->entityManager->getRepository(SemestreMutualisable::class)
                ->findOneById($semestreMutualisable);
            $linkSemestreMutuSemestre = $this->entityManager->getRepository(Semestre::class)
                ->findOneBy(['semestreOrigineCopie' => $initialSemestreMutu->getSemestre()]);
            $linkSemestreMutuParcours = $this->entityManager->getRepository(Parcours::class)
                ->findOneBy(['parcoursOrigineCopie' => $initialSemestreMutu->getParcours()]);
            $cloneSemestreMutu = clone $initialSemestreMutu;
            $cloneSemestreMutu->setSemestre($linkSemestreMutuSemestre);
            $cloneSemestreMutu->setParcours($linkSemestreMutuParcours);

            // Semestres Raccrochés
            $semestreARaccrocherArray = $this->entityManager->getRepository(Semestre::class)
                ->findBy(['semestreRaccroche' => $initialSemestreMutu]);
            foreach($semestreARaccrocherArray as $semestreARaccrocher) {
                $raccrochageSemestre = $this->entityManager->getRepository(Semestre::class)
                    ->findOneBy(['semestreOrigineCopie' => $semestreARaccrocher]);

                if($raccrochageSemestre !== null){
                    $raccrochageSemestre->setSemestreRaccroche($cloneSemestreMutu);
                    $this->entityManager->persist($raccrochageSemestre);
                }
            }

            $this->entityManager->persist($cloneSemestreMutu);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * UE
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Ue::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'UE'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ue) {
            $initialUe = $this->entityManager->getRepository(Ue::class)->findOneById($ue);
            $cloneUe = clone $initialUe;
            $cloneUe->setUeParent(null);
            $cloneUe->setUeRaccrochee(null);
            if($initialUe->getSemestre() !== null) {
                $linkUeSemestre = $this->entityManager->getRepository(Semestre::class)
                    ->findOneBy(['semestreOrigineCopie' => $initialUe->getSemestre()]);
                $cloneUe->setSemestre($linkUeSemestre);
            }
            $cloneUe->setUeOrigineCopie($initialUe);

            $this->entityManager->persist($cloneUe);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * UE PARENT
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Ue::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'UE Parents'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ueToProcess) {
            $initialUeToProcess = $this->entityManager->getRepository(Ue::class)
                ->findOneById($ueToProcess);
            if($initialUeToProcess->getUeParent() !== null){
                $clonedUe = $this->entityManager->getRepository(Ue::class)
                    ->findOneBy(['ueOrigineCopie' => $initialUeToProcess]);
                $newParentForClone = $this->entityManager->getRepository(Ue::class)
                    ->findOneBy(['ueOrigineCopie' => $initialUeToProcess->getUeParent()]);

                $clonedUe->setUeParent($newParentForClone);
                $this->entityManager->persist($clonedUe);
            }

            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * UE MUTUALISEES et UE Raccrochées
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(UeMutualisable::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'UE Mutualisables'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ueMutualisable) {
            $initialUeMutualisable = $this->entityManager->getRepository(UeMutualisable::class)
                ->findOneById($ueMutualisable);
            $cloneUeMutualisable = clone $initialUeMutualisable;
            if($initialUeMutualisable->getUe() !== null) {
                $linkUeMutuUe = $this->entityManager->getRepository(Ue::class)
                    ->findOneBy(['ueOrigineCopie' => $initialUeMutualisable->getUe()]);
                $cloneUeMutualisable->setUe($linkUeMutuUe);
            }
            if($initialUeMutualisable->getParcours() !== null) {
                $linkUeMutuParcours = $this->entityManager->getRepository(Parcours::class)
                    ->findOneBy(['parcoursOrigineCopie' => $initialUeMutualisable->getParcours()]);
                $cloneUeMutualisable->setParcours($linkUeMutuParcours);
            }
            // UE à raccrocher
            $ueARaccrocherArray = $this->entityManager->getRepository(Ue::class)
                ->findBy(['ueRaccrochee' => $initialUeMutualisable]);
            foreach($ueARaccrocherArray as $ueRaccroche) {
                $raccrochageUe = $this->entityManager->getRepository(Ue::class)
                    ->findOneBy(['ueOrigineCopie' => $ueRaccroche]);
                
                if($raccrochageUe !== null){
                    $raccrochageUe->setUeRaccrochee($cloneUeMutualisable);
                    $this->entityManager->persist($raccrochageUe);
                }
            }

            $this->entityManager->persist($cloneUeMutualisable);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * ELEMENT CONSTITUTIF
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(ElementConstitutif::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Éléments Constitutifs'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ec) {
            $initialEc = $this->entityManager->getRepository(ElementConstitutif::class)
                ->findOneById($ec);
            $cloneEc = clone $initialEc;
            $cloneEc->prepareCloneForNewAnnee();
            $linkEcFicheM = null;
            $linkEcUe = null;
            $linkEcParcours = null;
            if($initialEc->getFicheMatiere() !== null) {
                $linkEcFicheM = $this->entityManager->getRepository(FicheMatiere::class)
                    ->findOneBy(['ficheMatiereOrigineCopie' => $initialEc->getFicheMatiere()]);
            }
            if($initialEc->getUe() !== null) {
                $linkEcUe = $this->entityManager->getRepository(Ue::class)
                    ->findOneBy(['ueOrigineCopie' => $initialEc->getUe()]);
            }
            if($initialEc->getParcours() !== null) {
                $linkEcParcours = $this->entityManager->getRepository(Parcours::class)
                    ->findOneBy(['parcoursOrigineCopie' => $initialEc->getParcours()]);
            }
            $cloneEc->setFicheMatiere($linkEcFicheM);
            $cloneEc->setUe($linkEcUe);
            $cloneEc->setParcours($linkEcParcours);
            // Compétences
            foreach($initialEc->getCompetences() as $ecCompetence) {
                $linkEcCompetence = $this->entityManager->getRepository(Competence::class)
                    ->findOneBy(['competenceOrigineCopie' => $ecCompetence]);
                $cloneEc->addCompetence($linkEcCompetence);
            }

            $cloneEc->setEcParent(null);
            $cloneEc->setEcOrigineCopie($initialEc);
            $this->entityManager->persist($cloneEc);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * EC PARENT
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(ElementConstitutif::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'EC Parents'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $ecParentToLink) {
            $initialEcParentToLink = $this->entityManager->getRepository(ElementConstitutif::class)
                ->findOneById($ecParentToLink);
            if($initialEcParentToLink->getEcParent() !== null) {
                $clonedChild = $this->entityManager->getRepository(ElementConstitutif::class)
                    ->findOneBy(['ecOrigineCopie' => $initialEcParentToLink]);
                $clonedParent = $this->entityManager->getRepository(ElementConstitutif::class)
                    ->findOneBy(['ecOrigineCopie' => $initialEcParentToLink->getEcParent()]);
                if($clonedChild !== null && $clonedParent !== null) {
                    $clonedChild->setEcParent($clonedParent);
                    
                    $this->entityManager->persist($clonedChild);
                }
            }

            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * ADRESSES & CONTACTS
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Contact::class)
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'Adresses' et des 'Contacts'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $contact) {
            $initialContact = $this->entityManager->getRepository(Contact::class)
                ->findOneById($contact);
            $linkContactParcours = $this->entityManager->getRepository(Parcours::class)
                ->findOneBy(['parcoursOrigineCopie' => $initialContact->getParcours()]);

            $cloneContact = clone $initialContact;
            $cloneAdresse = clone $initialContact->getAdresse();
            $cloneAdresse->setAdresseOrigineCopie($initialContact->getAdresse());
            $cloneContact->setParcours($linkContactParcours);
            $cloneContact->setAdresse($cloneAdresse);

            $this->entityManager->persist($cloneAdresse);
            $this->entityManager->persist($cloneContact);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        /**
         * 
         * MCCC
         * 
         */
        $this->entitiesArray = $this->entityManager->getRepository(Mccc::class) 
            ->findFromAnneeUniversitaire($anneeSource);
        $io->writeln("Copie des 'MCCC'...");
        $io->progressStart(count($this->entitiesArray));
        foreach($this->entitiesArray as $mccc) {
            $initialMccc = $this->entityManager->getRepository(Mccc::class)
                ->findOneById($mccc);
            $cloneMccc = clone $initialMccc;
            $cloneMccc->setEc(null);
            $cloneMccc->setFicheMatiere(null);
            if($initialMccc->getFicheMatiere() !== null) {
                $linkMcccFicheMatiere = $this->entityManager->getRepository(FicheMatiere::class)
                    ->findOneBy(['ficheMatiereOrigineCopie' => $initialMccc->getFicheMatiere()]);
                $cloneMccc->setFicheMatiere($linkMcccFicheMatiere);
            }
            if($initialMccc->getEc() !== null) {
                $linkMcccElementConstitutif = $this->entityManager->getRepository(ElementConstitutif::class)
                    ->findOneBy(['ecOrigineCopie' => $initialMccc->getEc()]);
                $cloneMccc->setEc($linkMcccElementConstitutif);
            }

            $this->entityManager->persist($cloneMccc);
            $io->progressAdvance(1);
        }

        $io->progressFinish();
        $this->saveAndCleanUp($io);

        // Fin de la commande (succès)
        $io->success("La commande s'est exécutée correctement !");

        return Command::SUCCESS;
    }

    private function checkYear(string $yearToCheck) {
        return preg_match('/^[0-9]{4}$/', $yearToCheck) === 1;
    }

    private function checkDoubleYear(string $doubleYearToCheck) {
        return preg_match('/^[0-9]{4}-[0-9]{4}$/', $doubleYearToCheck) === 1;
    }

    private function checkFullDate(string $dateToCheck) {
        return 
            (   preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $dateToCheck) === 1
                && DateTime::createFromFormat('Y-m-d', $dateToCheck) !== false 
            )
            || $dateToCheck === 'empty';
    }

    private function createDateOrEmpty(string $dateOrEmpty) : DateTime|null {
        if($dateOrEmpty === 'empty'){
            return null;
        }

        return new DateTime($dateOrEmpty);
    }

    private function getInitialisationLibelleArray() {
        return [
            'libelleNewAnnee' => [
                    'askPrompt' => "Libellé de la nouvelle année universitaire [YYYY-YYYY]",
                    'errorMessage' => "Libellé de la nouvelle année universitaire invalide."
                ], 
            'anneeNewAnnee' => [
                    'askPrompt' => "Année de la nouvelle année universitaire [YYYY]",
                    'errorMessage' => "Année de la nouvelle année universitaire invalide."
                ], 
            'libelleNewCampagne' => [
                    'askPrompt' => "Libellé de la nouvelle Campagne de Collecte [YYYY-YYYY]",
                    'errorMessage' => "Libellé de la nouvelle Campagne de Collecte invalide."
                ],
            'anneeNewCampagne' => [
                    'askPrompt' => "Année de la nouvelle Campagne de Collecte [YYYY]",
                    'errorMessage' => "Année de la nouvelle Campagne de Collecte invalide."
                ], 
            'dateOuvertureNewCampagne' => [
                    'askPrompt' => "Date d'ouverture du DPE pour la nouvelle Campagne de Collecte [YYYY-MM-DD]",
                    'errorMessage' => "Date d'ouverture du DPE invalide."
                ], 
            'dateClotureNewCampagne' => [
                    'askPrompt' => "Date de clôture du DPE pour la nouvelle Campagne de Collecte [YYYY-MM-DD]",
                    'errorMessage' => "Date de clôture du DPE invalide."
                ],
            'dateTransmissionSesNewCampagne' => [
                    'askPrompt' => "Date de transmission aux SES pour la nouvelle Campagne de Collecte [YYYY-MM-DD]",
                    'errorMessage' => "Date de transmission aux SES invalide."
                ],
            'dateCfvuNewCampagne' => [
                    'askPrompt' => "Date de la CFVU pour la nouvelle Campagne de Collecte [YYYY-MM-DD]",
                    'errorMessage' => "Date de la CFVU invalide."
                ],
            'datePublicationNewCampagne' => [
                    'askPrompt' => "Date de publication pour la nouvelle Campagne de Collecte [YYYY-MM-DD]",
                    'errorMessage' => "Date de publication invalide."
                ],
        ];
    }

    private function testInitialisationInput(array $inputArray) {
        foreach($inputArray as $inputData){
            switch($inputData['type']){
                case 'year':
                    if($this->checkYear($inputData['value']) === false) {
                        $this->initialisationErrorValue[] = [
                            'name' => $inputData['libelle'],
                            'value' => $inputData['value']
                        ];
                    }
                    break;
                case 'doubleYear': 
                    if($this->checkDoubleYear($inputData['value']) === false) {
                        $this->initialisationErrorValue[] = [
                            'name' => $inputData['libelle'], 
                            'value' => $inputData['value']
                        ];
                    }
                    break;
                case 'fullDate': 
                    if($this->checkFullDate($inputData['value']) === false) {
                        $this->initialisationErrorValue[] = [
                            'name' => $inputData['libelle'],
                            'value' => $inputData['value']
                        ];
                    }
                    break;
            }
        }
    }

    private function emptyEntitiesArray() : void {
        $this->entitiesArray = [];
    } 

    private function saveAndCleanUp(SymfonyStyle $io) : void {
        $this->emptyEntitiesArray();
        $io->writeln("Enregistrement en base de données...");
        $this->entityManager->flush();
    }

    private function getNewSlugForFicheMatiere(string $initialSlug) : string {
        if(preg_match('/(.*)-2025$/', $initialSlug, $matches)) {
            return $matches[1] . self::SLUG_YEAR_SUFFIX;
        }

        return $initialSlug . self::SLUG_YEAR_SUFFIX;
    }
}