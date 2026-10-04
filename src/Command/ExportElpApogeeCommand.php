<?php

namespace App\Command;

use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\Ue;
use App\Enums\Apogee\CodeNatuElpEnum;
use App\Enums\Apogee\TypeHeureCE;
use App\Enums\TypeUeEcEnum;
use App\Repository\ElementConstitutifRepository;
use App\Repository\UeRepository;
use App\Service\Apogee\Classes\ElementPedagogiDTO6;
use App\Service\Apogee\Classes\ListeElementPedagogiDTO3;
use App\Service\Apogee\Classes\ParametrageAnnuelCeDTO2;
use App\Service\Apogee\Classes\TableauParametrageChargeEnseignementDTO2;
use App\Service\Apogee\Classes\TableauTypeHeureDTO;
use App\Service\Apogee\Classes\TypeHeureDTO;
use App\TypeDiplome\TypeDiplomeResolver;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use SoapClient;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:export-elp-apogee',
    description: "Exporte les éléments pédagogiques (ELP) dans l'application APOGEE",
)]
class ExportElpApogeeCommand extends Command
{

    // Données extraites d'APOTEST
    private static string $codElpApogeeDataTest = "COD_ELP_APOGEE-TEST-20-06-2024-10_13.json";
    private static string $codLseApogeeDataTest = "COD_LSE-LISTE_APOGEE-TEST-20-06-2024-10_14.json";
    // Données exportées depuis ORéOF
    private static string $fullLseExportDataTest = "OREOF-COD_LSE_TEST-16-04-2024_15-31-27.json";
    private static string $allParcoursCodElpExport = "OREOF-COD_ELP-ALL_PARCOURS-filtered-EXCLUDED-20-06-2024_11-25-51.json";
    // Fichier contenant les formations à exclure
    private static string $formationToExcludeFile = "INSERTION-INCLUSION-APOGEE-20062024-09_44 FR.txt";
    private static string $formationToExcludeJSON = "Formations-a-inclure-20-06-2024_10-46-58.json";
    // Vérifications entre deux fichiers JSON
    private static string $oldJsonFile = "COD_ELP_APOGEE-PRODUCTION-18-04-2024-10-42.json";
    private static string $newJsonFile = "COD_ELP_APOGEE-PRODUCTION-APRES-INSERTION-18-04-2024-11-45.json";

    private EntityManagerInterface $entityManager;
    private Filesystem $filesystem;
    private ParameterBagInterface $parameterBag;
    private ?SoapClient $soapClient;

    public static array $errorMessagesArray = [];
    private TypeDiplomeResolver $typeDiplomeResolver;

