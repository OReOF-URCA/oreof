<?php

namespace App\Service;

use App\Classes\MyGotenbergPdf;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportGeneriqueFicheMatiere {

    public function __construct(
        private EntityManagerInterface $em,
        private MyGotenbergPdf $myPdf,
        private Filesystem $fs,
    ){

    }

    public function generatePdf(Request $request) {
        [
            $fieldValueArray, 
            $parcoursData, 
            $campagneCollecte, 
            $withFieldSorting,
            $withDefaultHeader,
            $isPredefinedTemplate,
            $predefinedTemplateName
        ] = $this->checkExportGeneriqueData($request);

        $this->checkFieldsAreSupported($fieldValueArray);  

        // On trie les colonnes dans un certain ordre
        if($withFieldSorting){
            usort($fieldValueArray, 
                fn($f1, $f2) => $this->getFieldOrderForExportGenerique()[$f1] 
                <=> $this->getFieldOrderForExportGenerique()[$f2]
            );
        }

        $parcoursData = array_map(fn($id) => $id['parcours_id'], $parcoursData);

        $finalData = $this->em->getRepository(FicheMatiere::class)
            ->findByParcoursRangeForExport($parcoursData, $campagneCollecte);

        $dataStructure = [];
        foreach($finalData as $fiche){
            $dataStructure[] = [
                'libelleLong' => $fiche?->getLibelle(),
                'valueExport' => [
                        ...array_map(
                            fn($field) => [
                                ...$this->mapFicheMatiereExportWithValues($field, $fiche, 'pdf'), 
                                'fieldName' => $field
                            ]
                            , $fieldValueArray
                        )
                    ],
                'headersSectionPdf' => []
            ];
        }

        $dateNow = new DateTime();
        $dateFormat = $dateNow->format("d-m-Y_H-i");
        $filename = "Export-Generique-Fiche-Matiere-{$dateFormat}";

        $pdfContent = $this->myPdf->render('export/export_parcours_generique.html.twig', [
            'parcoursData' => $dataStructure,
            'titre' => 'Export des données des fiches matières'
        ], $filename);

        $tmpFile = $this->fs->tempnam(__DIR__ . "/../../public/temp/", 'export_generique');
        $this->fs->appendToFile($tmpFile, $pdfContent);
        $exportContent = file_get_contents($tmpFile);
        $this->fs->remove($tmpFile);

        return [$exportContent, $filename];
    }

    public function generateXlsxSpreadsheet(
        Request $request
    ){
        [
            $fieldValueArray, 
            $parcoursData, 
            $campagneCollecte, 
            $withFieldSorting,
            $withDefaultHeader,
            $isPredefinedTemplate,
            $predefinedTemplateName
        ] = $this->checkExportGeneriqueData($request);
        $this->checkFieldsAreSupported($fieldValueArray);

        // On trie les colonnes dans un certain ordre
        if($withFieldSorting){
            usort($fieldValueArray, 
                fn($f1, $f2) => $this->getFieldOrderForExportGenerique()[$f1] 
                <=> $this->getFieldOrderForExportGenerique()[$f2]
            );
        }

        $parcoursData = array_map(fn($id) => $id['parcours_id'], $parcoursData);

        $finalData = $this->em->getRepository(FicheMatiere::class)
            ->findByParcoursRangeForExport($parcoursData, $campagneCollecte);

        $finalData = array_map(
            function($fiche) use ($fieldValueArray){
                return array_merge(...array_map(
                    function($field) use ($fiche) {
                        if($this->mapFicheMatiereExportWithValues($field, $fiche)['type'] === 'full_block'){
                            return array_merge(...array_map(
                                function($fieldValue){
                                    if($fieldValue['type'] ?? 'none' === 'none'){
                                        return [$fieldValue['content']];
                                    }
                                }  
                                , $this->mapFicheMatiereExportWithValues($field, $fiche)['value']
                            ));
                        }
                        else {
                            return [$this->mapFicheMatiereExportWithValues($field, $fiche)['value']];
                        }
                    } 
                    , $fieldValueArray
                ));
            }, $finalData
        );

        $headersExcel = array_map(function ($field){
            return [
                $this->mapFicheMatiereExportWithValues($field, null, 'xlsx')['libelle']
            ];
        }, $fieldValueArray);

        $headersExcel = array_merge(...$headersExcel);

        $columns = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'
        ];

        $spreadSheet = new Spreadsheet();
        $activeWS = $spreadSheet->getActiveSheet();
        $activeWS->fromArray($headersExcel);
        $activeWS->fromArray($finalData, startCell: 'A2');

        foreach($columns as $col){
            $activeWS->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadSheet);
        $now = new DateTime();
        $dateFormat = $now->format('d-m-Y_H-i');

        $filename = "Export-Generique-Fiche-Matiere-{$dateFormat}";

        $tmpFile = $this->fs->tempnam(__DIR__ . "/../../public/temp/", 'export_generique');
        $writer->save($tmpFile);
        $fileContent = file_get_contents($tmpFile);
        $this->fs->remove($tmpFile);

        return [$fileContent, $filename];
    }

    private function checkExportGeneriqueData(
        Request $request
    ){
        $isPredefinedTemplate = $request->query->get('predefinedTemplate', 'false');
        $isPredefinedTemplate = $isPredefinedTemplate === 'true' ? true : false;
        $predefinedTemplateName = null;
        if($isPredefinedTemplate){
            $predefinedTemplateName = $request->query->get('templateName', null);
        }

        $withFieldSorting = $request->query->get('withFieldSorting', "true");
        $withFieldSorting = $withFieldSorting === 'false' ? false : true;

        $withDefaultHeader = $request->query->get('withHeader', 'true');
        $withDefaultHeader = $withDefaultHeader === 'false' ? false : true;

        $campagneCollecte = $request->query->get('campagne', 2);
        $campagneCollecte = $this->em->getRepository(CampagneCollecte::class)
            ->findOneById($campagneCollecte)
            ?? $this->em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);

        
        $parcoursIdArray = $request->query->all()['id'] ?? [];
        $searchParam = [];
        if(isset($parcoursIdArray[0]) && $parcoursIdArray[0] === 'all'){
            $searchParam = 'all';
        }
        else {
            $searchParam = array_map(fn($id) => (int)$id, $parcoursIdArray);
        }

        $parcoursData = $this->em->getRepository(Parcours::class)
            ->findByIdAndCampagneCollecte($searchParam, $campagneCollecte->getId());

        if(count($parcoursData) < 1){
            throw new NotFoundHttpException('Aucun parcours sélectionné.');
        }

        $fieldValueArray = $request->query->all()['val'] ?? [];
        // Vérification sur les champs demandés (non vide)
        if(count($fieldValueArray) === 0 && $isPredefinedTemplate === false){
            throw new NotFoundHttpException('Aucun champ précisé.');
        }

        if($isPredefinedTemplate && $predefinedTemplateName === null){
            throw new NotFoundHttpException("Aucun nom précisé pour le template prédéfini.");
        }

        return [
            $fieldValueArray,
            $parcoursData,
            $campagneCollecte,
            $withFieldSorting,
            $withDefaultHeader,
            $isPredefinedTemplate,
            $predefinedTemplateName
        ];
    }

    private function mapFicheMatiereExportWithValues(
        string $fieldValue,
        ?FicheMatiere $ficheMatiere,
        string $exportType = 'pdf'
    ){
        switch($fieldValue){
            case 'fmId':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Id": "",
                    'value' => [
                        [
                            'libelle' => "Id",  
                            'content' => $ficheMatiere?->getId() ?? ""
                        ]
                    ]
                ];
                break;
            case 'fmLibelle':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Fiche EC/matière" : "",
                    'value' => [
                        [
                            'libelle' => "Fiche EC/matière",
                            'content' => $ficheMatiere?->getLibelle() ?? ""
                        ]
                    ]
                ];
                break;
            case 'fmReferent':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Référent" : "",
                    'value' => [
                        [
                            'libelle' => "Référent",
                            'content' => $ficheMatiere?->getResponsableFicheMatiere()?->getDisplay() ?? ""
                        ]
                    ]
                ];
                break;
            case 'fmIsComplet':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Complet ?" : "",
                    'value' => [
                        [
                            'libelle' => 'Complet ?',
                            'content' => $ficheMatiere?->remplissageBrut()?->isFull() ? 'Complet' : 'Incomplet'
                        ]
                    ]
                ];
                break;
            case 'fmNbUtilisee':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Utilisée ?" : "",
                    'value' => [
                        [
                            'libelle' => "Utilisée ?",
                            'content' => $ficheMatiere?->getElementConstitutifs()?->count() ?? ""
                        ]
                    ]
                ];
                break;
            case 'fmParcoursPorteur':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Parcours porteur" : "",
                    'value' => [
                        [
                            'libelle' => "Parcours porteur",
                            'content' => $ficheMatiere?->isHorsDiplome() 
                            ? 'Hors diplôme'
                            : $ficheMatiere?->getParcours()?->getLibelle() ?? ""
                        ]
                    ]
                ];
                break;
            case 'fmFormation':
                return [
                    'type' => 'full_block',
                    'libelle' => $exportType === 'xlsx' ? "Formation" : "",
                    'value' => [
                        [
                            'libelle' => 'Formation',
                            'content' => $ficheMatiere?->isHorsDiplome()
                            ? 'Hors diplôme'
                            : $ficheMatiere?->getParcours()?->getFormation()?->getDisplayLong() ?? ""
                        ]
                    ]
                ];
                break;
        }
    }

    private function getFieldOrderForExportGenerique(){
        return [
            'fmId' => 1,
            'fmLibelle' => 2,
            'fmReferent' => 3,
            'fmIsComplet' => 4,
            'fmNbUtilisee' => 5,
            'fmParcoursPorteur' => 6,
            'fmFormation' => 7,
        ];
    }

    private function checkFieldsAreSupported(array $fieldValueArray){
        // Vérifications sur les champs demandés (les champs sont corrects)
        $fieldsAreCompatible = array_reduce(
            $fieldValueArray,
            fn($previous, $f) => $previous && array_key_exists($f, $this->getFieldOrderForExportGenerique()),
            true
        );
        if(!$fieldsAreCompatible){
            throw new NotFoundHttpException("Un des champs demandés n'est pas pris en charge.");
        }
    }
}