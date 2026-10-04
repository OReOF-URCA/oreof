<?php

namespace App\Command;

use App\Classes\GetDateConseilComposante;
use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'app:publish-valid-parcours',
    description: 'Add a short description for your command',
)]
class PublishValidParcoursCommand extends Command
{

    private EntityManagerInterface $entityManager;

    private WorkflowInterface $dpeParcoursWorkflow;

    private GetHistorique $getHistorique;

    private Filesystem $filesystem;

    public function __construct(
        private GetDateConseilComposante $getDateConseilComposante,
        EntityManagerInterface $entityManager,
        WorkflowInterface $dpeParcoursWorkflow,
        GetHistorique $getHistorique,
        Filesystem $filesystem,
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->dpeParcoursWorkflow = $dpeParcoursWorkflow;
        $this->getHistorique = $getHistorique;
        $this->filesystem = $filesystem;
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'date-is-today',
            mode: InputOption::VALUE_NONE,
            description: "Met à jour le workflow et l'historique pour les parcours à publier aujourd'hui"
        )->addOption(
            name: 'export-missing',
            mode: InputOption::VALUE_REQUIRED,
            description: "Exporte dans un fichier CSV les parcours qui ne sont pas encore valides"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2048M');

        $io = new SymfonyStyle($input, $output);

        $dateIsToday = $input->getOption('date-is-today');

        $exportMissing = $input->getOption('export-missing');

        if ($dateIsToday) {
            $io->writeln("Récupération des parcours à publier aujourd'hui...");

            $dpe = $this->entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);

            $parcoursArray = $this->entityManager->getRepository(Parcours::class)
                ->findAllParcoursForDpe($dpe);

            $parcoursArray = array_filter(
                $parcoursArray,
                fn($p) => $p->getDpeParcours()->last() instanceof DpeParcours
                    && $p->getDpeParcours()->last()->getEtatValidation() === ["valide_a_publier" => 1]
            );

            // Récupération des parcours à publier aujourd'hui
            $parcoursArray = array_filter(
                $parcoursArray,
                function ($p) {
                    $today = new DateTime('now');
                    $dateHistoriqueValide = $this->getHistorique
                        ->getHistoriqueParcoursLastStep(
                            $p->getDpeParcours()->last(), 'valide_cfvu'
                        )?->getDate();

                    $dateFormat = "d-m-Y";

                    return $dateHistoriqueValide?->format($dateFormat) === $today->format($dateFormat);
                }
            );

            $nombreParcours = count($parcoursArray);
            $io->writeln("Il y a {$nombreParcours} parcours à traiter.");

            try {
                if ($nombreParcours > 0) {
                    // Récupérer l'utilisateur admin
                    $adminUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'admin']);

                    // Pour chacun d'entre eux, créer un historique 'publication' et faire avancer le workflow
                    foreach($parcoursArray as $p){
                        $now = new DateTime();
                        $historiqueParcours = new HistoriqueParcours();
                        $historiqueParcours->setParcours($p);
                        $historiqueParcours->setDate($now);
                        $historiqueParcours->setUser($adminUser);
                        $historiqueParcours->setEtape('valide_a_publier');
                        $historiqueParcours->setEtat('valide');

                        $dpeParcours = $p->getDpeParcours()->last();
                        $this->dpeParcoursWorkflow->apply($dpeParcours, 'publier');

                        $this->entityManager->persist($historiqueParcours);
                    }

                    $this->entityManager->flush();
                }

                $now = (new DateTime())->format('d-m-Y_H-i-s');
                $logTxt = "[{$now}] La publication des parcours s'est bien déroulée. (Total : {$nombreParcours})\n";
                $this->filesystem->appendToFile(__DIR__ . "/../../publication-cron/publication-cron.success.log", $logTxt);