    public function __construct(
        EntityManagerInterface $entityManager,
        Filesystem             $filesystem,
        ParameterBagInterface  $parameterBag,
        TypeDiplomeResolver    $typeDiplomeResolver
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->filesystem = $filesystem;
        $this->parameterBag = $parameterBag;
        $this->soapClient = null;
        $this->typeDiplomeResolver = $typeDiplomeResolver;
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'mode',
            mode: InputOption::VALUE_OPTIONAL,
            description: "Mode d'exécution : 'test' ou 'production'",
            default: 'test'
        )->addOption(
            name: 'full-excel-export',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Génère un export Excel des ELP pour toutes les formations - Type : Semestre, UE, EC'
        )->addOption(
            name: 'dummy-insertion',
            mode: InputOption::VALUE_NONE,
            description: "Insère un ELP dans la base de données APOTEST"
        )->addOption(
            name: 'dummy-lse-insertion',
            mode: InputOption::VALUE_NONE,
            description: "Insère une LSE dans la base de données APOTEST"
        )->addOption(
            name: 'parcours-insertion',
            mode: InputOption::VALUE_REQUIRED,
            description: "Insère tous les ELP d'un parcours dans la base de données, via le Web Service"
        )
        ->addOption(
            name: 'full-parcours-insertion',
            mode: InputOption::VALUE_NONE,
            description: "Insère tous les ELP de tous les parcours disponibles en base de données"
        )->addOption(
            name: 'full-lse-insertion',
            mode: InputOption::VALUE_NONE,
            description: "Insère toutes les listes LSE de tous les parcours disponibles"
        )
        ->addOption(
            name: 'parcours-excel-export',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Genère une export de tous les ELP pour un parcours donné.'
        )->addOption(
            name: 'with-filter',
            mode: InputOption::VALUE_NONE,
            description: "Ajoute un filtre aux données traitées (parcours-excel-export)"
        )->addOption(
            name: 'with-json-export',
            mode: InputOption::VALUE_NONE,
            description: "Option si l'on souhaite un export JSON supplémentaire dans certains cas (lse-export --with-filter)"
        )
        ->addOption(
            name: 'check-lse-test-json',
            mode: InputOption::VALUE_NONE,
            description: "Vérifie un fichier d'export JSON pour les LSE de test (doublons)"
        )->addOption(
            name: 'check-duplicates',
            mode: InputOption::VALUE_NONE,
            description: "Vérifie s'il y a des doublons sur les codes Apogee depuis la base de données"
        )->addOption(
            name: 'full-verify-data',
            mode: InputOption::VALUE_NONE,
            description: "Instancie tous les ELP des parcours disponibles, et génère un compte-rendu selon les erreurs détectées."
        )->addOption(
            name: 'parcours-lse-excel-export',
            mode: InputOption::VALUE_REQUIRED,
            description: "Génère un export Excel pour les LSE d'un parcours"
        )->addOption(
            name: 'full-lse-excel-export',
            mode: InputOption::VALUE_NONE,
            description: "Génère l'export Excel pour les LSE de tous les parcours disponibles"
        )->addOption(
            name: 'check-duplicates-with-apogee',
            mode: InputOption::VALUE_NONE,
            description: "Vérifie si les codes apogee depuis OREOF ne sont pas déjà présents dans APOGEE"
        )->addOption(
            name: 'report-invalid-data',
            mode: InputOption::VALUE_NONE,
            description: "Génère un rapport listant les parcours dont certaines données sont manquantes"
        )->addOption(
            name: 'report-invalid-apogee-code',
            mode: InputOption::VALUE_NONE,
            description: "Génère un rapport listant les parcours dont les codes APOGEE sont invalides"
        )->addOption(
            name: 'check-duplicates-from-json-export',
            mode: InputOption::VALUE_NONE,
            description: "Vérifie si des doublons existent dans un export JSON des ELP"
        )->addOption(
            name: 'check-nested-children',
            mode: InputOption::VALUE_NONE,
            description: "Vérifie s'il n'y a pas trop d'éléments enfants imbriqués dans les parcours disponibles"
        )->addOption(
            name: 'format-formation-to-exclude',
            mode: InputOption::VALUE_NONE,
            description: "Formate un fichier contenant les formations à exclure vers du JSON"
        )->addOption(
            name: 'dump-parcours-to-insert',
            mode: InputOption::VALUE_REQUIRED,
            description: "Dump les parcours disponibles pour l'insertion"
        )->addOption(
            name: 'with-exclusion',
            mode: InputOption::VALUE_NONE,
            description: "Exclut certaines données"
        )->addOption(
            name: 'check-diff',
            mode: InputOption::VALUE_NONE,
            description: "Vérifie les différences entre deux fichier JSON"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        ini_set('memory_limit', '2048M');

        $io = new SymfonyStyle($input, $output);
        $mode = $input->getOption('mode');
        // Options pour certaines commandes
        $withFilter = $input->getOption('with-filter');
        $withJsonExport = $input->getOption('with-json-export');
        $withExclusionOption = $input->getOption('with-exclusion');
        $checkDifferences = $input->getOption('check-diff');
        // Insertion via le Web Service
        $dummyInsertion = $input->getOption('dummy-insertion');
        $dummyLseInsertion = $input->getOption('dummy-lse-insertion');
        $parcoursInsertion = $input->getOption('parcours-insertion');
        $fullParcoursInsertion = $input->getOption('full-parcours-insertion');
        $fullLseInsertion = $input->getOption('full-lse-insertion');
        // Export Excel
        $fullExport = $input->getOption('full-excel-export');
        $parcoursExport = $input->getOption('parcours-excel-export');
        $parcoursLseExport = $input->getOption('parcours-lse-excel-export');
        $fullLseExport = $input->getOption('full-lse-excel-export');
        // Tests des données
        $checkDuplicates = $input->getOption('check-duplicates');
        $fullVerifyData = $input->getOption('full-verify-data');
        $checkDuplicatesWithApogee = $input->getOption('check-duplicates-with-apogee');
        $reportInvalidData = $input->getOption('report-invalid-data');
        $checkLseTestJsonExport = $input->getOption('check-lse-test-json');
        $reportInvalidApogeeCode = $input->getOption('report-invalid-apogee-code');
        $checkDuplicatesFromJsonExport = $input->getOption('check-duplicates-from-json-export');
        $checkNestedChildren = $input->getOption('check-nested-children');
        $dumpParcoursToInsert = $input->getOption('dump-parcours-to-insert');
        // Formatage des données
        $formatFormationFile = $input->getOption('format-formation-to-exclude');

        if($mode === "test"){
            // Export total des ELP selon le type : EC, UE ou Semestre
            if($fullExport){
                switch(strtoupper($fullExport)){
                    case "EC":
                        $io->writeln("Génération de l'export Excel - EC...");
                        $this->saveFullExportAsSpreadsheet($output, "EC");
                        break;
                    case "UE":
                        $io->writeln("Génération de l'export Excel - UE...");
                        $this->saveFullExportAsSpreadsheet($output, "UE");
                        break;
                    case "SEMESTRE":
                        $io->writeln("Génération de l'export Excel - Semestres...");
                        $this->saveFullExportAsSpreadsheet($output, "SEMESTRE");
                        break;
                    case "PARCOURS":
                        $io->writeln("Génération de l'export Excel - Parcours...");
                        $this->saveFullExportAsSpreadsheet($output, "PARCOURS", $withFilter, $withJsonExport, $withExclusionOption);
                        break;
                    default:
                        $io->warning("Type d'export inconnu. Il devrait être parmi la liste : ['PARCOURS', 'SEMESTRE', 'UE', 'EC']");
                        return Command::INVALID;
                }
                $io->success("Fichier généré avec succès.");

                return Command::SUCCESS;
            }
            // Export Excel d'un parcours suivant l'ID
            if($parcoursExport){
                $parcours = $this->entityManager->getRepository(Parcours::class)->findOneById($parcoursExport);
                if($parcours){
                    $filterTxt = "";
                    $io->writeln('Parcours trouvé : ' . $parcours->getDisplay() . ' - Formation : ' . $parcours->getFormation()->getDisplayLong());
                    $io->writeln("Génération de l'export Excel...");
                    $soapObjectArray = $this->generateSoapObjectsForParcours($parcours);
                    if($withFilter){
                        $soapObjectArray = $this->filterInvalidElpArray($soapObjectArray);
                        $soapObjectArray = $this->filterAlreadyInsertedElpArray($soapObjectArray);
                        $filterTxt = "filtered-";
                    }
                    $this->generateSpreadsheet($soapObjectArray, "Parcours-{$filterTxt}{$parcours->getId()}");
                    $io->success("Parcours enregistré avec succès.");
                    return Command::SUCCESS;
                }else {
                    $io->warning("Aucun parcours trouvé. L'identifiant est incorrect. ({$parcoursExport})");
                    return Command::INVALID;
                }
            }
            // Insertion d'un parcours suivant l'ID
            if($parcoursInsertion){
                $io->write("Utilisation du Web Service APOTEST");
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer un parcours dans la base de données ?")){
                    $parcours = $this->entityManager->getRepository(Parcours::class)->findOneById($parcoursInsertion);
                    if($parcours){
                        $io->writeln('Parcours trouvé : ' . $parcours->getDisplay() . ' - Formation : ' . $parcours->getFormation()->getDisplayLong());
                        $soapObjectArray = $this->generateSoapObjectsForParcours($parcours);
                        $countElement = count($soapObjectArray);
                        if($this->verifyUserIntent($io, "Le parcours comprend {$countElement} ELP. Voulez-vous continuer ?")){
                            try{
                                $io->writeln("Appel au Web Service en cours...");
                                $this->createSoapClient();
                                $this->insertSeveralElp($soapObjectArray);
                                $io->success("Commande exécutée avec succès");
                                return Command::SUCCESS;
                            } catch (Exception $e) {
                                $io->writeln("Une erreur est survenue durant l'insertion.");
                                $io->writeln("Message : " . $e->getMessage());
                                return Command::FAILURE;
                            }
                        }else {
                            $io->warning("L'insertion des ELP a été annulée.");
                            return Command::SUCCESS;
                        }
                    }else {
                        $io->warning("Aucun parcours trouvé pour cet identifiant. ({$parcoursInsertion})");
                        return Command::INVALID;
                    }
                }
                else {
                    $io->warning("La commande d'insertion a été annulée.");
                    return Command::SUCCESS;
                }
            }
            // Insère tous les ELP de tous les parcours disponibles
            if($fullParcoursInsertion){
                $io->writeln("Utilisation du Web Service APOTEST");
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer les ELP de TOUS LES PARCOURS ?")){
                    $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                    $nbParcours = count($parcoursArray);
                    $nbElpInsere = 0;
                    if($this->verifyUserIntent($io, "Il y a {$nbParcours} parcours disponibles. Continuer ?")){
                        try{
                            $io->writeln('Initialisation du Web Service...');
                            $this->createSoapClient();
                            $io->writeln('Insertion des données en cours...');
                            $io->progressStart($nbParcours);
                            foreach($parcoursArray as $parcours){
                                $soapObjectArray = $this->generateSoapObjectsForParcours($parcours);
                                $soapObjectArray = $this->filterInvalidElpArray($soapObjectArray);
                                $soapObjectArray = $this->filterAlreadyInsertedElpArray($soapObjectArray);
                                $this->insertSeveralElp($soapObjectArray);
                                $nbElpInsere += count($soapObjectArray);
                                $io->progressAdvance();
                            }
                            $io->writeln("\nInsertion réussie !");
                            $io->writeln("{$nbElpInsere} ELP Insérés !");
                            return Command::SUCCESS;
                        } catch (Exception $e) {
                            $io->writeln("\nUne erreur est survenue durant l'insertion.");
                            $io->writeln("Message : " . $e->getMessage());
                            return Command::FAILURE;
                        }
                    }
                    else {
                        $io->warning("La commande d'insertion a été annulée.");
                        return Command::SUCCESS;
                    }
                }
                else {
                    $io->warning("La commande d'insertion a été annulée.");
                    return Command::SUCCESS;
                }
            }
            // Instancie les ELP et génère un rapport des erreurs détectées
            if($fullVerifyData){
                // date et heure pour le fichier
                $now = new DateTime();
                $date = $now->format('d-m-Y_H-i-s');
                $io->writeln("Vérification des donnéees pour les parcours disponibles...");
                $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                $io->progressStart(count($parcoursArray));
                foreach($parcoursArray as $parcours){
                    self::$errorMessagesArray[$parcours->getId()] = [];
                    $soapObjects = $this->generateSoapObjectsForParcours($parcours, true);
                    $io->progressAdvance();
                }
                $file = __DIR__ . "/../Service/Apogee/export/Parcours-invalides-{$date}.txt";
                $this->filesystem->appendToFile($file, "Compte Rendu des parcours invalides pour l'export APOGEE\n");
                $io->writeln("");
                $io->writeln("Traitement des erreurs détectées...");
                $hasError = false;
                foreach(self::$errorMessagesArray as $id => $errorArray){
                    if(count($errorArray) > 0){
                        $hasError = true;
                        $parcours = $this->entityManager->getRepository(Parcours::class)->findOneById($id);
                        $this->filesystem->appendToFile($file, "\nParcours {$id} - {$parcours->getDisplay()} :\n");
                        foreach($errorArray as $error){
                            $this->filesystem->appendToFile($file, "{$error}\n");
                        }
                    }
                }
                $io->writeln("Rapport d'erreurs généré avec succès.");
                $hasError ? $io->warning("Un ou plusieurs parcours est incorrect.") : $io->success("Aucun problème détecté.");
                return Command::SUCCESS;
            }
            // Rapport listant les parcours où il manque des données
            if($reportInvalidData){
                $now = new DateTime();
                $dateHeure = $now->format('d-m-Y_H-i-s');
                $errorArray = [];
                $io->writeln("Vérification des données disponibles...");
                $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                foreach($parcoursArray as $parcours){
                    if($parcours->getSigle() === null && $parcours->isParcoursDefaut() === false){
                        $errorArray[$parcours->getId()][] = "Le parcours n'a pas de sigle.";
                    }
                    if($parcours->getFormation()?->getSigle() === null){
                        $errorArray[$parcours->getId()][] = "La formation n'a pas de sigle.";
                    }
                    if($parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === null){
                        $errorArray[$parcours->getId()][] = "Le type de diplôme n'a pas de libellé court.";
                    }
                }
                if(count($errorArray) > 0){
                    $missingDataTxt = "";
                    foreach($errorArray as $errorId => $errorMessages){
                        $parcours = $this->entityManager->getRepository(Parcours::class)->find($errorId);
                        $libelleParcours = $parcours->getDisplay() . ' - Formation : ' . $parcours->getFormation()->getDisplayLong() . " - ID : {$parcours->getId()}";
                        $missingDataTxt .= "{$libelleParcours}\n";
                        foreach($errorMessages as $error){
                            $missingDataTxt .= "{$error}\n";
                        }
                        $missingDataTxt .= "\n";
                    }
                    $this->filesystem->appendToFile(__DIR__ . "/../Service/Apogee/export/Missing-data-report-" . $dateHeure . ".txt", $missingDataTxt);
                    $io->warning("Des erreurs ont été détectées. La rapport d'erreurs a été généré");
                    return Command::FAILURE;
                }
                else {
                    $io->success("Aucune donnée manquante détectée.");
                    return Command::SUCCESS;
                }
            }
            // Vérification des doublons sur les codes Apogee
            if($checkDuplicates){
                $io->writeln("Vérification de la présence de doublons sur les codes Apogee depuis la base de données...");
                // nombre de doubles
                $nbEcDuplicates = count($this->entityManager->getRepository(ElementConstitutif::class)->countDuplicatesCode());
                $nbUeDuplicates = count($this->entityManager->getRepository(Ue::class)->countDuplicatesCode());
                $nbSemestreDuplicates = count($this->entityManager->getRepository(Semestre::class)->countDuplicatesCode());
                $nbFicheMatiereDuplicates = count($this->entityManager->getRepository(FicheMatiere::class)->countDuplicatesCode());
                // affichage des résultats
                $io->writeln("Nombre de doublons sur les Fiches Matières : " . $nbFicheMatiereDuplicates);
                $io->writeln("Nombre de doublons sur les EC : " . $nbEcDuplicates);
                $io->writeln("Nombre de doublons sur les UE : " . $nbUeDuplicates);
                $io->writeln("Nombre de doublons sur les Semestres : " . $nbSemestreDuplicates);
                return Command::SUCCESS;
            }
            // Export Excel des LSE d'un parcours
            if($parcoursLseExport){
                $io->writeln("Génération d'export Excel des LSE d'un parcours.");
                $parcours = $this->entityManager->getRepository(Parcours::class)->findOneById($parcoursLseExport);
                if($parcours){
                    $exportName = 'Parcours_' . $parcours->getId();
                    $io->writeln("Parcours trouvé - Formation : " . $parcours->getDisplay());
                    $dto = $this->getDTOForParcours($parcours);
                    $lseArray = $this->getLseObjectArrayForParcours($dto);
                    if($withFilter){
                        $lseArray = $this->mapLseArrayObjectForTest($lseArray, $parcours->getId());
                        $exportName .= "-filtered";
                    }
                    $this->generateSpreadsheetForLSE($lseArray, $exportName);
                    $io->success("Export des LSE généré avec succès.");
                    return Command::SUCCESS;
                }else {
                    $io->warning("Identifiant du Parcours incorrect. (" . $parcoursLseExport . ")");
                    return Command::FAILURE;
                }
            }
            if($reportInvalidApogeeCode){
                $io->writeln("Génération d'un rapport pour les codes Apogée invalides...");
                $errorTxt = [];
                $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                $io->progressStart(count($parcoursArray));
                foreach($parcoursArray as $p){
                    $errorMessage = "";
                    $dto = $this->getDTOForParcours($p);
                    foreach($dto->semestres as $semestre){
                        if(mb_strlen($semestre->semestre->getCodeApogee()) > 8){
                            $errorMessage .= "Le Semestre {$semestre->ordre} : a un code APOGEE supérieur à 8 caractères. ({$semestre->semestre->getCodeApogee()})\n";
                        }
                        foreach($semestre->ues as $ue){
                            if(mb_strlen($ue->getCodeApogee()) > 8){
                                $errorMessage .= "L'{$ue->ue->display()} : a un code APOGEE supérieur à 8 caractères. ({$ue->getCodeApogee()})\n";
                            }
                            foreach($ue->elementConstitutifs as $ecUE){
                                $codeEcUe = $ecUE->elementConstitutif->getCodeApogee() ?? $ecUE->elementConstitutif->getFicheMatiere()?->getCodeApogee() ?? "";
                                if(mb_strlen($codeEcUe) > 8){
                                    $errorMessage .= "L'{$ecUE->elementConstitutif->getCode()} ({$ecUE->elementConstitutif->getUe()->display()}) :"
                                    . " a un code APOGEE supérieur à 8 caractères. ({$codeEcUe})\n";
                                }
                                foreach($ecUE->elementsConstitutifsEnfants as $ecE){
                                    $codeEcE = $ecE->elementConstitutif->getCodeApogee() ?? $ecE->elementConstitutif->getFicheMatiere()?->getCodeApogee() ?? "";
                                    if(mb_strlen($codeEcE) > 8){
                                        $errorMessage .= "L'{$ecE->elementConstitutif->getCode()} ({$ecE->elementConstitutif->getUe()->display()}) :"
                                        . " a un code APOGEE supérieur à 8 caractères. ({$codeEcE})\n";
                                    }
                                }
                            }
                            foreach($ue->uesEnfants() as $ueE){
                                if(mb_strlen($ueE->getCodeApogee()) > 8){
                                    $errorMessage .= "L'{$ueE->ue->display()} : a un code APOGEE supérieur à 8 caractères. ({$ueE->getCodeApogee()})\n";
                                }
                                foreach($ueE->elementConstitutifs as $ecUeE){
                                    $codeEcUeE = $ecUeE->elementConstitutif->getCodeApogee() ?? $ecUeE->elementConstitutif->getFicheMatiere()?->getCodeApogee() ?? "";
                                    if(mb_strlen($codeEcUeE) > 8){
                                        $errorMessage .= "L'{$ecUeE->elementConstitutif->getCode()} ({$ecUeE->elementConstitutif->getUe()->display()}) :"
                                        . " a un code APOGEE supérieur à 8 caractères. ({$codeEcUeE})\n";
                                    }
                                    foreach($ecUeE->elementsConstitutifsEnfants as $ecE){
                                        $codeEcEUeE = $ecE->elementConstitutif->getCodeApogee() ?? $ecE->elementConstitutif->getFicheMatiere()?->getCodeApogee() ?? "";
                                        if(mb_strlen($codeEcEUeE) > 8){
                                            $errorMessage .= "L'{$ecE->elementConstitutif->getCode()} ({$ecE->elementConstitutif->getUe()->display()}) :"
                                            . " a un code APOGEE supérieur à 8 caractères. ({$codeEcEUeE})\n";
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if(!empty($errorMessage)){
                        $errorMessage = "Le Parcours {$p->getId()} - {$p->getDisplay()} - Formation {$p->getFormation()->getDisplayLong()} est invalide :\n\n" . $errorMessage . "\n\n";
                        $errorTxt[] = $errorMessage;
                    }
                    $io->progressAdvance();
                }
                if(count($errorTxt) > 0){
                    $date = new DateTime();
                    $now = $date->format("d-m-Y_H-i-s");
                    $this->filesystem->appendToFile(
                        __DIR__ . "/../Service/Apogee/export/"
                        . "Report-invalid-Apogee-codes-{$now}.txt",
                        implode($errorTxt)
                    );
                    $io->writeln("\nRapport généré !");
                    $io->warning("Des erreurs de codes APOGEE ont été détectées.");
                }
                else {
                    $io->success("Aucun code APOGEE invalide détecté.");
                }
                return Command::SUCCESS;

            }
            // Export Excel des LSE de tous les parcours disponibles
            if($fullLseExport){
                // LSE déjà traité
                $lseCreated = [];
                // ELP présents dans Apogée
                $elpApogeeData = json_decode(
                    file_get_contents(
                        __DIR__ . "/../Service/Apogee/data-test/" . self::$codElpApogeeDataTest
                    )
                );
                $io->writeln("Génération des LSE pour tous les parcours...");
                $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                $io->progressStart(count($parcoursArray));
                $lseArray = [];
                $exportName = "ALL";
                foreach($parcoursArray as $parcours){
                    $dataLSE = $this->getLseObjectArrayForParcours(
                        $this->getDTOForParcours($parcours),
                        $elpApogeeData,
                        $lseCreated,
                    );
                    // if($withFilter){
                    //     $dataLSE = $this->mapLseArrayObjectForTest($dataLSE, $parcours->getId());
                    // }
                    $lseArray[] = $dataLSE;
                    $io->progressAdvance();
                }
                if($withFilter){
                    $exportName .= "-filtered";
                }
                $lseArray = array_merge(...$lseArray);
                $this->generateSpreadsheetForLSE($lseArray, $exportName);
                $io->writeln("\nCréation du fichier Excel...");
                if($withJsonExport){
                    $io->writeln("Création de l'export JSON...");
                    $date = new DateTime();
                    $now = $date->format("d-m-Y_H-i-s");
                    $jsonCodLse = array_map(fn($lse) => $lse->codListeElp, $lseArray);
                    $this->filesystem->appendToFile(__DIR__ . "/../Service/Apogee/export/COD_LSE_TEST-{$now}.json", json_encode($jsonCodLse));
                }
                $io->success("Export de tous les LSE généré avec succès.");
                return Command::SUCCESS;
            }
            // Vérifie un fichier d'export JSON pour les LSE de test (doublons)
            if($checkLseTestJsonExport){
                $io->writeln("Vérification de la présence de doublons dans le fichier JSON (LSE - TEST)");
                $content = (string) file_get_contents(__DIR__ . "/../Service/Apogee/data-test/" . self::$fullLseExportDataTest);
                $decoded = json_decode($content, true);
                $lseArray = is_array($decoded) ? $decoded : [];
                $io->progressStart(count($lseArray));
                $nbLseDuplicates = 0;
                $duplicatesList = [];
                $countValues = array_count_values(array_map('strval', $lseArray));
                foreach($countValues as $value => $count){
                    if($count > 1){
                        ++$nbLseDuplicates;
                        $duplicatesList[] = $value;
                    }
                    $io->progressAdvance();
                }
                if($nbLseDuplicates === 0){
                    $io->success("Aucun doublon détecté !");
                    return Command::SUCCESS;
                }
                else {
                    dump($duplicatesList);
                    $io->warning("Des doublons ont été detecté. ({$nbLseDuplicates})");
                    return Command::FAILURE;
                }
            }
            // Insère tous les LSE disponibles dans APOTEST
            if($fullLseInsertion){
                $io->writeln("Utilisation du Web Service APOTEST");
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer les LSE de TOUS LES PARCOURS ?")){
                    $nbLseInsere = 0;
                    $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                    $nbParcours = count($parcoursArray);
                    // LSE déjà traité
                    $lseCreated = [];
                    // ELP présents dans Apogée
                    $elpApogeeData = json_decode(
                        file_get_contents(
                            __DIR__ . "/../Service/Apogee/data-test/" . self::$codElpApogeeDataTest
                        )
                    );
                    if($this->verifyUserIntent($io, "Il y a {$nbParcours} parcours à traiter. Continuer ?")){
                        $io->writeln("Initialisation du Web Service...");
                        $this->createSoapClient();
                        $io->progressStart($nbParcours);
                        foreach($parcoursArray as $parcours){
                            $dto = $this->getDTOForParcours($parcours);
                            $lseArray = $this->getLseObjectArrayForParcours($dto, $elpApogeeData, $lseCreated);
                            $this->insertSeveralLSE($lseArray);
                            $io->progressAdvance();
                            $nbLseInsere += count($lseArray);
                        }
                        $io->writeln("\nInsertion réussie !");
                        $io->writeln("{$nbLseInsere} LSE Insérés.");
                    } else {
                        $io->warning("La commande d'insertion a été annulée.");
                    }
                    return Command::SUCCESS;
                }
                else {
                    $io->warning("La commande d'insertion a été annulée.");
                    return Command::SUCCESS;
                }
            }
            if($checkDuplicatesFromJsonExport){
                $io->writeln("Vérification des doublons depuis l'export JSON...");
                $content = (string) file_get_contents(__DIR__ . "/../Service/Apogee/data-test/" . self::$allParcoursCodElpExport);
                $decoded = json_decode($content, true);
                $codElpArray = is_array($decoded) ? $decoded : [];
                $io->progressStart(count($codElpArray));
                $nbDoublons = 0;
                $doublonArray = [];
                $counts = array_count_values(array_map('strval', $codElpArray));
                foreach($counts as $codeElp => $nb){
                    if($nb > 1){
                        $nbDoublons += ($nb - 1);
                        $doublonArray[] = $codeElp;
                    }
                    $io->progressAdvance();
                }
                if($nbDoublons === 0){
                    $io->success("Aucun doublon détecté !");
                } else {
                    if($withJsonExport){
                        $date = new DateTimeImmutable();
                        $now = $date->format("d-m-Y_H-i-s");
                        $this->filesystem->appendToFile(
                            __DIR__ . "/../Service/Apogee/export/" . "Doublons-export-" . $now  . ".json",
                            json_encode($doublonArray));
                    }
                    dump($doublonArray);
                    $io->warning("Des doublons ont été détecté ! ({$nbDoublons})");
                    if($withJsonExport){
                        $io->writeln("Le rapport contenant les doublons a été généré.");
                    }
                }
                return Command::SUCCESS;
            }
            // Vérifie qu'il n'y a pas trop d'enfants imbriqués dans les parcours disponibles
            if($checkNestedChildren){
                $io->writeln("Vérification des enfants imbriqués pour les parcours...");
                $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                $io->progressStart(count($parcoursArray));
                $globalErrorArray = [];
                foreach($parcoursArray as $p){
                    $dto = $this->getDTOForParcours($p);
                    $errorMessage = "";
                    foreach($dto->semestres as $semestre){
                        foreach($semestre->ues as $ue){
                            // Si trop d'UE imbriquées
                            foreach($ue->uesEnfants() as $ueEnfant){
                                if(count($ueEnfant->uesEnfants()) > 0){
                                    $errorMessage .= "L'{$ueEnfant->display} a trop d'enfants imbriqués.\n";
                                }
                                // Si une UE enfant a trop d'EC imbriqués
                                foreach($ueEnfant->elementConstitutifs as $ec){
                                    foreach($ec->elementsConstitutifsEnfants as $ecEnfant){
                                        if(count($ecEnfant->elementsConstitutifsEnfants) > 0){
                                            $errorMessage .= "L'{$ecEnfant->elementConstitutif->getCode()} de l'{$ueEnfant->display} as trop d'enfants imbriqués.\n";
                                        }
                                    }
                                }
                            }
                            // Si trop d'EC imbriqués pour une UE "standard"
                            foreach($ue->elementConstitutifs as $ec){
                                foreach($ec->elementsConstitutifsEnfants as $ecEnfant){
                                    if(count($ecEnfant->elementsConstitutifsEnfants) > 0){
                                        $errorMessage .= "L'{$ecEnfant->elementConstitutif->getCode()} de l'{$ue->display} as trop d'enfants imbriqués.\n";
                                    }
                                }
                            }
                        }
                    }
                    if(mb_strlen($errorMessage) > 0){
                        $errorMessage .= $p->getFormation()->getDisplayLong() . "\n\n" . $errorMessage . "\n";
                        $globalErrorArray[] = $errorMessage;
                    }
                    $io->progressAdvance();
                }
                if(count($globalErrorArray) === 0){
                    $io->success("Aucune erreur d'imbrication détectée.");
                    return Command::SUCCESS;
                }else {
                    dump($globalErrorArray);
                    $io->warning("Des erreurs d'imbrication ont été détectées !");
                    return Command::FAILURE;
                }
            }
            // Vérifie les doublons entre ORéOF et des export JSON des codes APOGEE (LSE & ELP)
            if($checkDuplicatesWithApogee){
                $io->writeln("Vérification des doublons entre les codes ORéOF et ceux présents dans APOGEE...");
                $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                $nbDoublonsELP = 0;
                $nbDoublonsLse = 0;
                $listeDoublonsELP = [];
                $listeDoublonsLSE = [];
                $io->progressStart(count($parcoursArray));
                $codeElpApogee = json_decode(file_get_contents(__DIR__ . "/../Service/Apogee/data-test/" . self::$codElpApogeeDataTest));
                $codeLseApogee = json_decode(file_get_contents(__DIR__ . "/../Service/Apogee/data-test/" . self::$codLseApogeeDataTest));
                foreach($parcoursArray as $parcours){
                    $elpArray = $this->generateSoapObjectsForParcours($parcours);
                    if($withFilter){
                        $elpArray = $this->filterInvalidElpArray($elpArray);
                        $elpArray = $this->filterAlreadyInsertedElpArray($elpArray);
                    }
                    $lseArray = $this->getLseObjectArrayForParcours($this->getDTOForParcours($parcours));
                    $lseArray = array_map(fn($lse) => $lse->codListeElp, $lseArray);
                    $elpArray = array_map(fn($elp) => $elp->codElp, $elpArray);
                    foreach($elpArray as $codeElp){
                        if(in_array($codeElp, $codeElpApogee, true)){
                            ++$nbDoublonsELP;
                            $listeDoublonsELP[] = $codeElp;
                        }
                        // if(in_array($codeElp, $codeLseApogee, true)){
                        //     ++$nbDoublonsLse;
                        //     $listeDoublonsLSE[] = $codeElp;
                        // }
                    }
                    foreach($lseArray as $lse){
                        if(in_array($lse, $codeLseApogee, true)){
                            ++$nbDoublonsLse;
                            $listeDoublonsLSE[] = $lse;
                        }
                    }
                    $io->progressAdvance();
                }
                $io->writeln("\nVérification des doublons réussie !");
                if($nbDoublonsELP === 0 && $nbDoublonsLse === 0){
                    $io->success("Aucun doublon détecté.");
                    return Command::SUCCESS;
                }
                elseif ($nbDoublonsELP > 0 || $nbDoublonsLse > 0) {
                    $now = new DateTime();
                    $dateHeure = $now->format("d-m-Y_H-i-s");
                    $io->writeln("Des doublons ont été détectés !");
                    $io->writeln("{$nbDoublonsLse} codes de liste (LSE)");
                    $io->writeln("{$nbDoublonsELP} codes d'élément pédagogique (ELP)");
                    $dataError = json_encode(array_merge($listeDoublonsELP, $listeDoublonsLSE));
                    $this->filesystem->appendToFile(__DIR__ . "/../Service/Apogee/data-test/ERROR-Apogee-duplicates-" . $dateHeure . ".json", $dataError);
                    $io->warning("Des doublons ont été détectés ! Le rapport d'erreur a été généré.");
                    return Command::FAILURE;
                }
            }
            // Insertion de test d'un ELP
            if($dummyInsertion){
                $io->write("Utilisation du Web Service APOTEST");
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer dans APOTEST (ELP) ?")){
                    // Récupération des donnéees à insérer
                    $parcours = $this->entityManager->getRepository(Parcours::class)->findOneById(405);
                    $dto = $this->getDTOForParcours($parcours);
                    $ec = $dto->semestres[1]->ues[0]->elementConstitutifs[0];
                    // $elp = new ElementPedagogiDTO6($ec, $dto);
                    $elp = $this->setObjectForSoapCall($ec, $dto, CodeNatuElpEnum::MATI);
                    $elp->codElp = 'TEST112';
                    $elp->codNatureElp = 'MATI';
                    $elp->libCourtElp = "TEST WS PHP 3";
                    $elp->libElp = "TEST WEBSERVICE PHP 12032024";
                    // $elp->nbrCredits = 0;
                    dump($elp);
                    if($this->verifyUserIntent($io, "Les données affichées (ELP) conviennent-elles ?")){
                        try{
                            $io->writeln("Initialisation du Web Service...");
                            // Création du client SOAP
                            $this->createSoapClient();
                            $io->writeln('Création du client SOAP réussie.');
                            // Insertion d'un élément
                            $result = $this->insertOneElp($elp);
                            $io->writeln("Résultat de l'appel au Web Service :");
                            dump($result);
                            $io->writeln('Insertion réussie !');
                            return Command::SUCCESS;
                        } catch (Exception $e) {
                            $io->writeln("Une erreur est survenue durant l'insertion. (ELP)");
                            $io->writeln("Message : " . $e->getMessage());
                            return Command::FAILURE;
                        }

                    }else {
                        $io->warning('La commande a été annulée.');
                        return Command::SUCCESS;
                    }
                }
                else {
                    $io->warning('La commande a été annulée.');
                    return Command::SUCCESS;
                }
            }
            // Insertion de test d'une liste (LSE)
            if($dummyLseInsertion){
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer dans APOTEST (LSE) ?")){
                    // Création des données à insérer
                    $lseObject = new ListeElementPedagogiDTO3(
                        'TEST110', 'O', 'TEST INSERT LISTE WS',
                        'TEST INSERTION LISTE WEB SERVICE 12032024',
                        ['TEST111', 'TEST112']
                    );
                    dump($lseObject);
                    if($this->verifyUserIntent($io, "Les données à insérer (LSE) conviennent-elles ?")){
                        try{
                            // Création du client SOAP
                            $io->writeln("Initialisation du Web Service...");
                            $this->createSoapClient();
                            $io->writeln("Création du client SOAP réussie.");
                            // Insertion de la liste LSE
                            $result = $this->insertOneLSE($lseObject);
                            $io->writeln("Résultat de l'appel au Web Service :");
                            dump($result);
                            $io->writeln('Insertion réussie !');
                            return Command::SUCCESS;
                        } catch (Exception $e) {
                            $io->writeln("Une erreur est survenue durant l'insertion. (LSE)");
                            $io->writeln("Message : " . $e->getMessage());
                            return Command::FAILURE;
                        }
                    }
                    else {
                        $io->warning('La commande a été annulée.');
                        return Command::SUCCESS;
                    }
                }
                else {
                    $io->warning('La commande a été annulée.');
                    return Command::SUCCESS;
                }

            }
            if($formatFormationFile){
                $date = new DateTime();
                $now = $date->format("d-m-Y_H-i-s");
                $io->writeln('Ouveture du fichier contenant les formations...');
                $textData = file_get_contents(__DIR__ . "/../Service/Apogee/data-test/" . self::$formationToExcludeFile);
                $textData = preg_replace('/\xA0|\\t|\\r/um', ' ', $textData);
                $textData = preg_split('/\s\\n/', $textData);
                $textData = array_map(function($text){
                    preg_match('/Slug : (.+) - ID : ([0-9]+) ((.*) \s*ID : ([0-9]+)){0,1}/u', $text, $matches);
                    return [
                        'slugFormation' => $matches[1],
                        'idFormation' => (int)$matches[2],
                        'libelleParcours' => $matches[4] ?? null,
                        'idParcours' => isset($matches[5]) ? (int)$matches[5] : null
                    ];
                }, $textData);
                $io->writeln('Encodage en cours...');
                $this->filesystem->appendToFile(
                    __DIR__ . "/../Service/Apogee/export/Formations-a-exclure-{$now}.json",
                    json_encode($textData)
                );
                $io->success('Export réussi !');
                return Command::SUCCESS;
            }
            if($dumpParcoursToInsert){
                if($dumpParcoursToInsert !== "exclusion" && $dumpParcoursToInsert !== "no-exclusion"){
                    $io->warning("Option non reconnue. Doit être ['exclusion', 'no-exclusion']");
                    return Command::FAILURE;
                }
                $withExclusion = $dumpParcoursToInsert === "exclusion";

                $io->writeln("Dump des parcours disponibles...");
                $this->retrieveParcoursDataFromDatabase($withExclusion, true);
                $io->writeln("Enregistrement réussi !");

                return Command::SUCCESS;
            }
            if($checkDifferences){
                $date = new DateTime();
                $now = $date->format("d-m-Y_H-i-s");
                $old = json_decode(
                    file_get_contents(
                        __DIR__ . "/../Service/Apogee/data-test/" . self::$oldJsonFile
                    )
                );
                $new = json_decode(
                    file_get_contents(
                        __DIR__ . "/../Service/Apogee/data-test/" . self::$newJsonFile
                    )
                );
                $differences = array_values(array_diff($new, $old));
                $io->writeln("Calcul des différences...");
                $this->filesystem->appendToFile(
                    __DIR__ . "/../Service/Apogee/export/json-differences-{$now}.json",
                    json_encode($differences)
                );
                $io->writeln("Rapport généré !");
                return Command::SUCCESS;
            }

            return Command::SUCCESS;
        }

        elseif($mode === "production"){
            $io->warning("Commande en mode PRODUCTION");
            // Insère tous les ELP de tous les parcours disponibles
            if($fullParcoursInsertion){
                $io->writeln("Utilisation du Web Service APOGEE - PRODUCTION");
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer les ELP de TOUS LES PARCOURS ?")){
                    $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                    $nbParcours = count($parcoursArray);
                    if($this->verifyUserIntent($io, "Il y a {$nbParcours} parcours disponibles. Continuer ?")){
                        try{
                            $nbElpInsere = 0;
                            $io->writeln('Initialisation du Web Service...');
                            // $this->createSoapClientProduction();
                            $io->writeln('Insertion des données en cours...');
                            $io->progressStart($nbParcours);
                            foreach($parcoursArray as $parcours){
                                $soapObjectArray = $this->generateSoapObjectsForParcours($parcours);
                                $soapObjectArray = $this->filterInvalidElpArray($soapObjectArray);
                                $soapObjectArray = $this->filterAlreadyInsertedElpArray($soapObjectArray);
                                $this->insertSeveralElp($soapObjectArray);
                                $io->progressAdvance();
                                $nbElpInsere += count($soapObjectArray);
                            }
                            $io->writeln("\nInsertion réussie !");
                            $io->writeln("{$nbElpInsere} ELP insérés.");
                            return Command::SUCCESS;
                        } catch (Exception $e) {
                            $io->writeln("\nUne erreur est survenue durant l'insertion.");
                            $io->writeln("Message : " . $e->getMessage());
                            return Command::FAILURE;
                        }
                    }
                    else {
                        $io->warning("La commande d'insertion a été annulée.");
                        return Command::SUCCESS;
                    }
                }
                else {
                    $io->warning("La commande d'insertion a été annulée.");
                    return Command::SUCCESS;
                }
            }
            // Insère tous les LSE disponibles dans APOTEST
            if($fullLseInsertion){
                $io->writeln("Utilisation du Web Service APOGEE - PRODUCTION");
                if($this->verifyUserIntent($io, "Voulez-vous vraiment insérer les LSE de TOUS LES PARCOURS ?")){
                    $parcoursArray = $this->retrieveParcoursDataFromDatabase();
                    $nbParcours = count($parcoursArray);
                    $nbLseInsere = 0;
                    // LSE déjà traité
                    $lseCreated = [];
                    // ELP présents dans Apogée
                    $elpApogeeData = json_decode(
                        file_get_contents(
                            __DIR__ . "/../Service/Apogee/data-test/" . self::$codElpApogeeDataTest
                        )
                    );
                    if($this->verifyUserIntent($io, "Il y a {$nbParcours} parcours à traiter. Continuer ?")){
                        $io->writeln("Initialisation du Web Service...");
                        // $this->createSoapClientProduction();
                        $io->progressStart($nbParcours);
                        foreach($parcoursArray as $parcours){
                            $dto = $this->getDTOForParcours($parcours);
                            $lseArray = $this->getLseObjectArrayForParcours($dto, $elpApogeeData, $lseCreated);
                            $this->insertSeveralLSE($lseArray);
                            $io->progressAdvance();
                            $nbLseInsere += count($lseArray);
                        }
                        $io->writeln("\nInsertion réussie !");
                        $io->writeln("{$nbLseInsere} LSE insérés.");
                    } else {
                        $io->warning("La commande d'insertion a été annulée.");
                    }
                    return Command::SUCCESS;
                }
                else {
                    $io->warning("La commande d'insertion a été annulée.");
                    return Command::SUCCESS;
                }
            }
            return Command::INVALID;
        }
        else{
            $io->error("Le mode d'exécution est invalide. Il devrait être 'test' ou 'production'");
            return Command::FAILURE;
        }
    }

