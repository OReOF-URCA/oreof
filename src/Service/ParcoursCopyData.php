<?php

namespace App\Service;

use App\Classes\MyGotenbergPdf;
use App\DTO\HeuresEctsEc;
use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\Ue;
use App\Repository\ElementConstitutifCopyRepository;
use App\Repository\FicheMatiereCopyRepository;
use App\Repository\McccCopyRepository;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class ParcoursCopyData {

    private EntityManagerInterface $entityManager;

    private ?EntityManagerInterface $entityManagerCopy = null;

    private ?ElementConstitutifCopyRepository $ecCopyRepo = null;

    private ?FicheMatiereCopyRepository $fmCopyRepo = null;

    private ?McccCopyRepository $mcccCopyRepo = null;

    private MyGotenbergPdf $myPdf;

    public static array $errorMessageArray = [];

    public static array $errorMcccMessageArray = [];

    private array $ficheMatiereCopyDataArray = [];

    private array $ficheMatiereEctsCopyArray = [];

    private array $mcccCopyDataArray = [];


    public function __construct(
        ManagerRegistry $doctrine,
        MyGotenbergPdf $myPdf,
        private readonly TypeDiplomeResolver $typeDiplomeResolver
    ){
        $em = $doctrine->getManager('default');
        assert($em instanceof EntityManagerInterface);
        $this->entityManager = $em;
        try {
            $emCopy = $doctrine->getManager('parcours_copy');
            if ($emCopy instanceof EntityManagerInterface) {
                $this->entityManagerCopy = $emCopy;
                $this->ecCopyRepo = new ElementConstitutifCopyRepository($this->entityManagerCopy, ElementConstitutif::class);
                $this->fmCopyRepo = new FicheMatiereCopyRepository($this->entityManagerCopy, FicheMatiere::class);
                $this->mcccCopyRepo = new McccCopyRepository($this->entityManagerCopy, Mccc::class);
            }
        } catch (\Throwable) {
            $this->entityManagerCopy = null;
        }

        $this->myPdf = $myPdf;
    }

    public function copyDataForAllParcoursInDatabase(SymfonyStyle $io): void
    {
        $io->writeln("Commande pour copier les heures des matières sur la nouvelle base de données.");

        $formationArray = $this->entityManager->getRepository(Formation::class)->findAll();
        $nombreParcours = array_sum(
            array_map(fn($f) => count($f->getParcours()),
                array_filter($formationArray, fn($f) => $f->getTypeDiplome()->getLibelleCourt() !== "BUT")
            )
        );
        $io->writeln("Début de la copie des heures sur les fiches matières...");
        $io->progressStart($nombreParcours);

        foreach($formationArray as $formation){
            if($formation->getTypeDiplome()->getLibelleCourt() !== "BUT"){
                foreach($formation->getParcours() as $parcours){
                    $this->copyDataForParcoursFromDTO($parcours);
                    $io->progressAdvance();
                }
            }
        }
        $io->progressFinish();

        $io->writeln("Deuxième parcours de la base de données pour les heures spécifiques...");
        $io->progressStart($nombreParcours);
        foreach($formationArray as $f){
            if($f->getTypeDiplome()->getLibelleCourt() !== "BUT"){
                foreach($f->getParcours() as $p){
                    $this->copyDataForParcoursFromDTO($p, onlyHeuresSpecifiques: true);
                    $io->progressAdvance();
                }
            }
        }
        $io->progressFinish();

        $io->writeln("Traitement des ECTS spécifiques...");
        $io->progressStart($nombreParcours);
        foreach($formationArray as $f){
            if($f->getTypeDiplome()->getLibelleCourt() !== "BUT"){
                foreach($f->getParcours() as $p){
                    $this->copyDataForParcoursFromDTO($p, onlyEctsSpecifiques: true);
                    $io->progressAdvance();
                }
            }
        }
        $io->progressFinish();

        $io->writeln("Traitement de la copie des MCCC...");
        $io->progressStart($nombreParcours);
        foreach($formationArray as $f){
            if($f->getTypeDiplome()->getLibelleCourt() !== "BUT"){
                foreach($f->getParcours() as $parcours){
                    $this->copyDataForParcoursFromDTO($parcours, onlyMccc: true);
                    $io->progressAdvance();
                }
            }
        }
        $io->progressFinish();

        $io->writeln("Application des changements...");
        $this->entityManagerCopy->flush();

        $io->writeln("Second parcours pour les MCCC spécifiques...");
        $io->progressStart($nombreParcours);
        foreach($formationArray as $f){
            if($f->getTypeDiplome()->getLibelleCourt() !== "BUT"){
                foreach($f->getParcours() as $parcours){
                    $this->copyDataForParcoursFromDTO($parcours, onlyEcMcccSpecifiques: true);
                    $io->progressAdvance();
                }
            }
        }
        $io->progressFinish();

        $io->writeln("Application des changements...");
        $this->entityManagerCopy->flush();
        $io->success("La copie s'est exécutée avec succès !");
    }

    public function copyDataForParcours(Parcours $parcours): void
    {
        foreach($parcours->getSemestreParcours() as $semestreParcours){
            $semestre = $this->getSemestre($semestreParcours->getSemestre());
            if($semestre){
                $ueArray = $this->entityManager->getRepository(Ue::class)->getBySemestre($semestre);
                foreach($ueArray as $ueData){
                    $ue = $this->getUe($ueData);
                    $this->copyDataForUe($ue, $parcours->getId());
                    foreach($ue->getUeEnfants() as $ueEnfantData){
                        $ueEnfant = $this->getUe($ueEnfantData);
                        $this->copyDataForUe($ueEnfant, $parcours->getId());
                        foreach($ueEnfant->getUeEnfants() as $ueEnfantDeuxiemeData){
                            $ueEnfantDeuxieme = $this->getUe($ueEnfantDeuxiemeData);
                            $this->copyDataForUe($ueEnfantDeuxieme, $parcours->getId());
                        }
                    }
                }
            }
        }
    }

    public function copyDataForParcoursFromDTO(
        Parcours $parcours,
        bool $onlyHeuresSpecifiques = false,
        bool $onlyMccc = false,
        bool $onlyEcMcccSpecifiques = false,
        bool $onlyEctsSpecifiques = false,
    ): void
    {
        $dto = $this->getDTOForParcours($parcours);
        foreach($dto->semestres as $semestre){
            foreach($semestre->ues as $ue){
                $this->copyDataForUeFromDTO($ue, $parcours->getId(), $onlyHeuresSpecifiques, $onlyMccc, $onlyEcMcccSpecifiques, $onlyEctsSpecifiques);
                foreach($ue->uesEnfants() as $ueEnfant){
                    $this->copyDataForUeFromDTO($ueEnfant, $parcours->getId(), $onlyHeuresSpecifiques, $onlyMccc, $onlyEcMcccSpecifiques, $onlyEctsSpecifiques);
                    foreach($ueEnfant->uesEnfants() as $ueEnfantDeuxieme){
                        $this->copyDataForUeFromDTO($ueEnfantDeuxieme, $parcours->getId(), $onlyHeuresSpecifiques, $onlyMccc, $onlyEcMcccSpecifiques, $onlyEctsSpecifiques);
                    }
                }
            }
        }
    }

    private function copyDataForUe(Ue $ue, int $parcoursId): void
    {
        $elementConstitutifArray = $this->entityManager->getRepository(ElementConstitutif::class)
            ->getByUe($ue);
        foreach($elementConstitutifArray as $ec){
            $this->copyDataOnFicheMatiere($ec, $ec->getFicheMatiere(), $parcoursId);
            foreach($ec->getEcEnfants() as $ecEnfant){
                $this->copyDataOnFicheMatiere($ecEnfant, $ecEnfant->getFicheMatiere(), $parcoursId);
            }
        }
    }

    private function copyDataForUeFromDTO(
        StructureUe $structUE,
        int $parcoursId,
        bool $onlyHeuresSpecifiques,
        bool $onlyMccc,
        bool $onlyEcMcccSpecifiques,
        bool $onlyEctsSpecifiques,
    ): void
    {
        foreach($structUE->elementConstitutifs as $ec){
            if($onlyHeuresSpecifiques){
                $this->placeHeuresSpecifiquesFlag($ec);
            }
            elseif($onlyMccc){
                $this->moveMcccToFicheMatiere($ec, $parcoursId);
            } elseif($onlyEcMcccSpecifiques){
                $this->placeMcccSpecifiquesFlag($ec);
            }
            elseif($onlyEctsSpecifiques){
                $this->placeEctsSpecifiquesFlag($ec);
            }else {
                $this->copyDataOnFicheMatiere($ec->elementConstitutif, $ec->elementConstitutif->getFicheMatiere(), $parcoursId);
            }
            foreach($ec->elementsConstitutifsEnfants as $ecEnfant){
                if($onlyHeuresSpecifiques){
                    $isHeuresIdentiques = $ec->elementConstitutif->isHeuresEnfantsIdentiques();
                    $this->placeHeuresSpecifiquesFlag($ecEnfant, $isHeuresIdentiques);
                }elseif($onlyMccc){
                    $this->moveMcccToFicheMatiere($ecEnfant, $parcoursId);
                }elseif($onlyEcMcccSpecifiques){
                    $this->placeMcccSpecifiquesFlag($ecEnfant);
                }elseif($onlyEctsSpecifiques){
                    $this->placeEctsSpecifiquesFlag($ecEnfant);
                }
                else {
                    if($ec->elementConstitutif->isHeuresEnfantsIdentiques()){
                        $this->copyDataOnFicheMatiere(
                            $ec->elementConstitutif,
                            $ecEnfant->elementConstitutif->getFicheMatiere(),
                            $parcoursId,
                            isHeuresEnfantIdentiques: true
                        );
                    }else {
                        $this->copyDataOnFicheMatiere($ecEnfant->elementConstitutif, $ecEnfant->elementConstitutif->getFicheMatiere(), $parcoursId);
                    }
                }
            }
        }
    }

    private function copyDataOnFicheMatiere(
        ElementConstitutif $ecSource,
        ?FicheMatiere $ficheMatiereSource,
        int $parcoursId,
        bool $isHeuresEnfantIdentiques = false
    ): void
    {
//todo: gérer l'info "EC sans heure" dans la fiche ? Cas des EC extra académique ?
        if($ficheMatiereSource){
            $isVolumeHoraireFMImpose = $ficheMatiereSource->isVolumesHorairesImpose();
            $ecFromParcours = $ecSource->getParcours()?->getId() === $ficheMatiereSource->getParcours()?->getId();
            $ficheMatiereFromParcours = $ficheMatiereSource->getParcours()?->getId() === $parcoursId;
            // $hasEcParentHeures = $ecSource->getEcParent()?->isHeuresEnfantsIdentiques();
            $hasSynchroHeures = $ecSource->isSynchroHeures();
            $isHorsDiplome = $ficheMatiereSource->isHorsDiplome();
            // Si la fiche matière a un EC porteur (parcours de la fiche matière = parcours de l'EC)
            $hasFicheMatiereEcPorteur = array_filter(
                $ficheMatiereSource->getElementConstitutifs()->toArray(),
                fn($ecFM) => $ecFM->getParcours()?->getId() === $ficheMatiereSource->getParcours()?->getId()
                    && $ecFM->getParcours()?->getId() !== null && $ficheMatiereSource->getParcours()?->getId() !== null
            );
            $hasFicheMatiereEcPorteur = count($hasFicheMatiereEcPorteur) > 0;
            $countEcForFiche = count($ficheMatiereSource->getElementConstitutifs()->toArray());
            $ecEcts = null;
            $ec = null;

            $isEcPorteur = false;
            // Si l'EC et la FM font partie du parcours
            if($ficheMatiereFromParcours && $ecFromParcours){
                $ec = $ecSource;
                $isEcPorteur = true;
                $ecEcts = $ecSource->getEcParent() ?: $ecSource;
            }
            // Si la fiche n'a pas d'EC porteur, on prend le premier
            if($hasFicheMatiereEcPorteur === false || $countEcForFiche === 1){
                $ec = $ficheMatiereSource->getElementConstitutifs()->first();
                $ecEcts = $ficheMatiereSource->getElementConstitutifs()->first();
            }
            // Si l'EC fait partie d'une UE mutualisée (portée par un autre parcours)
            if($ecSource->getUe()?->getUeMutualisables()->count() > 0){
                if($ecSource->getUe()->getSemestre()->getSemestreParcours()->first()->getParcours()->getId() === $parcoursId){
                    $ec = $ecSource;
                    $isEcPorteur = true;
                    $ecEcts = $ecSource;
                }
            }

            // Cas où il y a la valeur 'synchro heures'
            if($hasSynchroHeures){
                if(count($ficheMatiereSource->getElementConstitutifs()->toArray()) >= 2){
                    $ecPorteur = $ficheMatiereSource->getElementConstitutifs()->filter(
                        fn($ec) => $ec->getParcours()?->getId() === $ficheMatiereSource->getParcours()?->getId()
                            && $ec->getParcours()?->getId() !== null && $ficheMatiereSource->getParcours()?->getId() !== null
                    );
                    if(count($ecPorteur) > 0){
                        $ec = $ecPorteur->first();
                    }
                }elseif(count($ficheMatiereSource->getElementConstitutifs()->toArray()) === 1){
                    $ec = $ficheMatiereSource->getElementConstitutifs()->first();
                }
            }
            // Cas où il y a la valeur 'heure enfant identique'
            if($isHeuresEnfantIdentiques && $ficheMatiereFromParcours && $ecFromParcours){
                $ec = $ecSource;
            }
            if(($isEcPorteur || $hasFicheMatiereEcPorteur === false || $countEcForFiche === 1)
                && $this->hasHeuresFicheMatiereCopy($ficheMatiereSource) === false
                && $ec !== null
            ) {
                $ficheMatiereFromCopy = $this->fmCopyRepo->find($ficheMatiereSource->getId());
                // Si le volume est imposé ou que la FM est hors diplôme, les heures sont déjà dessus
                if(!$isVolumeHoraireFMImpose && !$isHorsDiplome){

                    $ficheMatiereFromCopy->setVolumeCmPresentiel($ec->getVolumeCmPresentiel());
                    $ficheMatiereFromCopy->setVolumeTdPresentiel($ec->getVolumeTdPresentiel());
                    $ficheMatiereFromCopy->setVolumeTpPresentiel($ec->getVolumeTpPresentiel());
                    $ficheMatiereFromCopy->setVolumeCmDistanciel($ec->getVolumeCmDistanciel());
                    $ficheMatiereFromCopy->setVolumeTdDistanciel($ec->getVolumeTdDistanciel());
                    $ficheMatiereFromCopy->setVolumeTpDistanciel($ec->getVolumeTpDistanciel());
                    $ficheMatiereFromCopy->setVolumeTe($ec->getVolumeTe());

                    $this->ficheMatiereCopyDataArray[$ficheMatiereSource->getId()] = [
                        'cmPres' => $ec->getVolumeCmPresentiel(),
                        'tdPres' => $ec->getVolumeTdPresentiel(),
                        'tpPres' => $ec->getVolumeTpPresentiel(),
                        'cmDist' => $ec->getVolumeCmDistanciel(),
                        'tdDist' => $ec->getVolumeTdDistanciel(),
                        'tpDist' => $ec->getVolumeTpDistanciel(),
                        'te' => $ec->getVolumeTe(),
                    ];
                }

                // ECTS
                if( $ecEcts !== null
                    && $this->hasFicheMatiereEcts($ficheMatiereSource) === false
                ){
                    $ects = $ecEcts->getEcts();
                    if(is_null($ects) === false){
                        if(!$ficheMatiereFromCopy->isEctsImpose()){
                            $ficheMatiereFromCopy->setEcts($ects);
                            $this->ficheMatiereEctsCopyArray[$ficheMatiereSource->getId()] = $ects;
                        }
                    }
                }

                $this->entityManagerCopy->persist($ficheMatiereFromCopy);
            }
        }
    }

    public function placeHeuresSpecifiquesFlag(StructureEc $ec, bool $isHeuresIdentiques = false): void
    {
        if($ec->elementConstitutif->getFicheMatiere()){
            $isDifferent = $this->hasHeuresFicheMatiereCopy($ec->elementConstitutif->getFicheMatiere())
                && $this->hasEcSameHeuresAsFicheMatiereCopy($ec->heuresEctsEc, $ec->elementConstitutif->getFicheMatiere()) === false;

            // Si différent, que les heures ne sont pas sur le parent, ni sur la FM
            if( ( $isDifferent && !$isHeuresIdentiques
                && !$ec->elementConstitutif->getFicheMatiere()->isHorsDiplome()
                && !$ec->elementConstitutif->getFicheMatiere()->isVolumesHorairesImpose() )
                || ($ec->elementConstitutif->isSansHeure())
            ){
                $ecCopyFlag = $this->ecCopyRepo->find($ec->elementConstitutif->getId());
                $ecCopyFlag->setHeuresSpecifiques(true);
                $this->entityManagerCopy->persist($ecCopyFlag);
            }
        }
    }

    public function placeEctsSpecifiquesFlag(StructureEc $ec): void
    {
        if($ec->elementConstitutif->getFicheMatiere()){
            $isDifferent = $this->hasFicheMatiereEcts($ec->elementConstitutif->getFicheMatiere())
            && $this->hasEctsDifferent($ec);

            if( $ec->elementConstitutif->getEcParent() === null
                && $isDifferent === true
                && !$ec->elementConstitutif->getFicheMatiere()->isEctsImpose()
            ){
                $ecCopyEcts = $this->ecCopyRepo->find($ec->elementConstitutif->getId());
                $ecCopyEcts->setEctsSpecifiques(true);
                $this->entityManagerCopy->persist($ecCopyEcts);
            }
        }
    }

    public function moveMcccToFicheMatiere(
        StructureEc $structEc,
        int $parcoursId
    ): void
    {
        $ficheMatierePorteuse = null;
        $ficheMatiereSource = $structEc->elementConstitutif->getFicheMatiere();
        $ficheMatiereFromParcours = $ficheMatiereSource?->getParcours()?->getId() === $parcoursId;
        $hasParentMcccIdentique = $structEc->elementConstitutif->getEcParent()?->isMcccEnfantsIdentique();
        $ecFromParcours = $structEc->elementConstitutif->getParcours()?->getId() === $ficheMatiereSource?->getParcours()?->getId();

        $hasFicheMatiereEcPorteur = null;
        if($ficheMatiereSource){
            $hasFicheMatiereEcPorteur = array_filter(
                $ficheMatiereSource->getElementConstitutifs()->toArray(),
                fn($ecFM) => $ecFM->getParcours()?->getId() === $ficheMatiereSource->getParcours()?->getId()
                    && $ecFM->getParcours()?->getId() !== null && $ficheMatiereSource->getParcours()?->getId() !== null
            );
            $hasFicheMatiereEcPorteur = count($hasFicheMatiereEcPorteur) > 0;
        }

        // Si c'est l'EC porteur
        $isEcPorteur = false;
        if($ecFromParcours && $ficheMatiereFromParcours){
            $isEcPorteur = true;
            $ficheMatierePorteuse = $structEc->elementConstitutif->getFicheMatiere();
        }

        // Si la fiche matière n'a pas d'EC porteur
        // Les fiches hors diplôme devraient être traitées ici
        if($hasFicheMatiereEcPorteur === false){
            $ficheMatierePorteuse = $structEc->elementConstitutif->getFicheMatiere();
        }

        $isEcOnlyOne = false;
        // Si la fiche matière n'est liée qu'à un seul EC
        if($ecFromFiche = $structEc->elementConstitutif->getFicheMatiere()?->getElementConstitutifs()){
            if(count($ecFromFiche) === 1 && !$isEcPorteur){
                $ficheMatierePorteuse = $structEc->elementConstitutif->getFicheMatiere();
                $isEcOnlyOne = true;
            }
        }

        // Si les MCCC sont sur l'EC
        if(!$structEc->elementConstitutif->getFicheMatiere()?->isMcccImpose()){
           if($ficheMatierePorteuse){
                if(array_key_exists($ficheMatierePorteuse->getId(), $this->mcccCopyDataArray) === false){
                    $this->mcccCopyDataArray[$ficheMatierePorteuse->getId()] = [];
                    foreach($structEc->mcccs as $mccc){
                        if($isEcPorteur || $isEcOnlyOne || $hasFicheMatiereEcPorteur === false){
                            $mcccCopy = $this->mcccCopyRepo->find($mccc->getId());
                            $ficheMatiereCopy = $this->fmCopyRepo->find($ficheMatierePorteuse->getId());
                            // MCCC Sur la fiche matière
                            $mcccCopy->setFicheMatiere($ficheMatiereCopy);
                            $this->mcccCopyDataArray[$ficheMatiereCopy->getId()]['mccc'] = $mccc;
                            // Type MCCC et etat MCCC recopié sur la fiche matière
                            $ficheMatiereCopy->setTypeMccc($structEc->elementConstitutif->getTypeMccc());
                            $this->mcccCopyDataArray[$ficheMatiereCopy->getId()]['type_mccc'] = $structEc->elementConstitutif->getTypeMccc();

                            //todo; David - Ajouté manquant
                            $ficheMatiereCopy->setEtatMccc($structEc->elementConstitutif->getEtatMccc());
                            $this->mcccCopyDataArray[$ficheMatiereCopy->getId()]['etat_mccc'] = $structEc->elementConstitutif->getEtatMccc();
                            $ficheMatiereCopy->setQuitus($structEc->elementConstitutif->isQuitus());
                            $this->mcccCopyDataArray[$ficheMatiereCopy->getId()]['quitus'] = $structEc->elementConstitutif->isQuitus();

                            $this->entityManagerCopy->persist($ficheMatiereCopy);
                            $this->entityManagerCopy->persist($mcccCopy);
                        }
                    }
                }
           }
        }
    }

    /**
     * Les données sont récupérées depuis la copie
     * Il n'y a plus besoin de sélectionner l'élément en BD
     */
    public function placeMcccSpecifiquesFlag(StructureEc $structEc): void
    {
        $mcccResult = null;
        $typeMcccAreEqual = null;
        if(!$structEc->elementConstitutif->getEcParent()?->isMcccEnfantsIdentique()){
            $mcccResult = $this->mcccCopyRepo->findBy(
                ['ec' => $structEc->elementConstitutif->getId()]
            );
        }

        if($structEc->elementConstitutif->getFicheMatiere()
           && !$structEc->elementConstitutif->getEcParent()?->isMcccEnfantsIdentique()
        ){
            $mcccResult = $this->mcccCopyRepo->findBy(
                ['ficheMatiere' => $structEc->elementConstitutif->getFicheMatiere()->getId()]
            );
            $ficheMatiereCopy = $this->fmCopyRepo->find($structEc->elementConstitutif->getFicheMatiere()->getId());
            $typeMcccAreEqual = $structEc->typeMccc === $ficheMatiereCopy?->getTypeMccc();
        }
        if(is_array($mcccResult)){
            $mcccAreEqual = $this->compareTwoMcccArray($structEc->mcccs, $mcccResult);
            if(($mcccAreEqual === false || $typeMcccAreEqual === false) && !$structEc->elementConstitutif->getFicheMatiere()?->isMcccImpose()){
                $ecCopyMccc = $this->ecCopyRepo->find($structEc->elementConstitutif->getId());
                $ecCopyMccc->setMcccSpecifiques(true);
                $this->entityManagerCopy->persist($ecCopyMccc);
            }
        }
    }

    public function getDTOForParcours(
        Parcours $parcours,
        bool $dataFromFicheMatiere = false,
        bool $withCopy = false,
        bool $fromCopy = false
    ): StructureParcours {
        if ($withCopy) {
            $this->copyDataForParcours($parcours);
        }
        $handler = $this->typeDiplomeResolver->fromParcours($parcours);
        return $handler->calcul($parcours, new OptionsCalculStructure(dataFromFicheMatiere: $dataFromFicheMatiere));
    }

    private function getUe(Ue $ue): ?Ue
    {
        return $ue->getUeRaccrochee() !== null
            ? $ue->getUeRaccrochee()->getUe()
            : $ue;
    }

    private function getSemestre(?Semestre $semestre): ?Semestre
    {
        if($semestre){
            return $semestre->getSemestreRaccroche() !== null
                ? $semestre->getSemestreRaccroche()->getSemestre()
                : $semestre;
        }
        return null;
    }

    public function exportDTOAsPdf(
        Parcours $parcours,
        bool $heuresSurFicheMatiere,
        bool $withCopy = false,
        bool $fromCopy = false,
    ): \Symfony\Component\HttpFoundation\Response
    {
        return $this->myPdf->render(
            'typeDiplome/formation/_structure.html.twig',
            [
                'print' => true,
                'dto' => $this->getDTOForParcours($parcours, $heuresSurFicheMatiere, $withCopy, $fromCopy),
                'parcours' => $parcours,
                'titre' => "Maquette-Parcours-{$parcours->getDisplay()}"
            ]
        );
    }

    public function compareTwoDTO(
        StructureParcours $dto1,
        StructureParcours $dto2,
        string $typeVerif = 'hours'
    ): bool
    {
        if(in_array($typeVerif, ['hours', 'ects']) === false){
            throw new Exception("Le type de vérification doit être parmi ['hours', 'ects'].");
        }

        $result = true;
        // Même nombre de semestres
        $nbSemestre = count($dto1->semestres) === count($dto2->semestres);
        if($nbSemestre === false){
            self::$errorMessageArray[] = "Nombre de semestres différents.";
            $result = false;
        }
        foreach($dto1->semestres as $indexSemestre => $semestre){
            $totalHeureSemestre = $this->compareSemestresHeures($dto1->semestres[$indexSemestre], $dto2->semestres[$indexSemestre]);
            if($totalHeureSemestre === false && $typeVerif === 'hours'){
                $result = false;
            }
            if($typeVerif === 'ects'){
                if( $dto1->semestres[$indexSemestre]->heuresEctsSemestre->sommeSemestreEcts
                    !== $dto2->semestres[$indexSemestre]->heuresEctsSemestre->sommeSemestreEcts
                ){
                    $result = false;
                    self::$errorMessageArray[] = "Semestre {$dto1->semestres[$indexSemestre]->ordre} - Somme des ECTS différente";
                }
            }
            // Même nombre d'UE
            $nbUe = count($dto1->semestres[$indexSemestre]->ues) === count($dto2->semestres[$indexSemestre]->ues);
            if($nbUe === false){
                self::$errorMessageArray[] = "S{$indexSemestre} : nombre d'UE différent";
                $result = false;
            }
            foreach($dto1->semestres[$indexSemestre]->ues as $indexUe => $ue){
                if($typeVerif === 'hours'){
                    // Comparaison des heures des UE
                    $comparaisonUe = $this->compareTwoUeDTO(
                        $dto1->semestres[$indexSemestre]->ues[$indexUe],
                        $dto2->semestres[$indexSemestre]->ues[$indexUe]
                    );
                    if($comparaisonUe === false){
                        $result = false;
                    }
                }
                if($typeVerif === 'ects'){
                    // Comparaison des ECTS des UE
                    $comparaisonEctsUe = $this->compareTwoUeDTO(
                        $dto1->semestres[$indexSemestre]->ues[$indexUe],
                        $dto2->semestres[$indexSemestre]->ues[$indexUe],
                        typeVerif: 'ects',
                    );
                    if($comparaisonEctsUe === false){
                        $result = false;
                    }
                }
                // Si des UE enfants
                if(count($dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants) > 0){
                    // Même nombre d'UE enfants
                    $nbUeEnfant = count($dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants())
                        === count($dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants());
                    if($nbUeEnfant === false){
                        $result = false;
                        self::$errorMessageArray[] = "S{$indexSemestre} - {$ue->display} : nombre d'enfant différent";
                    }

                    foreach($dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants() as $indexUeE => $ueE){
                        if($typeVerif === 'hours'){
                            // Comparaison des heures des UE Enfant
                            $comparaisonUeEnfant = $this->compareTwoUeDTO(
                                    $dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE],
                                    $dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]
                                );
                            if($comparaisonUeEnfant === false){
                                $result = false;
                            }
                        }
                        if($typeVerif === 'ects'){
                            // Comparaison des ECTS de UE enfant
                            $comparaisonEctsUeEnfant = $this->compareTwoUeDTO(
                                $dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE],
                                $dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE],
                                typeVerif: 'ects'
                            );
                            if($comparaisonEctsUeEnfant === false){
                                $result = false;
                            }
                        }
                        // Même nombre d'UE Enfant
                        if(count($dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants()) > 0){
                            $nbUeEnfantDeuxieme = count($dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants())
                                === count($dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants());
                            if($nbUeEnfantDeuxieme === false){
                                $result = false;
                                self::$errorMessageArray[] = "S{$indexSemestre} - {$ue->display} : nombre d'enfant différent";
                            }
                            // Comparaison des heures des UE enfant d'UE enfant
                            foreach($dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants() as $indexUeEDeuxieme => $ueEDeuxieme){
                                if($typeVerif === 'hours'){
                                    $comparaisonUeEnfantDeuxieme = $this->compareTwoUeDTO(
                                        $dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants()[$indexUeEDeuxieme],
                                        $dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants()[$indexUeEDeuxieme]
                                    );
                                    if($comparaisonUeEnfantDeuxieme === false){
                                        $result = false;
                                    }
                                }
                                if($typeVerif === 'ects'){
                                    // Comparaison des ECTS d'UE enfant d'UE enfant (deuxième niveau)
                                    $comparaisonEctsUeEnfantDeuxieme = $this->compareTwoUeDTO(
                                        $dto1->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants()[$indexUeEDeuxieme],
                                        $dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeE]->uesEnfants()[$indexUeEDeuxieme],
                                        typeVerif: 'ects'
                                    );
                                    if($comparaisonEctsUeEnfantDeuxieme === false){
                                        $result = false;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function compareTwoDtoForMCCC(StructureParcours $dto1, StructureParcours $dto2): bool
    {
        $result = true;
        foreach($dto1->semestres as $indexSemestre => $semestre){
            foreach($semestre->ues as $indexUe => $ue){
                $testUe = $this->compareTwoUeDtoForMccc(
                    $ue, $dto2->semestres[$indexSemestre]->ues[$indexUe], $dto1->parcours->getId()
                );
                $result = $result && $testUe;
                foreach($ue->uesEnfants() as $indexUeEnfant => $ueEnfant){
                    $testUeEnfant = $this->compareTwoUeDtoForMccc(
                        $ueEnfant,
                        $dto2->semestres[$indexSemestre]->ues[$indexUe]->uesEnfants()[$indexUeEnfant],
                        $dto1->parcours->getId()
                    );
                    $result = $result && $testUeEnfant;
                    foreach($ueEnfant->uesEnfants() as $indexUeEnfantDeuxieme => $ueEnfantDeuxieme){
                        $testUeEnfantDeuxieme = $this->compareTwoUeDtoForMccc(
                            $ueEnfantDeuxieme,
                            $dto2->semestres[$indexSemestre]->ues[$indexUe]
                                ->uesEnfants()[$indexUeEnfant]
                                ->uesEnfants()[$indexUeEnfantDeuxieme],
                            $dto1->parcours->getId()
                        );
                        $result = $result && $testUeEnfantDeuxieme;
                    }
                }
            }
        }

        return $result;
    }

    public function compareTwoUeDtoForMccc(StructureUe $ue1, StructureUe $ue2, int $parcoursId): bool
    {
        $result = true;
        if(count($ue1->elementConstitutifs) !== count($ue2->elementConstitutifs)){
            $result = false;
        }
        if($result){
            foreach($ue1->elementConstitutifs as $indexEc => $ec){
                $mcccTest = $this->compareTwoMcccArray(
                    $ec->mcccs,
                    $ue2->elementConstitutifs[$indexEc]->mcccs ?? [],
                    $parcoursId,
                    $ue1->display . " " . $ec->elementConstitutif->getCode()
                );
                $typeMcccTest = $ec->typeMccc === $ue2->elementConstitutifs[$indexEc]?->typeMccc;
                if(!$typeMcccTest){
                    self::$errorMcccMessageArray[$parcoursId][] = "{$ue1->display} {$ec->elementConstitutif->getCode()} - Type MCCC différent";
                }
                $result = $result && $mcccTest && $typeMcccTest;
                foreach($ec->elementsConstitutifsEnfants as $indexEcEnfant => $ecEnfant){
                    $mcccEnfantTest = $this->compareTwoMcccArray(
                        $ecEnfant->mcccs,
                        $ue2->elementConstitutifs[$indexEc]?->elementsConstitutifsEnfants[$indexEcEnfant]->mcccs ?? [],
                        $parcoursId,
                        $ue1->display . " " . $ecEnfant->elementConstitutif->getCode()
                    );
                    $typeMcccEnfantTest = $ecEnfant->typeMccc === $ue2->elementConstitutifs[$indexEc]?->elementsConstitutifsEnfants[$indexEcEnfant]?->typeMccc;
                    if(!$typeMcccEnfantTest){
                        self::$errorMcccMessageArray[$parcoursId][] = "{$ue1->display} {$ecEnfant->elementConstitutif->getCode()} - Type MCCC différent";
                    }
                    $result = $result && $mcccEnfantTest && $typeMcccEnfantTest;
                }
            }
        }

        return $result;
    }

    public function compareSemestresHeures(
        StructureSemestre $semestre1,
        StructureSemestre $semestre2
    ) : bool {

        /**
         * Même nombre d'heures total sur le semestre
         */
        $result = $semestre1->heuresEctsSemestre->sommeSemestreTotalPres()
            === $semestre2->heuresEctsSemestre->sommeSemestreTotalPres();

        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTotalDist()
            === $semestre2->heuresEctsSemestre->sommeSemestreTotalDist();

        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTotalPresDist()
            === $semestre2->heuresEctsSemestre->sommeSemestreTotalPresDist();
        /**
         * Présentiel
         */
        // CM
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreCmPres
            === $semestre2->heuresEctsSemestre->sommeSemestreCmPres;
        // TD
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTdPres
            === $semestre2->heuresEctsSemestre->sommeSemestreTdPres;
        // TP
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTpPres
            === $semestre2->heuresEctsSemestre->sommeSemestreTpPres;
        /**
         * Distanciel
         */
        // CM
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreCmDist
            === $semestre2->heuresEctsSemestre->sommeSemestreCmDist;
        // TD
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTdDist
            === $semestre2->heuresEctsSemestre->sommeSemestreTdDist;
        //TP
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTpDist
            === $semestre2->heuresEctsSemestre->sommeSemestreTpDist;

        // TE
        $result = $result && $semestre1->heuresEctsSemestre->sommeSemestreTePres
            === $semestre2->heuresEctsSemestre->sommeSemestreTePres;

        if($result === false){
            self::$errorMessageArray[] = "Semestre {$semestre1->semestre->getOrdre()} - le total d'heure du semestre est différent";
        }

        return $result;
    }

    public function compareUeHeures(
        StructureUe $ue1,
        StructureUe $ue2
    ) : bool {

        // Totaux
        $result = $ue1->heuresEctsUe->sommeUeTotalPres()
            === $ue2->heuresEctsUe->sommeUeTotalPres();

        $result = $result && $ue1->heuresEctsUe->sommeUeTotalDist()
            === $ue2->heuresEctsUe->sommeUeTotalDist();

        $result = $result && $ue1->heuresEctsUe->sommeUeTotalPresDist()
            === $ue2->heuresEctsUe->sommeUeTotalPresDist();
        /**
         * Présentiel
         */
        // CM
        $result = $result && $ue1->heuresEctsUe->sommeUeCmPres
            === $ue2->heuresEctsUe->sommeUeCmPres;
        // TD
        $result = $result && $ue1->heuresEctsUe->sommeUeTdPres
            === $ue2->heuresEctsUe->sommeUeTdPres;
        // TP
        $result = $result && $ue1->heuresEctsUe->sommeUeTpPres
            === $ue2->heuresEctsUe->sommeUeTpPres;
        /**
         * Distanciel
         */
        // CM
        $result = $result && $ue1->heuresEctsUe->sommeUeCmDist
            === $ue2->heuresEctsUe->sommeUeCmDist;
        // TD
        $result = $result && $ue1->heuresEctsUe->sommeUeTdDist
            === $ue2->heuresEctsUe->sommeUeTdDist;
        // TP
        $result = $result && $ue1->heuresEctsUe->sommeUeTpDist
            === $ue2->heuresEctsUe->sommeUeTpDist;

        // TE
        $result = $result && $ue1->heuresEctsUe->sommeUeTePres
            === $ue2->heuresEctsUe->sommeUeTePres;

        if($result === false){
            self::$errorMessageArray[] = "{$ue1->display} - le total d'heure de l'UE est différent";
        }

        return $result;
    }

    public function compareEcHeures(
        StructureEc $ec1,
        StructureEc $ec2,
        string $ueDisplay = ""
    ) : bool {

        // Totaux
        $result = $ec1->heuresEctsEc->sommeEcTotalPres()
            === $ec2->heuresEctsEc->sommeEcTotalPres();

        $result = $result && $ec1->heuresEctsEc->sommeEcTotalDist()
            === $ec2->heuresEctsEc->sommeEcTotalDist();

        $result = $result && $ec1->heuresEctsEc->sommeEcTotalPresDist()
            === $ec2->heuresEctsEc->sommeEcTotalPresDist();

        /**
         * Présentiel
         */
        // CM
        $result = $result && $ec1->heuresEctsEc->cmPres === $ec2->heuresEctsEc->cmPres;
        // TD
        $result = $result && $ec1->heuresEctsEc->tdPres === $ec2->heuresEctsEc->tdPres;
        // TP
        $result = $result && $ec1->heuresEctsEc->tpPres === $ec2->heuresEctsEc->tpPres;
        /**
         * Distanciel
         */
        // CM
        $result = $result && $ec1->heuresEctsEc->cmDist === $ec2->heuresEctsEc->cmDist;
        // TD
        $result = $result && $ec1->heuresEctsEc->tdDist === $ec2->heuresEctsEc->tdDist;
        // TP
        $result = $result && $ec1->heuresEctsEc->tpDist === $ec2->heuresEctsEc->tpDist;

        // TE
        $result = $result && $ec1->heuresEctsEc->tePres === $ec2->heuresEctsEc->tePres;

        if($result === false){
            self::$errorMessageArray[] = "Les deux EC ne correspondent pas. ({$ueDisplay} - {$ec1->elementConstitutif->getCode()})";
        }

        return $result;
    }

    public function compareTwoUeDTO(
        StructureUe $ue1,
        StructureUe $ue2,
        string $typeVerif = "hours"
    ) : bool {

        if(in_array($typeVerif, ['hours', 'ects']) === false){
            throw new Exception("Le type de vérification doit être parmi ['hours', 'ects'].");
        }

        $result = true;

        // Même ECTS sur les UE
        if($ue1->heuresEctsUe->sommeUeEcts !== $ue2->heuresEctsUe->sommeUeEcts && $typeVerif === 'ects'){
            self::$errorMessageArray[] = "{$ue1->display} : ECTS différent";
            $result = false;
        }
        // Même heures sur les UE
        $totalHeureUe = $this->compareUeHeures($ue1, $ue2);
        // Même nombre d'EC
        $nbEc = count($ue1->elementConstitutifs) === count($ue2->elementConstitutifs);
        if($totalHeureUe === false && $typeVerif === 'hours'){
            $result = false;
        }
        if($nbEc === false){
            self::$errorMessageArray[] = "{$ue1->display} : Nombre d'EC différent";
            $result = false;
        }
        foreach($ue1->elementConstitutifs as $indexEc => $valueEc){
            if($typeVerif === 'hours'){
                // Comparaison d'heures des EC
                $comparaisonHeureEc = $this->compareEcHeures(
                    $ue1->elementConstitutifs[$indexEc],
                    $ue2->elementConstitutifs[$indexEc],
                    $ue1->display
                );
                if($comparaisonHeureEc === false){
                    $result = false;
                }
            }
            if($typeVerif === 'ects'){
                $comparaisonEctsEc = $this->compareEctsEC(
                    $ue1->elementConstitutifs[$indexEc],
                    $ue2->elementConstitutifs[$indexEc],
                    $ue1->display
                );
                if($comparaisonEctsEc === false){
                    $result = false;
                }
            }
            if(count($ue1->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants) > 0){
                // Même nombre d'EC enfants
                $nbEcEnfant = count($ue1->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants)
                    === count($ue2->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants);
                if($nbEcEnfant === false){
                    self::$errorMessageArray[] = "{$ue1->display} - nombre d'EC enfants différent";
                    $result = false;
                }
                // Les EC enfants ont leur ID de BD comme clé
                foreach($ue1->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants as $indexEcE => $valueEcE){
                    if($typeVerif === 'hours'){
                        // Comparaison d'heures des EC enfants
                        $comparaisonHeureEcEnfant = $this->compareEcHeures(
                            $ue1->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants[$indexEcE],
                            $ue2->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants[$indexEcE],
                            $ue1->display
                        );
                        if($comparaisonHeureEcEnfant === false){
                            $result = false;
                        }
                    }
                    if($typeVerif === 'ects'){
                        $comparaisonEctsEcEnfant = $this->compareEctsEC(
                            $ue1->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants[$indexEcE],
                            $ue2->elementConstitutifs[$indexEc]->elementsConstitutifsEnfants[$indexEcE],
                            $ue1->display
                        );
                        if($comparaisonEctsEcEnfant === false){
                            $result = false;
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function hasEcSameHeuresAsFicheMatiereCopy(
        HeuresEctsEc $heures,
        FicheMatiere $ficheMatiere
    ): ?bool
    {
        if(array_key_exists($ficheMatiere->getId(), $this->ficheMatiereCopyDataArray)){
            $ficheMatiereCopy = $this->ficheMatiereCopyDataArray[$ficheMatiere->getId()];

            $fmCmPres = $ficheMatiereCopy["cmPres"] ?? 0.0;
            $fmCmDist = $ficheMatiereCopy["cmDist"] ?? 0.0;
            $fmTdPres = $ficheMatiereCopy["tdPres"] ?? 0.0;
            $fmTdDist = $ficheMatiereCopy["tdDist"] ?? 0.0;
            $fmTpPres = $ficheMatiereCopy["tpPres"] ?? 0.0;
            $fmTpDist = $ficheMatiereCopy["tpDist"] ?? 0.0;
            $fmTe = $ficheMatiereCopy["te"] ?? 0.0;

            $ecTe = $heures->tePres;

            return $fmCmPres === $heures->cmPres
            && $fmCmDist === $heures->cmDist
            && $fmTdPres === $heures->tdPres
            && $fmTdDist === $heures->tdDist
            && $fmTpPres === $heures->tpPres
            && $fmTpDist === $heures->tpDist
            && $fmTe === $ecTe;
        }

        return null;

    }

    public function hasHeuresFicheMatiereCopy(FicheMatiere $ficheMatiere) : bool {
        $haystack = [0, null];

        if(array_key_exists($ficheMatiere->getId(), $this->ficheMatiereCopyDataArray)){
            // $ficheMatiereCopy = $this->ficheMatiereCopyDataArray[$ficheMatiere->getId()];
            // return in_array($ficheMatiereCopy["cmPres"], $haystack) === false
            //     || in_array($ficheMatiereCopy["tdPres"], $haystack) === false
            //     || in_array($ficheMatiereCopy["tpPres"], $haystack) === false
            //     || in_array($ficheMatiereCopy["cmDist"], $haystack) === false
            //     || in_array($ficheMatiereCopy["tdDist"], $haystack) === false
            //     || in_array($ficheMatiereCopy["tpDist"], $haystack) === false
            //     || in_array($ficheMatiereCopy["te"], $haystack) === false;

            // La FM est dans le tableau, donc elle a été traitée
            return true;
        }

        return false;
    }

    public function hasEcHeures(ElementConstitutif $ec): bool
    {
        if($ec->isSansHeure()){
            return false;
        }

        $haystack = [0, null];
        return in_array($ec->getVolumeCmPresentiel(), $haystack) === false
        || in_array($ec->getVolumeCmDistanciel(), $haystack) === false
        || in_array($ec->getVolumeTdPresentiel(), $haystack) === false
        || in_array($ec->getVolumeTdDistanciel(), $haystack) === false
        || in_array($ec->getVolumeTpPresentiel(), $haystack) === false
        || in_array($ec->getVolumeTpDistanciel(), $haystack) === false
        || in_array($ec->getVolumeTe(), $haystack) === false;
    }

    public function compareTwoMCCC(
        ?Mccc $mccc1,
        ?Mccc $mccc2,
        int $parcoursId = -1,
        string $debugText = "",
        bool $withDebug = true
    ) : bool{
        $retour = true;
        $variableError = [];

        if( ($mccc1 === null && $mccc2 !== null) || ($mccc1 !== null && $mccc2 === null) ){
            $retour = false;
            $variableError[] = "MCCC 'null' alors que son équivalent à une valeur";
        }

        if($mccc1 !== null && $mccc2 !== null){
            if($mccc1->getLibelle() !== $mccc2->getLibelle()){
                $retour = false;
                $variableError[] = "Libellé - {$mccc1->getLibelle()} | {$mccc2->getLibelle()}";
            }
            if($mccc1->getNumeroSession() !== $mccc2->getNumeroSession()){
                $retour = false;
                $variableError[] = "Numéro de sesssion";
            }
            if($mccc1->isSecondeChance() !== $mccc2->isSecondeChance()){
                $retour = false;
                $variableError[] = "Seconde chance";
            }
            if($mccc1->getPourcentage() !== $mccc2->getPourcentage()){
                $retour = false;
                $variableError[] = "Pourcentage";
            }
            if($mccc1->getNbEpreuves() !== $mccc2->getNbEpreuves()){
                $retour = false;
                $variableError[] = "Nb. épreuves";
            }
            // Comparaison du tableau de type épreuve
            if($this->twoArrayAreIdentical($mccc1->getTypeEpreuve(), $mccc2->getTypeEpreuve()) === false){
                $retour = false;
                $variableError[] = "Type épreuve";
            }
            if($mccc1->isControleContinu() !== $mccc2->isControleContinu()){
                $retour = false;
                $variableError[] = "Contrôle continu";
            }
            if($mccc1->isExamenTerminal() !== $mccc2->isExamenTerminal()){
                $retour = false;
                $variableError[] = "Examen terminal";
            }
            // Comparaison de date
            $dateFormat = "d-m-Y H:i:s";
            if($mccc1->getDuree()?->format($dateFormat) !== $mccc2->getDuree()?->format($dateFormat)){
                $retour = false;
                $variableError[] = "Durée";
            }
            if($mccc1->getNumeroEpreuve() !== $mccc2->getNumeroEpreuve()){
                $retour = false;
                $variableError[] = "Numéro épreuve";
            }
            // Si une option est nulle mais que son équivalent ne l'est pas
            if(
                ($mccc1->getOptions() !== null && $mccc2->getOptions() === null )
                ||
                ($mccc1->getOptions() === null && $mccc2->getOptions() !== null)
            ){
                $retour = false;
                $variableError[] = "Option à 'null' alors que son équivalent ne l'est pas";
            }
            // Comparaison du tableau d'options
            if($mccc1->getOptions() && $mccc2->getOptions()){
                if($this->twoArrayAreIdentical($mccc1->getOptions(), $mccc2->getOptions()) === false){
                    $retour = false;
                    $variableError[] = "Options";
                }
            }
        }
        if($retour === false){
            if($withDebug){
                $dataError = $debugText . " : Les deux MCCC ({$mccc1->getId()}) ne correspondent pas : [" . implode(", ", $variableError) . "]";
                if(array_key_exists($parcoursId, self::$errorMcccMessageArray) === false){
                    self::$errorMcccMessageArray[$parcoursId] = [];
                }
                self::$errorMcccMessageArray[$parcoursId][] = $dataError;
            }
        }

        return $retour;
    }

    private function compareTwoMcccArray(
        array $array1,
        array $array2,
        int $parcoursId = -1,
        string $debugText = "",
    ) : bool {
        $return = true;
        // Si on a le même nombre de MCCC avant et après
        if(count($array1) !== count($array2)){
            $return = false;
            if(array_key_exists($parcoursId, self::$errorMcccMessageArray) === false){
                self::$errorMcccMessageArray[$parcoursId] = [];
            }
            self::$errorMcccMessageArray[$parcoursId][] = $debugText .  " : Il n'y a pas le même nombre de MCCC";

        }
        if($return){
            $alreadyUsedIndex = [];
            // Pour chaque MCCC, on regarde s'il a un équivalent dans le résultat, et on le compare
            foreach($array1 as $index1 => $mccc1){
                foreach($array2 as $index2 => $mccc2){
                    // Si une valeur équivalente n'a pas encore été utilisée
                    if(array_key_exists($index2, $alreadyUsedIndex) === false){
                        // On teste le MCCC
                        $testEqual = $this->compareTwoMCCC($mccc1, $mccc2, $parcoursId, $debugText, false);
                        if($testEqual === true){
                            // Si le test est positif, on marque l'équivalent
                            // pour qu'il ne soit pas à nouveau utilisé
                            $alreadyUsedIndex[$index2] = true;
                        }
                    }
                }
            }

            $differentIndex = array_diff_key($array2, $alreadyUsedIndex);
            if(count($differentIndex) > 0){
                if(array_key_exists($parcoursId, self::$errorMcccMessageArray) === false){
                    self::$errorMcccMessageArray[$parcoursId] = [];
                }
                foreach($differentIndex as $diffIndex => $diffValue){
                    $differentMccc = $array2[$diffIndex];
                    self::$errorMcccMessageArray[$parcoursId][] = $debugText
                    . " - EC ID ({$differentMccc->getEc()?->getId()}) - Le MCCC est différent - ID : ({$differentMccc->getId()})";
                }
            }


            $return = count($alreadyUsedIndex) === count($array1);
        }

        return $return;
    }

    private function compareEctsEC(
        StructureEc $ec1,
        StructureEc $ec2,
        string $debugText = ""
    ): bool {
        $result = $ec1->heuresEctsEc->ects === $ec2->heuresEctsEc->ects;
        if($result === false){
            self::$errorMessageArray[] = $debugText . " EC {$ec1->elementConstitutif->getCode()} - "
                . "ECTS différents {$ec1->heuresEctsEc->ects} | {$ec2->heuresEctsEc->ects}";
        }
        return $result;
    }

    private function twoArrayAreIdentical(array $array1, array $array2): bool
    {
        $retour = true;
        if(count($array1) !== count($array2)){
            $retour = false;
        }
        foreach($array1 as $key => $value){
            if(array_key_exists($key, $array2)){
                if($value !== $array2[$key]){
                    $retour = false;
                }
            }
            else {
                $retour = false;
            }
        }
        return $retour;
    }

    private function hasEctsDifferent(StructureEc $ec) : bool {
        return $ec->heuresEctsEc->ects !== $this->ficheMatiereEctsCopyArray[$ec->elementConstitutif->getFicheMatiere()->getId()];
    }

    private function hasFicheMatiereEcts(FicheMatiere $fm): bool
    {
        return array_key_exists($fm->getId(), $this->ficheMatiereEctsCopyArray);
    }
}
