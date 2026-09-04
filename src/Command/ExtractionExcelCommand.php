<?php

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Service\TypeDiplomeResolver;
use App\Service\VersioningParcours;
use App\Service\VersioningStructure;
use App\Utils\LogValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:extraction-excel',
    description: 'Extraction Excel de différentes requêtes',
)]
class ExtractionExcelCommand extends Command
{
    private $outputArray = [];

    public function __construct(
        private EntityManagerInterface $em,
        private VersioningParcours $versioningParcours,
        private TypeDiplomeResolver $typeD
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'output',
            shortcut: null,
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Comment le rapport est exporté. [raw, excel] - Sortie standard ou fichier xlsx.'
        )->addOption(
            name: 'has-been-modified', 
            shortcut: null, 
            mode: InputOption::VALUE_NONE, 
            description: 'Récupère les parcours qui ont été modifiés depuis la dernière version JSON valide.'
        );     
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $output = $input->getOption('output');

        $hasBeenModified = $input->getOption('has-been-modified');

        if(!in_array($output, ['raw', 'excel'], true)) {
            $io->warning("Le format du rapport doit être défini. --output=[raw, excel] - Sortie standard ou fichier xlsx");
            return Command::INVALID;
        }

        if($hasBeenModified) {
            $this->outputArray = [
                'no_version_available' => [],
                'errors' => []
            ];

            $campagneDefaut = $this->em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => 1]);
            $parcoursArray = $this->em->getRepository(Parcours::class)->findAllParcoursForDpe($campagneDefaut);

            $io->progressStart(count($parcoursArray));
            foreach($parcoursArray as $p){
                try {
                    $typeDiplome = $this->typeD->get($p->getFormation()->getTypeDiplome());
                    $v = $typeDiplome->getExportVersion($campagneDefaut, $p, withLogs: true);
                    if ($v === false) {
                        $this->outputArray['no_version_available'][] = ['parcours_id' => $p->getId()];
                    }
                } catch(\Exception $e) {
                    $this->outputArray['errors'][] = [
                        'parcours_id' => $p->getId(),
                        'log' => $e->getFile() . ' - Line : ' . $e->getLine() . ' - ' . $e->getMessage()
                    ];
                }
                $io->progressAdvance();
            }

            $io->progressFinish();
        }

        if($output === 'raw') {
            dump(LogValue::$logArray);
            dump($this->outputArray);
            $io->success("La commande s'est exécutée correctement.");
            return Command::SUCCESS;
        }

        if($output === 'excel') {
            $io->success('Export Excel généré !');
            return Command::SUCCESS;
        }
        

        return Command::SUCCESS;
    }

    private function isDiffArrayDifferent(array $diffArray) {
        $isDifferent = false;
        foreach($diffArray as $d) {
            if($d->isDifferent()) {
                $isDifferent = true;
            }
        }

        return $isDifferent;
    }

    private function isEcMcccDifferent(array $mcccDiff, bool $isBut) {
        return array_reduce($mcccDiff, function($previous, $current) use ($isBut) {
            if($isBut){
                return $this->isDiffArrayDifferent($current) || $previous;
            }
            
        }, false);
    }

    private function isParcoursDifferentFromLastVersion(array $parcoursDiffStructure, bool $isBut) {
        $logOutput = [
            'is_different' => false,
            'error' => false,
            'element_libelle_diff' => []
        ];

        if(!isset($parcoursDiffStructure['semestres'])) {
            $logOutput['error'] = true;
            $logOutput['element_libelle_diff'][] = 'Aucun semestre disponible';
        }

        $parcoursDifferent = false;
        foreach($parcoursDiffStructure['semestres'] ?? [] as $idx => $sem) {
            if(!isset($sem['ues'])) {
                $logOutput['error'] = true;
                $logOutput['element_libelle_diff'][] = "Semestre $idx - Aucune UE pour ce semestre";
            }
            foreach($sem['ues'] ?? [] as $ue) {
                if($this->isDiffArrayDifferent($ue['heuresEctsUe'])) {
                    $logOutput['element_libelle_diff'][] = $ue['display']->new;
                    $parcoursDifferent = true;
                }
                foreach($ue['elementConstitutifs'] ?? [] as $ec){
                    if($this->isDiffArrayDifferent($ec['heuresEctsEc'])) {
                        $logOutput['element_libelle_diff'][] = $ec['code']->new . ' - Heures';
                        $parcoursDifferent = true;
                    }
                    if($this->isEcMcccDifferent($ec['mcccs'], $isBut)){ 
                        $logOutput['element_libelle_diff'][] = $ec['code']->new . ' - MCCC';
                        $parcoursDifferent = true;
                    }
                    foreach($ec['ecEnfants'] ?? [] as $ecEnfant) {
                        if($this->isDiffArrayDifferent($ecEnfant['heuresEctsEc'])) {
                            $logOutput['element_libelle_diff'][] = $ecEnfant['code']->new;
                            $parcoursDifferent = true;
                        }
                        if($this->isEcMcccDifferent($ecEnfant['mcccs'], $isBut)) {
                            $logOutput['element_libelle_diff'][] = $ecEnfant['code']->new . ' - MCCC';
                            $parcoursDifferent = true;
                        }   
                    }
                }
                foreach($ue['uesEnfants'] ?? [] as $ueE) {
                    if($this->isDiffArrayDifferent($ueE['heuresEctsUe'])) {
                        $logOutput['element_libelle_diff'][] = $ueE['display']->new;
                        $parcoursDifferent = true;
                    }
                    foreach($ueE['elementConstitutifs'] ?? [] as $ueEnfantEc) {
                        if($this->isDiffArrayDifferent($ueEnfantEc['heuresEctsEc'])) {
                            $logOutput['element_libelle_diff'][] = $ueEnfantEc['code']->new;
                            $parcoursDifferent = true;
                        }
                        if($this->isEcMcccDifferent($ueEnfantEc['mcccs'], $isBut)){
                            $logOutput['element_libelle_diff'][] = $ueEnfantEc['code']->new . ' - MCCC';
                            $parcoursDifferent = true;
                        }
                        foreach($ueEnfantEc['ecEnfants'] ?? [] as $ueEnfantEcEnfant) {
                            if($this->isDiffArrayDifferent($ueEnfantEcEnfant['heuresEctsEc'])) {
                                $logOutput['element_libelle_diff'][] = $ueEnfantEcEnfant['code']->new;
                                $parcoursDifferent = true;
                            }
                            if($this->isEcMcccDifferent($ueEnfantEcEnfant['mcccs'], $isBut)){
                                $logOutput['element_libelle_diff'][] = $ueEnfantEcEnfant['code']->new . ' - MCCC';
                                $parcoursDifferent = true;
                            }
                        }
                    } 
                }
            }
        }

        if($parcoursDifferent) {
            $logOutput['is_different'] = true;
        }

        return $logOutput;
    }
}