    /**
     * Créer un ELP à partir d'un Semestre, UE, ou EC
     * @param StructureEc|StructureUe|StructureSemestre $elementPedagogique Données sources
     * @param StructureParcours $dto DTO du parcours
     * @param ?CodeNatuElpEnum $natureElp Nature de l'élément pédagogique
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     */
    private function setObjectForSoapCall(
        StructureEc|StructureUe|StructureSemestre $elementPedagogique,
        StructureParcours $dto,
        ?CodeNatuElpEnum $natureElp = null,
        bool $withChecks = false
    ) : ElementPedagogiDTO6 {
        $tableauParamCE = [];
        if($natureElp !== null && in_array($natureElp->value, ['MATI', 'MATM', 'MATP', 'MATS']) && $elementPedagogique instanceof StructureEc){
           $tableauParamCE = $this->configureChargeEnseignementForEC($elementPedagogique);
        }
        return new ElementPedagogiDTO6($elementPedagogique, $dto, $natureElp, $withChecks, $tableauParamCE);
    }

    /**
     * Exporte tous les ELP d'une formation, en sélectionnant les EC, UE, ou Semestres
     * @param OutputInterface $output Sortie de la commande
     * @param string $type Sélectionne le type, dans la liste : [EC, UE, SEMESTRE]
     */
    private function saveFullExportAsSpreadsheet(
        OutputInterface $output,
        string $type,
        bool $withFilter = false,
        bool $withJsonExport = false,
        bool $withExclusionOption = false
    ): void
    {
        // retrieve data
        $dataArray = $this->retrieveParcoursDataFromDatabase();
        $totalElement = count($dataArray);
        // progress bar
        $progressBar = new ProgressBar($output, $totalElement);
        // transform into valid soap object
        $soapObjectArray = [];
        // export typename for file
        $exportTypeName = "";
        foreach($dataArray as $parcours){
            $dto = $this->getDTOForParcours($parcours);
            if($type === "EC"){
                foreach($dto->semestres as $semestre) {
                    // UE
                    foreach($semestre->ues as $ue) {
                        // EC à insérer
                        foreach($ue->elementConstitutifs as $ec) {
                            $this->addEcToElpArray($soapObjectArray, $ec, $dto);
                        }
                        // UE Enfants
                        foreach($ue->uesEnfants() as $ueEnfant) {
                            foreach($ueEnfant->elementConstitutifs as $ec) {
                                // EC à insérer
                                $this->addEcToElpArray($soapObjectArray, $ec, $dto);
                            }
                        }
                    }
                }
                $exportTypeName = "EC";
                $progressBar->advance();
            }
            elseif ($type === "UE") {
                foreach($dto->semestres as $semestre){
                    foreach($semestre->ues as $ue){
                        $this->addUeToElpArray($soapObjectArray, $ue, $dto, false, false);
                    }
                }
                $exportTypeName = "UE";
                $progressBar->advance();
            }
            elseif ($type === "SEMESTRE"){
                foreach($dto->semestres as $semestre){
                    $this->addSemestreToElpArray($soapObjectArray, $semestre, $dto);
                }
                $exportTypeName = "SEMESTRE";
                $progressBar->advance();
            }
            elseif ($type === "PARCOURS"){
                $exportTypeName = "ALL_PARCOURS";
                $dataELP = $this->generateSoapObjectsForParcours($parcours);
                if($withFilter){
                    $dataELP = $this->filterInvalidElpArray($dataELP);
                    $exportTypeName .= "-filtered";
                }
                if($withExclusionOption){
                    $dataELP = $this->filterAlreadyInsertedElpArray($dataELP);
                    $exportTypeName .= "-EXCLUDED";

                }
                $soapObjectArray[] = $dataELP;
                $progressBar->advance();
            }

        }
        if($type === "PARCOURS"){
            $soapObjectArray = array_merge(...$soapObjectArray);
            if($withJsonExport){
                $jsonCodeApogee = array_map(fn($elp) => $elp->codElp, $soapObjectArray);
                $date = new DateTime();
                $now = $date->format("d-m-Y_H-i-s");
                $this->filesystem->appendToFile(
                    __DIR__ . "/../Service/Apogee/export/OREOF-COD_ELP-" . $exportTypeName . "-" . $now . ".json",
                    json_encode($jsonCodeApogee)
                );
            }
        }
        $this->generateSpreadsheet($soapObjectArray, $exportTypeName);
    }

