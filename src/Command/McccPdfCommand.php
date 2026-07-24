<?php

namespace App\Command;

use App\Classes\GetDateConseilComposante;
use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\TypeDiplome\But\Services\ButMccc;
use App\TypeDiplome\Licence\Services\LicenceMccc;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:mccc-pdf',
    description: "Génère les PDF contenant les descriptifs d'une formation ORéOF",
)]
class McccPdfCommand extends Command
{

    private EntityManagerInterface $entityManager;

    private HttpClientInterface $httpClient;

    private Filesystem $fs;

    private GetHistorique $getHistorique;

    // Types des formations
    private LicenceMccc $licenceMccc;
    private ButMccc $butMccc;

    public function __construct(
        private GetDateConseilComposante $getDateConseilComposante,
        EntityManagerInterface $entityManager,
        Filesystem $fs,
        HttpClientInterface $httpClient,
        GetHistorique $getHistorique,
        LicenceMccc $licenceMccc,
        ButMccc $butMccc
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->fs = $fs;
        $this->httpClient = $httpClient;
        $this->getHistorique = $getHistorique;

        $this->licenceMccc = $licenceMccc;
        $this->butMccc = $butMccc;
    }

    protected function configure(): void
    {
        $this
        ->addOption(
            name: 'generate-parcours',
            mode: InputOption::VALUE_REQUIRED,
            description: "Identifiant (PK) du parcours pour lequel on souhaite générer l'export des MCCC au format PDF"
        )->addOption(
            name: 'generate-all-parcours',
            mode: InputOption::VALUE_NONE,
            description: "Génère tous les PDF des MCCC pour tous les parcours validés ('publie')"
        )->addOption(
            name: 'generate-today-cfvu-valid',
            mode: InputOption::VALUE_NONE,
            description: "Génère les PDF des MCCC pour les parcours qui ont été validés le jour même ('valide_a_publier')"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '3500M');

        $io = new SymfonyStyle($input, $output);

        $generateParcours = $input->getOption("generate-parcours");

        $generateAllParcours = $input->getOption("generate-all-parcours");

        $generateTodayCfvuValid = $input->getOption('generate-today-cfvu-valid');

        if($generateParcours){
            $parcours = $this->entityManager->getRepository(Parcours::class)->findOneById($generateParcours);
            if($parcours){
                $io->writeln("Récupération du Parcours : [O.K]");
                $io->writeln("\n" . $parcours->getFormation()->getDisplayLong());

                $typeDiplomeParcours = $parcours->getFormation()->getTypeDiplome()->getLibelleCourt();

                $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                $anneeDpe = $dpeParcours->getCampagneCollecte();
                $dateConseil = $this->getDateConseilComposante->getDateConseilComposante($dpeParcours);
                $dateCfvu = $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_cfvu')?->getDate();

                if($typeDiplomeParcours !== "BUT"){
                    $pdfFull = $this->licenceMccc->exportPdfLicenceMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours : $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil
                    );
                    $pdfSimplifie = $this->licenceMccc->exportPdfLicenceMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil,
                        versionFull: false
                    );
                }
                elseif($typeDiplomeParcours === "BUT"){
                    $pdfFull = $this->butMccc->exportPdfbutMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil
                    );
                    $pdfSimplifie = $this->butMccc->exportPdfbutMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil,
                        versionFull: false
                    );
                }

                // $fileName = "MCCC - " . $anneeDpe->getAnnee() . " - " . $parcours->getFormation()->getSlug() ?? '---';
                // $fileName = "MCC-Parcours-{$parcours->getId()}-" . Tools::slug($parcours->getLibelle()) . "-" . $anneeDpe->getAnnee() . ".pdf";

                $fileName = "MCCC-Parcours-{$parcours->getId()}-{$anneeDpe->getAnnee()}";
                $now = (new DateTime())->format("d-m-Y_H-i-s");

                $this->fs->appendToFile(
                    __DIR__ . "/../../export/" . $now . "-" . $fileName . "-simplifie.pdf",
                    $pdfSimplifie
                );

                $this->fs->appendToFile(
                    __DIR__ . "/../../export/" . $now . "-" . $fileName . ".pdf",
                    $pdfFull
                );

                $io->success("Fichier généré avec succès.");

                return Command::SUCCESS;
            }
            else {
                $io->warning("L'identifiant du parcours semble incorrect, récupération impossible ({$generateParcours}).");
                return Command::FAILURE;
            }
        }
        elseif($generateAllParcours){

            $io->writeln("\nCommande pour générer les exports 'MCCC' au format PDF, de tous les parcours valides.\n");
            $anneeDpe = $this->entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => 1]);

            if ($io->ask("Année récupérée : [{$anneeDpe->getLibelle()}] - Souhaitez-vous continuer ? [Y/n]", 
                'n', fn($response) => $response === 'Y') === false
            ) {
                $io->warning("Annulation utilisateur. La commande de s'est pas exécutée.");
                return Command::SUCCESS;
            }

            $parcoursArray = $this->entityManager->getRepository(Parcours::class)->findAllParcoursForDpe($anneeDpe);

            $parcoursArray = array_filter(
                $parcoursArray,
                fn($p) =>
                $p->getDpeParcours()->last() instanceof DpeParcours &&
                (
                    (array_keys(
                        $p->getDpeParcours()->last()->getEtatValidation()
                    )[0] === 'publie'
                    && array_values(
                        $p->getDpeParcours()->last()->getEtatValidation()
                    )[0] === 1)
                    ||
                    (
                    array_keys(
                        $p->getDpeParcours()->last()->getEtatValidation()
                    )[0] === 'valide_a_publier'
                    && array_values(
                        $p->getDpeParcours()->last()->getEtatValidation()
                    )[0] === 1)
                    || ($p->getDpeParcours()->last()->getEtatValidation() === ['valide_cfvu' => 1])
                )
            );

            $nombreParcoursValides = count($parcoursArray);
            $io->writeln("Il y a {$nombreParcoursValides} parcours valides, à exporter.");
            $io->progressStart($nombreParcoursValides);

            foreach($parcoursArray as $parcours){
                $directoryPath = __DIR__ . "/../../public/mccc-export";

                if($this->fs->exists([$directoryPath . "/old-version"]) === false){
                    $this->fs->mkdir($directoryPath . "/old-version");
                }

                $dpeParcours = GetDpeParcours::getFromParcours($parcours);

                // $dateConseil = $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_conseil')?->getDate();
                $dateConseil = $this->getDateConseilComposante->getDateConseilComposante($dpeParcours);
                $dateCfvu = $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_cfvu')?->getDate();

                $fileNamePdf = "MCCC-Parcours-{$parcours->getId()}-{$anneeDpe->getAnnee()}.pdf";
                $fileNameSimplifiePdf = "MCCC-Parcours-{$parcours->getId()}-{$anneeDpe->getAnnee()}-simplifie.pdf";
                // Si les fichiers PDF de MCCC existent déjà (précédente validation)
                // On les sauvegarde dans un dossier 'old-version'
                $now = (new DateTime())->format("d-m-Y_H-i-s");
                if($this->fs->exists($directoryPath . "/" . $fileNamePdf)){
                    $this->fs->rename($directoryPath . "/" . $fileNamePdf, $directoryPath . "/old-version/{$now}-{$fileNamePdf}");
                }
                if($this->fs->exists($directoryPath . "/" . $fileNameSimplifiePdf)){
                    $this->fs->rename($directoryPath . "/" . $fileNameSimplifiePdf, $directoryPath . "/old-version/{$now}-{$fileNameSimplifiePdf}");
                }

                $typeDiplome = $parcours->getTypeDiplome()->getLibelleCourt();
                if($typeDiplome !== "BUT"){
                    $pdfFull = $this->licenceMccc->exportPdfLicenceMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil
                    );
                    $pdfSimplifie = $this->licenceMccc->exportPdfLicenceMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil,
                        versionFull: false
                    );
                    $this->fs->appendToFile(
                        $directoryPath . "/" . $fileNamePdf,
                        $pdfFull
                    );
                    $this->fs->appendToFile(
                        $directoryPath . "/" . $fileNameSimplifiePdf,
                        $pdfSimplifie
                    );
                }
                elseif($typeDiplome === "BUT"){
                    $pdfFull = $this->butMccc->exportPdfbutMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil
                    );
                    $pdfSimplifie = $this->butMccc->exportPdfbutMccc(
                        anneeUniversitaire: $anneeDpe,
                        parcours: $parcours,
                        dateCfvu: $dateCfvu,
                        dateConseil: $dateConseil,
                        versionFull: false
                    );
                    $this->fs->appendToFile(
                        $directoryPath . "/" . $fileNamePdf,
                        $pdfFull
                    );
                    $this->fs->appendToFile(
                        $directoryPath . "/" . $fileNameSimplifiePdf,
                        $pdfSimplifie
                    );
                }
                $io->progressAdvance();
            }
            $io->progressFinish();

            $io->success("Tous les exports MCCC au format PDF ont été générés.");

            return Command::SUCCESS;
        } elseif($generateTodayCfvuValid){
            // Récupération des parcours
            $dpe = $this->entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => 1]);
            $parcoursArray = $this->entityManager->getRepository(Parcours::class)->findAllParcoursForDpe($dpe);
            // Filtrage pour ne garder que ceux qui ont été validés aujourd'hui
            $parcoursArray = array_filter(
                $parcoursArray,
                function($parcours) {
                    $lastDpe = $parcours->getDpeParcours()->last();

                    $dateHistoriquePublication = $this->getHistorique
                        ->getHistoriqueParcoursLastStep($lastDpe, 'valide_cfvu')
                        ?->getDate();

                    $dateFormat = 'd-m-Y';
                    $today = new DateTime();

                    return $today->format($dateFormat) === $dateHistoriquePublication?->format($dateFormat);

                }
            );

            $nbParcours = count($parcoursArray);

            try {
                $io->writeln("Il y a {$nbParcours} parcours à traiter.");
                $io->progressStart($nbParcours);

                $directoryPath = __DIR__ . "/../../public/mccc-export";

                if($this->fs->exists([$directoryPath . "/old-version"]) === false){
                    $this->fs->mkdir($directoryPath . "/old-version");
                }

                foreach($parcoursArray as $p){
                    $fileNamePdf = "MCCC-Parcours-{$p->getId()}-{$dpe->getAnnee()}.pdf";
                    $fileNameSimplifiePdf = "MCCC-Parcours-{$p->getId()}-{$dpe->getAnnee()}-simplifie.pdf";
                    // Si les fichiers PDF de MCCC existent déjà (précédente validation)
                    // On les sauvegarde dans un dossier 'old-version'
                    $now = (new DateTime())->format("d-m-Y_H-i-s");
                    if($this->fs->exists($directoryPath . "/" . $fileNamePdf)){
                        $this->fs->rename($directoryPath . "/" . $fileNamePdf, $directoryPath . "/old-version/{$now}-{$fileNamePdf}");
                    }
                    if($this->fs->exists($directoryPath . "/" . $fileNameSimplifiePdf)){
                        $this->fs->rename($directoryPath . "/" . $fileNameSimplifiePdf, $directoryPath . "/old-version/{$now}-{$fileNameSimplifiePdf}");
                    }

                    $dpeParcours = GetDpeParcours::getFromParcours($p);
                    //$dateConseil = $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_conseil')?->getDate();
                    $dateConseil = $this->getDateConseilComposante->getDateConseilComposante($dpeParcours);
                    $dateCfvu = $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_cfvu')?->getDate();

                    // Sauvegarde de la version actuelle en PDF
                    $typeDiplome = $p->getTypeDiplome()->getLibelleCourt();
                    if($typeDiplome === "BUT"){
                        $pdfFull = $this->butMccc->exportPdfbutMccc(
                            anneeUniversitaire: $dpe,
                            parcours: $p,
                            dateCfvu: $dateCfvu,
                            dateConseil: $dateConseil
                        );
                        $pdfSimplifie = $this->butMccc->exportPdfbutMccc(
                            anneeUniversitaire: $dpe,
                            parcours: $p,
                            dateCfvu: $dateCfvu,
                            dateConseil: $dateConseil,
                            versionFull: false
                        );
                        $this->fs->appendToFile($directoryPath . "/" . $fileNamePdf, $pdfFull);
                        $this->fs->appendToFile($directoryPath . "/" . $fileNameSimplifiePdf, $pdfSimplifie);
                    }
                    elseif($typeDiplome !== "BUT"){
                        $pdfFull = $this->licenceMccc->exportPdfLicenceMccc(
                            anneeUniversitaire: $dpe,
                            parcours: $p,
                            dateCfvu: $dateCfvu,
                            dateConseil: $dateConseil
                        );
                        $pdfSimplifie = $this->licenceMccc->exportPdfLicenceMccc(
                            anneeUniversitaire: $dpe,
                            parcours: $p,
                            dateCfvu: $dateCfvu,
                            dateConseil: $dateConseil,
                            versionFull: false
                        );
                        $this->fs->appendToFile($directoryPath . "/" . $fileNamePdf, $pdfFull);
                        $this->fs->appendToFile($directoryPath . "/" . $fileNameSimplifiePdf, $pdfSimplifie);
                    }
                    $io->progressAdvance();
                }
                $io->progressFinish();
                $io->success("Tous les parcours ont été traités avec succès.");

                $dateLog = (new DateTime())->format("d-m-Y_H-i-s");

                $logTxt = "[{$dateLog}] Les MCCC au format PDF ont été générés. (Nombre de parcours : {$nbParcours})\n";
                $this->fs->appendToFile(__DIR__ . "/../../mccc-export/success_log/global_save_mccc_success.log", $logTxt);

                return Command::SUCCESS;

            } catch (Exception $e) {
                $io->error("Une erreur est survenue : \n{$e->getMessage()}");

                $now = (new DateTime())->format('d-m-Y_H-i-s');
                $logTxt = "[{$now}] Une erreur est survenue lors de la génération des MCCC au format PDF.\nMessage : {$e->getMessage()}\n";

                $this->fs->appendToFile(__DIR__ . "/../../mccc-export/error_log/global_save_mccc_error.log", $logTxt);

                return Command::FAILURE;
            }

        }

        return Command::SUCCESS;
    }
}