                return Command::SUCCESS;

            } catch (Exception $e) {
                $now = (new DateTime())->format('d-m-Y_H-i-s');
                $logTxt = "[{$now}] La publication des parcours a rencontré une erreur.\nMessage : {$e->getMessage()}\n";
                $this->filesystem->appendToFile(__DIR__ . "/../../publication-cron/publication-cron.error.log", $logTxt);
                $io->writeln("Le script a rencontré une erreur. Le fichier de log a été généré.");

                return Command::FAILURE;
            }
        }
        if($exportMissing){
            if(in_array($exportMissing, ['not-valid', 'no-cfvu-date', 'no-conseil-date']) === false){
                $io->warning("Option invalide : ({$exportMissing}) - Options disponibles : ['not-valid', 'no-cfvu-date', 'no-conseil-date']");
                return Command::INVALID;
            }
            $dpe = $this->entityManager
                ->getRepository(CampagneCollecte::class)
                ->findOneBy(['defaut' => true]);

            $parcoursArray = $this->entityManager
                ->getRepository(Parcours::class)
                ->findAllParcoursForDpe($dpe);

            if($exportMissing === "not-valid"){
                // On garde ceux qui ne sont pas à l'état "valide_a_publier"
                $parcoursArray = array_filter(
                    $parcoursArray,
                    function($p) {
                        $lastDpe = $p->getDpeParcours()->last();
                        return $lastDpe instanceof DpeParcours
                            && $lastDpe->getEtatValidation() !== ["valide_a_publier" => 1];
                    }
                );
                $exportName = "non-valides";
            }
            elseif($exportMissing === "no-cfvu-date") {
                $parcoursArray = array_filter(
                    $parcoursArray,
                    function($p) {
                        $dpeParcours = GetDpeParcours::getFromParcours($p);
                        $historiqueCfvu = $this->getHistorique
                            ->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_cfvu')
                            ?->getDate() === null;
                        $isValide = $p->getDpeParcours()->last() instanceof DpeParcours
                        && ($p->getDpeParcours()->last()->getEtatValidation() === ["valide_a_publier" => 1]
                            || $p->getDpeParcours()->last()->getEtatValidation() === ["publie" => 1]
                        );
                        return $historiqueCfvu && $isValide;
                    }
                );
                $exportName = "valides-aucune-date-cfvu";
            }
            elseif($exportMissing === "no-conseil-date"){
                $parcoursArray = array_filter(
                    $parcoursArray,
                    function($p) {
                        $dpeParcours = GetDpeParcours::getFromParcours($p);
                        $historiqueConseil = $this->getDateConseilComposante->getDateConseilComposante($dpeParcours) === null;

                        // $historiqueConseil = $this->getHistorique
                        // ->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_conseil')
                        //?->getDate() === null;
                        $isValideAPublier = $p
                                ->getDpeParcours()->last() instanceof DpeParcours
                            && ($p->getDpeParcours()->last()->getEtatValidation() === ["valide_a_publier" => 1]
                                || $p->getDpeParcours()->last()->getEtatValidation() === ["publie" => 1]
                            );
                        return $historiqueConseil && $isValideAPublier;
                    }
                );
                $exportName = "valides-aucune-date-de-conseil";
            }

            $nombreParcours = count($parcoursArray);
            $io->writeln("Il y a {$nombreParcours} parcours qui ne sont pas encore validés");

            $exportExcel = array_map(function($parcours) {
                return [
                    $parcours->getFormation()->getDisplayLong() . " " . $parcours->getDisplay(),
                    $parcours->getSigle() ?? '---',
                    $parcours->getId(),
                    $parcours->getDpeParcours()->last()->getEtatValidation()
                    ? array_keys($parcours->getDpeParcours()->last()->getEtatValidation())[0]
                    : "---"
                ];
            }, $parcoursArray);

            $exportExcel = [['Libellé du Parcours', 'Sigle', 'Identifiant', "État"], ...$exportExcel];

            $io->writeln("Export Excel en cours...");

            $spreadsheet = new Spreadsheet();
            $activeWorksheet = $spreadsheet->getActiveSheet();
            $activeWorksheet->fromArray($exportExcel);

            $now = (new DateTime())->format('d-m-Y_H-i-s');
            $fileName = __DIR__ . "/../../export/{$now}-Parcours-{$exportName}.xlsx";

            $writer = new Xlsx($spreadsheet);
            $writer->save($fileName);

            $io->success('Export généré !');

            return Command::SUCCESS;
        }

        $io->warning("Aucune option spécifiée (--date-is-today ou --export-missing=<type>).");
        return Command::INVALID;
    }
}