    /**
     * Crée les ELP correspondants depuis un parcours passé en paramètre
     * @param Parcours $parcours Le parcours que l'on souhaite générer en ELP
     * @return array Tableau comprenant tous les ELP d'un parcours
     */
    private function generateSoapObjectsForParcours(Parcours $parcours, bool $withChecks = false) : array {
        $dto = $this->getDTOForParcours($parcours);
        $soapObjectArray = [];
        foreach($dto->semestres as $semestre){
            if($semestre->semestre->isNonDispense() === false){
                $this->addSemestreToElpArray($soapObjectArray, $semestre, $dto, $withChecks);
                foreach($semestre->ues as $ue){
                    $this->addUeToElpArray($soapObjectArray, $ue, $dto, $withChecks);
                    foreach($ue->elementConstitutifs as $ec){
                        $this->addEcToElpArray($soapObjectArray, $ec, $dto, $withChecks);
                    }
                }
            }
        }

        return $soapObjectArray;
    }

    /**
     * Permet d'obtenir la structure DTO pour un parcours donné
     * @param Parcours $parcours
     * @return StructureParcours DTO du parcours
     */
    private function getDTOForParcours(Parcours $parcours): StructureParcours
    {
        return $this->typeDiplomeResolver->fromParcours($parcours)->calcul($parcours);
    }

    /**
     * Génère dans un fichier Excel les ELP fournis en paramètre.
     * @param array $ElpArray Les éléments pédagogiques : ElementPedagogiDTO6[]
     * @return void
     */
    private function generateSpreadsheet(array $ElpArray, string $exportTypeName = ""): void
    {
        // spreadsheet headers
        $headers = [
            "codElp", "libCourtElp", "libElp", "codNatureElp",
            "codComposante", "temModaliteControle", "nbrCredits",
            "volume", "uniteVolume", "codPeriode", "listeCentreInsPedagogi",
            "paramCE"
        ];
        // cast element into array values
        $ElpArray = array_map(
            fn($elp) => [
                $elp->codElp, $elp->libCourtElp, $elp->libElp,
                $elp->codNatureElp, $elp->codComposante, $elp->temModaliteControle,
                $elp->nbrCredits ?? "", $elp->volume, $elp->uniteVolume, $elp->codPeriode,
                // CIP list
                implode(", ", array_map(
                        fn($cip) => $cip->codCentreInsPedagogi,
                        $elp->listCentreInsPedagogi->centreInsPedagogi
                    )
                ), isset($elp->listParamChargEns) ? $elp->listParamChargEns->printInformation() : ""
            ],
            $ElpArray
        );
        // Write to spreadsheet
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->fromArray($headers);
        $activeWorksheet->fromArray($ElpArray, startCell: "A2");
        // Write to file
        $now = new DateTime();
        $date = $now->format('d-m-Y_H-i-s');
        $filename = __DIR__ . "/../Service/Apogee/export/{$exportTypeName}-ELP-export-{$date}.xlsx";
        $this->filesystem->dumpFile($filename, "");
        $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
        $writer->save($filename);
    }

    /**
     * Prompt pour poser une question à l'utilisateur de la commande
     * @param SymfonyStyle $io Entrée / Sortie de la commande
     * @param string $message Question à poser
     * @return boolean Vrai si l'utilisateur confirme la question, Faux sinon
     */
    private function verifyUserIntent(SymfonyStyle $io, string $message) : bool {
        return $io->ask("{$message} [Y/n]", 'n', function($message) {
            if($message === "Y"){
                return true;
            }
            else {
                return false;
            }
        });
    }

    /**
     * Création du client SOAP, selon la WSDL fournie dans .env.local
     */
    private function createSoapClient() : void {
        $wsdl = $this->parameterBag->get('WSDL_APOTEST');
        $this->soapClient = new SoapClient($wsdl, [
            "trace" => true
        ]);
    }

    /**
     * Méthode pour appeler la fonction d'insertion d'un ELP du Web Service Apogee
     * @param ElementPedagogiDTO6 $elementPedagogique Élément à insérer
     */
    private function insertOneElp(ElementPedagogiDTO6 $elementPedagogique): mixed {
        $param = new stdClass();
        $param->elementPedagogi = $elementPedagogique;
        if($this->soapClient){
            return $this->soapClient->__soapCall("creerModifierELP", [$param]);
        }
        else {
            throw new Exception("Soap Client is not initialized.");
        }
    }

    /**
     * Appelle la fonction d'insertion d'une liste LSE du Web Service Apogee
     * @param ListeElementPedagogiDTO3 $lseObject Élément à insérer via le WS
     */
    private function insertOneLSE(ListeElementPedagogiDTO3 $lseObject): mixed {
        $param = new stdClass();
        $param->listeElementPedagogi = $lseObject;
        if($this->soapClient){
            return $this->soapClient->__soapCall("creerLSE", [$param]);
        }
        else {
            throw new Exception("Soap Client is not initialized.");
        }
    }

    /**
     * Appelle le Web Service d'insertion d'ELP d'Apogee,
     * et insère plusieurs valeurs d'un coup
     * @param array $elpArray
     */
    private function insertSeveralElp(array $elpArray): void
    {
        $dataWS = array_map([$this, 'mapDataForWebService'], $elpArray);
        if($this->soapClient){
            foreach($dataWS as $elpWS){
                $this->soapClient->__soapCall("creerModifierELP", [$elpWS]);
            }
        }else {
            throw new Exception('Soap Client is not initialized.');
        }
    }

    /**
     * Appelle le Web Service d'insertion des listes LSE d'Apogee
     * avec plusieurs valeurs
     * @param array $lseArray Tableau contenant les objets LSE
     */
    private function insertSeveralLSE(array $lseArray): void
    {
        $dataWS = array_map([$this, 'mapDataLseForWebService'], $lseArray);
        if($this->soapClient){
            foreach($dataWS as $lseWS){
                $this->soapClient->__soapCall('creerLSE', [$lseWS]);
            }
        }
        else {
            throw new Exception('Soap Client is not initialized.');
        }
    }


    /**
     * Permet de savoir si une matière mutualisée est la matière porteuse (maître)
     * @return boolean Vrai si la matière est porteuse, Faux sinon
     */
    private function isEcMutualiseMaster(StructureEc $ec, StructureParcours $dto) : bool {
        // si la matière est mutualisée et est l'élément maître
        return $this->isEcMutualise($ec)
        && $dto->parcours->getId() === $ec->elementConstitutif->getFicheMatiere()?->getParcours()?->getId();
    }

    /**
     * Permet de savoir si une matière est mutualisée avec un autre parcours
     * @return boolean Vrai si la matière est mutualisée, Faux sinon
     */
    private function isEcMutualise(StructureEc $ec) : bool {
        // return count($ec->elementConstitutif->getFicheMatiere()?->getFicheMatiereParcours() ?? []) >= 1;
        return $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() === 'MATM';
    }

    /**
     * Ajoute une matière (élément constitutif) dans le tableau d'ELP,
     * avec les types adéquats
     * @param array &$elpArray Tableau dans lequel on insère l'élément
     * @param StructureEc $ec Element Constitutif
     * @param StructureParcours $dto Structure DTO du parcours complet
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     * @return void
     */
    private function addEcToElpArray(array &$elpArray, StructureEc $ec, StructureParcours $dto, bool $withChecks = false) : void {
        $hasChildren = count($ec->elementsConstitutifsEnfants) > 0;
        // si l'élément est mutualisé, on ne l'insère qu'une fois
        $nature = $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? 'MATI';
        if($this->isEcMutualiseMaster($ec, $dto) && $hasChildren === false && $nature === 'MATM'){
            $natureApogee = CodeNatuElpEnum::MATM;
            if($ec->elementConstitutif->getTypeEc()->getType() === TypeUeEcEnum::STAGE){
                $natureApogee = CodeNatuElpEnum::MATS;
            }
            $elpArray[] = $this->setObjectForSoapCall($ec, $dto, $natureApogee, $withChecks);
        }
        // si l'élément a des enfants, on insère que les enfants
        if($hasChildren){
            // Ne pas insérer les éléments de nature 'CHOI' ?
            $elpArray[] = $this->setObjectForSoapCall($ec, $dto, CodeNatuElpEnum::CHOI, $withChecks);
            foreach($ec->elementsConstitutifsEnfants as $ecEnfant){
                $natureEcEnfant = $ecEnfant->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? 'MATI';
                if($this->isEcMutualiseMaster($ecEnfant, $dto) === true && $natureEcEnfant === 'MATM'){
                    $elpArray[] = $this->setObjectForSoapCall($ecEnfant, $dto, CodeNatuElpEnum::MATM, $withChecks);
                }
                elseif ($this->isEcMutualise($ecEnfant) === false && !$ecEnfant->elementConstitutif->getNatureUeEc()?->isLibre()) {
                    $elpArray[] = $this->setObjectForSoapCall($ecEnfant, $dto, CodeNatuElpEnum::MATI, $withChecks);
                }
                elseif($this->isEcMutualise($ecEnfant) === false && $ecEnfant->elementConstitutif->getNatureUeEc()?->isLibre() === true){
                    $elpArray[] = $this->setObjectForSoapCall($ecEnfant, $dto, CodeNatuElpEnum::CHOI, $withChecks);
                }
            }
        }
        // si c'est une matière standard
        if($hasChildren === false && $nature === 'MATI'){
            $natureApogee = CodeNatuElpEnum::MATI;
            if ($ec->elementConstitutif->getTypeEc()?->getType() === TypeUeEcEnum::STAGE){
                $natureApogee = CodeNatuElpEnum::MATS;
            }
            if($ec->elementConstitutif->getNatureUeEc()?->isLibre()){
                $natureApogee = CodeNatuElpEnum::CHOI;
            }
            $elpArray[] = $this->setObjectForSoapCall($ec, $dto, $natureApogee, $withChecks);
        }
    }

    /**
     * Ajoute une UE dans le tableau d'ELP
     * @param array &$elpArray Tableau d'ELP dans lequel on insère
     * @param StructureUe $ue La source de données de l'UE
     * @param StructureParcours $dto DTO du parcours
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     * @return void
     */
    private function addUeToElpArray(array &$elpArray, StructureUe $ue, StructureParcours $dto, bool $withChecks = false, bool $withEcChildren = true) : void {
        if(count($ue->uesEnfants) > 0){
            // Ne pas insérer les éléments de nature 'CHOI' ?
            $elpArray[] = $this->setObjectForSoapCall($ue, $dto, CodeNatuElpEnum::CHOI, $withChecks);
            foreach($ue->uesEnfants as $ueEnfant){
                if($ueEnfant->ue->getNatureUeEc()?->isLibre()){
                    $elpArray[] = $this->setObjectForSoapCall($ueEnfant, $dto, CodeNatuElpEnum::CHOI, $withChecks);
                }else {
                    $elpArray[] = $this->setObjectForSoapCall($ueEnfant, $dto, CodeNatuElpEnum::UE, $withChecks);
                }
                if($withEcChildren) {
                    foreach($ueEnfant->elementConstitutifs as $ecUeEnfant){
                        $this->addEcToElpArray($elpArray, $ecUeEnfant, $dto, $withChecks);
                    }
                }
            }
        }else {
            if($ue->ue->getNatureUeEc()?->isLibre()){
                $elpArray[] = $this->setObjectForSoapCall($ue, $dto, CodeNatuElpEnum::CHOI, $withChecks);
            }else {
                $elpArray[] = $this->setObjectForSoapCall($ue, $dto, CodeNatuElpEnum::UE, $withChecks);
            }
        }
    }

    /**
     * Ajoute un Semestre dans le tableau d'ELP
     * @param array &$elpArray Tableau d'ELP dans lequel on insère
     * @param StructureSemestre $semestre Données sources du semestre
     * @param StructureParcours $dto DTO du parcours
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     */
    private function addSemestreToElpArray(array &$elpArray, StructureSemestre $semestre, StructureParcours $dto, bool $withChecks = false) : void {
        $elpArray[] = $this->setObjectForSoapCall($semestre, $dto, CodeNatuElpEnum::SEM, $withChecks);
    }

    /**
     * Met en forme les données pour être utilisées par le Web Service Apogee
     * @param ElementPedagogiDTO6 $elp Element à mettre en forme
     * @return stdClass Objet transformé
     */
    private function mapDataForWebService(ElementPedagogiDTO6 $elp) : stdClass {
        $object = new stdClass();
        $object->elementPedagogi = $elp;
        return $object;
    }

    /**
     * Met en forme la liste LSE pour être utilisée par le Web Service Apogee
     * @param ListeElementPedagogiDTO3 $lse Element à mettre en forme
     * @return stdClass Objet transformé
     */
    private function mapDataLseForWebService(ListeElementPedagogiDTO3 $lse) : stdClass {
        $object = new stdClass();
        $object->listeElementPedagogi = $lse;
        return $object;
    }

    /**
     * Récupère les parcours disponibles et valides en base de données
     * @return array Tableau des parcours disponibles à traiter
     */
    private function retrieveParcoursDataFromDatabase(bool $withExclude = true, bool $withJsonExport = false): array
    {
        /**
         * FAIRE VARIER LE FILTRAGE SELON LE BESOIN
         */
        if($withExclude){
            $formationToExclude = json_decode(
                file_get_contents(
                    __DIR__ . "/../Service/Apogee/export/" . self::$formationToExcludeJSON
                )
            );
            $formationIdArray = array_filter(
                array_map(function($exclude){
                    if($exclude->idParcours === null){
                        return $exclude->idFormation;
                    }
                }, $formationToExclude)
            );
            $parcoursIdArray = array_filter(
                array_map(function($exclude){
                    if($exclude->idParcours !== null){
                        return $exclude->idParcours;
                    }
                }, $formationToExclude)
            );
        }
        $dataArray = $this->entityManager->getRepository(Formation::class)->findAll();
        // $dataArray = array_filter($dataArray, [$this, 'filterFormationByPublicationState']);
        // if($withExclude){
        //     // Filtrage des formations
        //     $dataArray = array_filter(
        //         $dataArray,
        //         // false si exclusion, true si inclusion
        //         fn($formation) => in_array($formation->getId(), $formationIdArray, true) === true
        //     );
        // }
        $dataArray = array_map(fn($formation) => $formation->getParcours()->toArray(), $dataArray);
        $dataArray = array_merge(...$dataArray);
        if($withExclude){
            // Filtrage des parcours
            $dataArray = array_filter(
                $dataArray,
                // false si exclusion, true si inclusion
                fn($parcours) => in_array($parcours->getId(), $parcoursIdArray) === true
            );
        }

        if($withJsonExport){
            $filtered = $withExclude ? "exclusion-" : "";
            $now = new DateTime();
            $date = $now->format('d-m-Y_H-i-s');
            $exportJson = array_values(array_map(
                fn($parcours) => $parcours->getFormation()->getDisplayLong() . " - " . $parcours->getLibelle(),
                $dataArray
            ));
            $this->filesystem->appendToFile(
                __DIR__ . "/../Service/Apogee/export/liste-parcours-a-inserer-{$filtered}{$date}.json",
                json_encode($exportJson));
        }

        return $dataArray;
    }

    /**
     * Calcule les charges d'enseignements pour l'element pédagogique fourni
     * @param StructureEc $elementPedagogique Élément pédagogique
     * @return array Paramètres des charges d'enseignement
     */
    private function configureChargeEnseignementForEC(StructureEc $elementPedagogique) : array {
        $typeHeureArray = [];
        if($elementPedagogique->heuresEctsEc->cmPres > 0 || $elementPedagogique->heuresEctsEc->cmDist > 0){
            $nbHeure = $elementPedagogique->heuresEctsEc->cmPres + $elementPedagogique->heuresEctsEc->cmDist;
            $typeHeureArray[] = new TypeHeureDTO(TypeHeureCE::CM, (string)$nbHeure, '300');
        }
        if($elementPedagogique->heuresEctsEc->tdPres > 0 || $elementPedagogique->heuresEctsEc->tdDist > 0){
            $nbHeure = $elementPedagogique->heuresEctsEc->tdPres + $elementPedagogique->heuresEctsEc->tdDist;
            $typeHeureArray[] = new TypeHeureDTO(TypeHeureCE::TD, (string)$nbHeure, '32');
        }
        if($elementPedagogique->heuresEctsEc->tpPres > 0 || $elementPedagogique->heuresEctsEc->tpDist > 0){
            $nbHeure = $elementPedagogique->heuresEctsEc->tpPres + $elementPedagogique->heuresEctsEc->tpDist;
            $typeHeureArray[] = new TypeHeureDTO(TypeHeureCE::TP, (string)$nbHeure, '16');
        }

        if(count($typeHeureArray) > 0){
            $typeHeureArrayDTO = new TableauTypeHeureDTO($typeHeureArray);
            $paramCE = new ParametrageAnnuelCeDTO2('2024', $typeHeureArrayDTO, 'O');
            return [$paramCE];
        }else {
            return [];
        }

    }

    /**
     * Crée la liste d'éléments pédagogiques pour les enfants d'un EC à choix
     * @param StructureEc $ec EC à traiter
     * @param string $libelleCourt Libellé court à utiliser
     * @param string $libelleLong Libellé long à utiliser
     * @return ListeElementPedagogiDTO3|null Liste d'éléments pédagogiques s'il y a des enfants, null sinon
     */
    private function getLseObjectForEcChildren(StructureEc $ec, string $libelleCourt, string $libelleLong) : ListeElementPedagogiDTO3|null {
        $codeApogee = $ec->elementConstitutif->displayCodeApogee();
        if($codeApogee === "Aucun code Apogée"){
            $codeApogee = "ERROR";
        }
        $return = null;
        if(count($ec->elementsConstitutifsEnfants) > 0){
            $return = new ListeElementPedagogiDTO3($codeApogee, 'X', $libelleCourt, $libelleLong, array_map(
                function($ecEnfant) {
                    // $ecEnfant->elementConstitutif->getCodeApogee()
                    $codeApogeeEcEnfant = $ecEnfant->elementConstitutif->displayCodeApogee();
                    if($codeApogeeEcEnfant === "Aucun code Apogée"){
                        $codeApogeeEcEnfant = "ERROR";
                    }
                    return $codeApogeeEcEnfant;
                },
                $ec->elementsConstitutifsEnfants
            ));
        }
        return $return;
    }

    /**
     * Crée la liste d'éléments pédagogiques pour les enfants d'une UE à choix
     * @param StructureUe $ue UE à traiter
     * @param string $libelleCourt Libellé court à utiliser
     * @param string $libelleLong Libellé long à utiliser
     * @return ListeElementPedagogiDTO3|null Liste d'éléments pédagogiques s'il y a des enfants, null sinon
     */
    private function getLseObjectForUeChildren(StructureUe $ue, string $libelleCourt, string $libelleLong) : ListeElementPedagogiDTO3|null {
        $return = null;
        if(count($ue->uesEnfants) > 0){
            $return = new ListeElementPedagogiDTO3($ue->getCodeApogee() ?? 'ERROR', 'X', $libelleCourt, $libelleLong, array_map(
                fn($ueEnfant) => $ueEnfant->getCodeApogee() ?? "ERROR",
                $ue->uesEnfants
            ));
        }
        return $return;
    }

    /**
     * Crée la liste LSE des EC composant une UE
     * @param StructureUe $ue UE à utiliser
     * @param string $libelleCourt Libellé court à utiliser pour la liste
     * @param string $libelleLong Libellé long à utiliser pour la liste
     * @return ListeElementPedagogiDTO3 Liste LSE
     */
    private function getLseEcFromUe(StructureUe $ue, string $libelleCourt, string $libelleLong) : ListeElementPedagogiDTO3 {
        return new ListeElementPedagogiDTO3($ue->getCodeApogee() ?? 'ERROR', 'O', $libelleCourt, $libelleLong, array_map(
            function($ec) {
                $codeApogee = $ec->elementConstitutif->displayCodeApogee();
                if($codeApogee === "Aucun code Apogée"){
                    $codeApogee = "ERROR";
                }
                return $codeApogee;
            },
            $ue->elementConstitutifs
        ));
    }

    /**
     * Crée la liste LSE des UE composant un Semestre
     * @param StructureSemestre $semestre Semestre à traiter
     * @param string $libCourt Libellé court à utiliser pour la liste
     * @param string $libLong Libellé long à utiliser pour la liste
     * @return ListeElementPedagogiDTO3 Liste LSE
     */
    private function getLseUeFromSemestre(StructureSemestre $semestre, string $libCourt, string $libLong) : ListeElementPedagogiDTO3 {
        return new ListeElementPedagogiDTO3($semestre->semestre->getCodeApogee() ?? 'ERROR', 'O', $libCourt, $libLong, array_map(
            fn($ue) => $ue->getCodeApogee(),
            $semestre->ues
        ));
    }

    /**
     * Crée le tableau de liste d'éléments pédagogiques pour un semestre.
     * Descend l'arborescence jusqu'aux EC et leurs enfants possibles
     * @param StructureSemestre $semestre Semestre à utiliser
     * @param StructureParcours $dto DTO du parcours
     * @return array Tableau de listes LSE
     */
    private function getLseObjectArrayForSemestre(StructureSemestre $semestre, StructureParcours $dto) : array {
        $return = [];
        $libelles = $this->getLibellesForListeUE($semestre, $dto);
        $return[] = $this->getLseUeFromSemestre($semestre, $libelles['libCourt'], $libelles['libLong']);
        foreach($semestre->ues as $ue){
            if(count($ue->elementConstitutifs) > 0){
                $libelles = $this->getLibellesForListeEC($ue, $dto);
                $return[] = $this->getLseEcFromUe($ue, $libelles['libCourt'], $libelles['libLong']);
            }
            foreach($ue->elementConstitutifs as $ec){
                if(count($ec->elementsConstitutifsEnfants) > 0){
                    $libelles = $this->getLibellesForEcWithChildren($ec, $dto);
                    $return[] = $this->getLseObjectForEcChildren($ec, $libelles['libCourt'], $libelles['libLong']);
                }
            }
            if(count($ue->uesEnfants()) > 0){
                $libelles = $this->getLibellesForUeWithChildren($ue, $dto);
                $return[] = $this->getLseObjectForUeChildren($ue, $libelles['libCourt'], $libelles['libLong']);
                foreach($ue->uesEnfants() as $ueEnfant){
                    if(count($ueEnfant->elementConstitutifs) > 0){
                        $libelles = $this->getLibellesForListeEC($ueEnfant, $dto);
                        $return[] = $this->getLseEcFromUe($ueEnfant, $libelles['libCourt'], $libelles['libLong']);
                    }
                    foreach($ueEnfant->elementConstitutifs as $ec){
                        if(count($ec->elementsConstitutifsEnfants) > 0){
                            $libelles = $this->getLibellesForEcWithChildren($ec, $dto);
                            $return[] = $this->getLseObjectForEcChildren($ec, $libelles['libCourt'], $libelles['libLong']);
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Génère les listes LSE pour un parcours complet
     * @param StructureParcours $parcours Parcours à utiliser
     * @return array Tableau comportant des LSE de type ListeElementPedagogiDTO3
     */
    private function getLseObjectArrayForParcours(
        StructureParcours $parcours,
        array $elpApogeeArray = [],
        array &$lseCreated = [],
    ) : array {
        $return = [];
        // Inclure la liste des semestres ?
        // $libelleListeSemestre = $parcours->parcours->getFormation()->getTypeDiplome()->getLibelleCourt() . " "
        //     . $parcours->parcours->getFormation()->getSigle() . " "
        //     . $parcours->parcours->getSigle();
        // $libCourtListeSemestre = "SEM " . $libelleListeSemestre;
        // $libLongListeSemestre = "Semestres " . $libelleListeSemestre;
        // $return[] = [
        //     new ListeElementPedagogiDTO3('LISTE_SEMESTRE', 'O', $libCourtListeSemestre, $libLongListeSemestre, array_map(
        //         fn($semestre) => $semestre->semestre->getCodeApogee(),
        //         $parcours->semestres
        //     ))
        // ];
        // LSE présent dans APOGEE
        $lseInApogee = json_decode(
            file_get_contents(
                __DIR__ . "/../Service/Apogee/data-test/" . self::$codLseApogeeDataTest
            )
        );
        foreach($parcours->semestres as $semestre){
            $return[] = $this->getLseObjectArrayForSemestre($semestre, $parcours);
        }
        // Filtrer les LSE pour éviter d'insérer avec des éléments manquants ou des doublons
        $return = array_merge(...$return);
        $return = array_filter($return,
            function($lse) use ($elpApogeeArray, &$lseCreated, $lseInApogee){
                $result = true;
                // Si ce code de LSE a déjà été inséré
                if(in_array($lse->codListeElp, $lseCreated, true)){
                    $result = false;
                }else {
                    $lseCreated[] = $lse->codListeElp;
                }
                foreach($lse->listElementPedagogi->elementPedagogi as $codElp){
                    // Si tous les ELP sont présents dans l'export APOGEE
                    if(!in_array($codElp->codElp, $elpApogeeArray, true) || $codElp->codElp === "ERROR"){
                        $result = false;
                    }
                }
                // Si pas de code apogée...
                if($lse->codListeElp === "ERROR"){
                    $result = false;
                }
                // Si la liste est sur un ELP qui n'est pas inséré...
                if(in_array($lse->codListeElp, $elpApogeeArray) === false){
                    $result = false;
                }
                // Si la liste n'a pas d'éléments à relier (liste vide)
                if(count($lse->listElementPedagogi->elementPedagogi) === 0){
                    $result = false;
                }
                // Si le code de la liste est déjà présent dans APOGEE
                if(in_array($lse->codListeElp, $lseInApogee, true)){
                    $result = false;
                }
                // Si les ELP de la liste ont des codes en double
                $countElpDuplicates = array_count_values(
                    array_map(
                        fn($elp) => $elp->codElp,
                        $lse->listElementPedagogi->elementPedagogi)
                    );
                foreach($countElpDuplicates as $count){
                    if($count > 1){
                        $result = false;
                    }
                }
                return $result;
            }
        );

        return $return;
    }

    /**
     * Génère un rapport Excel suivant le tableau de liste (LSE) fourni en paramètre.
     * @param array $lseArray Tableau contenant les listes (LSE)
     * @param string $exportTypeName Texte à faire figurer dans le nom du fichier d'export
     * @return void
     */
    private function generateSpreadsheetForLSE(array $lseArray, string $exportTypeName = ""): void
    {
        $headers = ['codListeELP', 'typListeELP', 'libelleCourt', 'libelleLong', 'tableauCodeELP'];
        // arrange data
        $lseArray = array_map(
            fn($lse) => [
                $lse->codListeElp, $lse->typListeElp, $lse->libCourtListeElp, $lse->libListeElp,
                implode(
                    ", ",
                    array_map(fn($elp) => $elp->codElp,
                        $lse->listElementPedagogi->elementPedagogi
                    )
                )
            ],
            $lseArray
        );
        // Write to spreadsheet
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->fromArray($headers);
        $activeWorksheet->fromArray($lseArray, startCell: "A2");
        // Write to file
        $now = new DateTime();
        $date = $now->format('d-m-Y_H-i-s');
        $filename = __DIR__ . "/../Service/Apogee/export/{$exportTypeName}-LSE-export-{$date}.xlsx";
        $this->filesystem->dumpFile($filename, "");
        $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
        $writer->save($filename);
    }

    /**
     * Génère les libellés courts et long pour une liste d'UE, raccrochée à un semestre
     * @param StructureSemestre $semestre Semestre contenant les UE
     * @param StructureParcours $dto DTO du parcours concerné
     * @return array Tableau avec les libellés accessibles via les clés 'libCourt' et 'libLong'
     */
    private function getLibellesForListeUE(StructureSemestre $semestre, StructureParcours $dto): array
    {
        $typeDiplome = $dto->parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() ?? "";
        $sigleFormation = $dto->parcours->getFormation()?->getSigle() ?? "";
        $sigleParcours = $dto->parcours->getSigle() ?? "";
        $libelleCourt = "LISTE UE S" . $semestre->semestre->getOrdre() . " "
        . $sigleFormation . " " . $sigleParcours;
        $libelleLong = "LISTE UE SEMESTRE " . $semestre->semestre->getOrdre() . " "
            . $typeDiplome . " " . $sigleFormation . " " . $sigleParcours;
        return [
            'libCourt' => $libelleCourt,
            'libLong' => $libelleLong
        ];
    }

    /**
     * Génère les libellés courts et long pour une liste d'EC
     * @param StructureUe $ue UE contenant la liste d'EC
     * @param StructureParcours $dto DTO du parcours concerné
     * @return array Tableau avec les libellés court et long accessibles via les clés 'libCourt' et 'libLong'
     */
    private function getLibellesForListeEC(StructureUe $ue, StructureParcours $dto): array
    {
        $typeDiplome = $dto->parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() ?? "";
        $sigleFormation = $dto->parcours->getFormation()?->getSigle() ?? "";
        $sigleParcours = $dto->parcours->getSigle() ?? "";
        $libelleCourt = "ECs " . $ue->display . " S" . $ue->ue->getSemestre()->getOrdre() . " "
            . $sigleFormation . " " . $sigleParcours;
        $libelleLong = "LISTE EC " . $ue->display . " S" . $ue->ue->getSemestre()->getOrdre()
            . " " . $typeDiplome . " " . $sigleFormation . " " . $sigleParcours;
        return [
            'libCourt' => $libelleCourt,
            'libLong' => $libelleLong
        ];
    }

    /**
     * Genère les libellés pour la liste comportant des EC enfants
     * @param StructureEc $ec EC contenant des enfants
     * @param StructureParcours $dto Parcours concerné
     * @return array Tableau avec les libellés court et long
     */
    private function getLibellesForEcWithChildren(StructureEc $ec, StructureParcours $dto): array
    {
        $typeDiplome = $dto->parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() ?? "";
        $sigleFormation = $dto->parcours->getFormation()?->getSigle() ?? "";
        $sigleParcours = $dto->parcours->getSigle() ?? "";
        $libelleCourt = $ec->elementConstitutif->getCode() . " " . $ec->elementConstitutif->getUe()?->display()
            . " S" . $ec->elementConstitutif->getUe()?->getSemestre()?->getOrdre() . " "
            . $typeDiplome . " " . $sigleFormation . " " . $sigleParcours;
        $libelleLong = $ec->elementConstitutif->getCode() . " " . $ec->elementConstitutif->getUe()?->display() . " S"
            . $ec->elementConstitutif->getUe()?->getSemestre()?->getOrdre() . " " . $sigleFormation . " ". $sigleParcours;
        return [
            'libCourt' => $libelleCourt,
            'libLong' => $libelleLong
        ];
    }

    /**
     * Génère les libellés pour une liste d'UE avec des enfants
     * @param StructureUe $ue UE avec des enfants
     * @param StructureParcours $dto DTO du parcours concerné
     * @return array Libellés long et court
     */
    private function getLibellesForUeWithChildren(StructureUe $ue, StructureParcours $dto): array
    {
        $typeDiplome = $dto->parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() ?? "";
        $sigleFormation = $dto->parcours->getFormation()->getSigle();
        $sigleParcours = $dto->parcours->getSigle() ?? "";
        $libelleLong = "LISTE " . $ue->ue->display() . " " . $ue->ue->getSemestre()->display()
            . " " . $typeDiplome . " " . $sigleFormation . " " . $sigleParcours;
        $libelleCourt = $ue->ue->display() . " " . $ue->ue->getSemestre()->display()
            . " " . $typeDiplome . " " . $sigleFormation . " " . $sigleParcours;
        return [
            'libCourt' => $libelleCourt,
            'libLong' => $libelleLong
        ];
    }

    /**
     * Filtre les ELP, en excluant ceux qui ont un code à 'null', 'ERROR' ou faisant plus de 8 caractères
     * @param array $elpArray Tableau d'ELP à filtrer
     * @return array Tableau d'ELP filtré
     */
    private function filterInvalidElpArray(array $elpArray) : array {
        return array_filter(
            $elpArray,
            fn($elp) => $elp->codElp !== "ERROR"
                        && $elp->codElp !== null
                        && mb_strlen($elp->codElp) <= 8
                        && !empty($elp->codElp)
        );
    }

    /**
     * Permet d'attribuer des codes de listes 'factices' pour les tests d'insertion
     * Retire les codes ELP invalides du TableauElementPedagogi de la liste
     * @param array $lseArray Tableau contenant les objets listes
     * @param int $idParcours ID du parcours
     * @return array Tableau de liste transformé
     */
    private function mapLseArrayObjectForTest(array $lseArray, int $idParcours) : array {
        $retour = $lseArray;
        // Codes LSE factices
        for($i = 0; $i < count($lseArray); $i++){
            $retour[$i]->codListeElp = "TST" . $idParcours . str_pad((string)($i + 1), 2, "0", STR_PAD_LEFT);
        }
        // Retirer les Codes ELP invalides (supérieur à 8 carac. ou 'ERROR')
        for($i = 0; $i < count($retour); $i++){
            $retour[$i]->listElementPedagogi->elementPedagogi = array_filter(
                $retour[$i]->listElementPedagogi->elementPedagogi,
                fn($elp) => mb_strlen($elp->codElp) <= 8 && $elp->codElp !== 'ERROR'
            );
        }

        return $retour;
    }

    private function filterAlreadyInsertedElpArray(array $elpArray): array
    {
        $dataElpApogee = json_decode(
            file_get_contents(
                __DIR__ . "/../Service/Apogee/data-test/" . self::$codElpApogeeDataTest)
        );
        return array_filter(
            $elpArray,
            fn($elp) => in_array($elp->codElp, $dataElpApogee, true) === false
        );
    }
}
