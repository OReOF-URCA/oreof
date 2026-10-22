<?php

namespace App\Service;

use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Classes\MyGotenbergPdf;
use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\CampagneCollecte;
use App\Entity\Contact;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportGeneriqueParcours {

    public function __construct(
        private EntityManagerInterface $em,
        private MyGotenbergPdf $myPdf,
        private Filesystem $fs,
        private GetHistorique $getHistorique,
        private TypeDiplomeResolver $typeDiplomeR
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

        if($isPredefinedTemplate){
            $this->checkPredefinedTemplateExists($predefinedTemplateName);
            if($this->checkCanExportData($predefinedTemplateName, 'pdf') === false){
                throw new NotFoundHttpException("Ce type d'export n'est pas disponible");
            }
            return $this->getPredefinedTemplate($predefinedTemplateName, $parcoursData, 'pdf');
        }

        // On trie les colonnes dans un certain ordre
        if($withFieldSorting){
            usort($fieldValueArray, 
                fn($f1, $f2) => $this->getFieldOrderForExportGenerique()[$f1] 
                <=> $this->getFieldOrderForExportGenerique()[$f2]
            );
        }

        $parcoursData = array_map(fn($p) => $p['parcours_id'], $parcoursData);

        $dataStructure = [];
        foreach($parcoursData as $parcoursId){
            $parcours = $this->em->getRepository(Parcours::class)->findOneById($parcoursId);

            $headersSectionPdf = [];
            foreach($fieldValueArray as $f){
                if(in_array($f, ['objectifsFormation']) && isset($headersSectionPdf[$f]) === false){
                    $headersSectionPdf[$f] = [
                        'libelle' => 'presentationFormationHeader',
                        'content' => "Présentation formation {$parcours->getFormation()->getDisplay()}"
                    ];
                }
                elseif(in_array($f, [
                    'objectifsParcours',
                    'resultatsAttendusParcours',
                    'rythmeFormation',
                    'organisationParcours'
                    ]
                ) && isset($headersSectionPdf[$f]) === false){
                    $headersSectionPdf[$f] = [
                        'libelle' => 'presentationParcoursHeader',
                        'content' => "Présentation du parcours {$parcours->getLibelle()}",
                    ];
                }
                elseif(in_array($f, [
                    'competencesAcquises'
                ]) && isset($headersSectionPdf[$f]) === false){
                    $headersSectionPdf[$f] = [
                        'libelle' => 'compentencesAcquisesHeader',
                        'content' => "Compétences Acquises"
                    ];
                }
                elseif(in_array($f, [
                    'poursuiteEtudes',
                    'debouchesParcours',
                    'codesRome'
                ]) && isset($headersSectionPdf[$f]) === false) {
                    $headersSectionPdf[$f] = [
                        'libelle' => 'etApresHeader',
                        'content' => "Et après..."
                    ];
                }else if(in_array($f, [
                    'modalitesEnseignement'
                ]) && isset($headersSectionPdf[$f]) === false){
                    $headersSectionPdf[$f] = [
                        'libelle' => 'descriptifParcoursHeader',
                        'content' => 'Descriptif du parcours'
                    ];
                }
            }

            $dataStructure[] = [
                'idParcours' => $parcours->getId(),
                'libelleLong' => $parcours->getFormation()->getDisplayLong() . " " . $parcours->getDisplay(),
                'valueExport' => [...array_map(
                        fn($field) => [...$this->mapParcoursExportWithValues($field, $parcours), 'fieldName' => $field],
                        $fieldValueArray
                    )
                ],
                'headersSectionPdf' => $headersSectionPdf
            ];
        }

        $dateNow = new DateTime();
        $dateFormat = $dateNow->format("d-m-Y_H-i");

        $fileName = "Export-Generique-Parcours-{$dateFormat}";
        
        $tmpFile = $this->fs->tempnam(__DIR__ . '/../../public/temp/', 'export_generique');
        $pdfContent = $this->myPdf->render('export/export_parcours_generique.html.twig', [
            'parcoursData' => $dataStructure,
            'titre' => 'Export des données de parcours'
        ], $fileName);

        $this->fs->appendToFile($tmpFile, $pdfContent);
        $exportContent = file_get_contents($tmpFile);
        $this->fs->remove($tmpFile);

        return [$exportContent, $fileName];
    }

    public function generateXlsxSpreadsheet(
        Request $request
    ) {

        [
            $fieldValueArray, 
            $parcoursData, 
            $campagneCollecte, 
            $withFieldSorting,
            $withDefaultHeader,
            $isPredefinedTemplate,
            $predefinedTemplateName
        ] = $this->checkExportGeneriqueData($request);

        if($isPredefinedTemplate){
            $this->checkPredefinedTemplateExists($predefinedTemplateName);
            if($this->checkCanExportData($predefinedTemplateName, 'xlsx') === false){
                throw new NotFoundHttpException("Ce type d'export n'est pas disponible");
            }
            return $this->getPredefinedTemplate($predefinedTemplateName, $parcoursData, 'xlsx');
        }

        $this->checkFieldsAreSupported($fieldValueArray);

        // On trie les colonnes dans un certain ordre
        if($withFieldSorting){
            usort($fieldValueArray, 
                fn($f1, $f2) => $this->getFieldOrderForExportGenerique()[$f1] 
                <=> $this->getFieldOrderForExportGenerique()[$f2]
            );
        }

        $parcoursData = array_map(fn($p) => $p['parcours_id'], $parcoursData);

        /**
         * Variables pour les header
         */
        $nbMaxComposanteInscription = $this->em->getRepository(Parcours::class)
            ->findMaxNumberOfComposanteForRange($parcoursData, $campagneCollecte->getId()) ?? 1;

        $headerDeBase = ["Type de diplôme", "Intitulé de la formation", "Intitulé du parcours"];
        $headersExcel = array_map(
            function($field) use ($nbMaxComposanteInscription){
                if($this->mapParcoursExportWithValues($field, null)['type'] === 'longtext'){
                    return [$this->mapParcoursExportWithValues($field, null)['libelle']];
                }
                elseif($this->mapParcoursExportWithValues($field, null)['type'] === 'list'){
                    return array_map(fn($v) => $v['libelle'], $this->mapParcoursExportWithValues($field, null)['value']);
                }
                elseif($this->mapParcoursExportWithValues($field, null)['type'] === 'nested_list'){
                    return [$this->mapParcoursExportWithValues($field, null)['libelle']];
                }
                elseif (isset($this->mapParcoursExportWithValues($field, null)['header_content'])) {
                    return [...$this->mapParcoursExportWithValues($field, null)['header_content']];
                }
                elseif($this->mapParcoursExportWithValues($field, null)['type'] === 'full_block'){
                    return array_merge(...array_map(
                        function($fieldBlock) use ($nbMaxComposanteInscription) {
                            $type = $fieldBlock['type'] ?? "none";
                            if($type === 'nested_content_array'){
                                if(($fieldBlock['type_value'] ?? "none") === "multipleComposanteInscription"){
                                    $return = [];
                                    for($i = 0; $i < $nbMaxComposanteInscription; $i++){
                                        $return[] = $fieldBlock['header_content'];
                                    }
                                    return array_merge(...$return);
                                }
                            }
                            else {
                                return [$fieldBlock['libelle']];
                            }
                        }
                        , $this->mapParcoursExportWithValues($field, null)['value']
                    ));
                }
                elseif($this->mapParcoursExportWithValues($field, null)['type'] === 'array_list'){
                    return [$this->mapParcoursExportWithValues($field, null)['libelle']];
                }
            }
            , $fieldValueArray
        );

        if($withDefaultHeader){
            $headersExcel = array_merge($headerDeBase, ...$headersExcel);
        }
        else {
            $headersExcel = array_merge(...$headersExcel);
        }

        $dataExcel = array_map(
        function($parcoursId) use ($fieldValueArray, $withDefaultHeader, $nbMaxComposanteInscription) { 
            $parcours = $this->em->getRepository(Parcours::class)->findOneById($parcoursId);

            return array_merge(
                $this->getDefaultHeaderValue($parcours, $withDefaultHeader)
            , array_merge(
                    ...array_map(
                        function($field) use ($parcours, $nbMaxComposanteInscription) {
                            if($this->mapParcoursExportWithValues($field, $parcours)['type'] === 'list'){
                                return array_map(fn($value) => $value['content'], $this->mapParcoursExportWithValues($field, $parcours)['value']);
                            }
                            elseif ($this->mapParcoursExportWithValues($field, $parcours)['type'] === 'longtext') {
                                return [$this->mapParcoursExportWithValues($field, $parcours)['value']];
                            }
                            elseif ($this->mapParcoursExportWithValues($field, $parcours)['type'] === 'nested_list') {
                                $blocsIntoString = array_map(function($list) {
                                    return $list['nested_libelle'] . ' - ' . implode($list['nested_value']);
                                }, $this->mapParcoursExportWithValues($field, $parcours)['value']);

                                $blocsIntoString = implode(' - ', $blocsIntoString);
                                return [$blocsIntoString];
                            }
                            elseif ($this->mapParcoursExportWithValues($field, $parcours)['type'] === 'full_block') {
                                return array_merge(...array_map(
                                    function ($fieldBlock) {
                                        $fieldBlockType = $fieldBlock['type'] ?? 'none';
                                        if($fieldBlockType === 'list_enum'){
                                            return [implode(" - ", array_map(fn($value) => $value->value ?? $value, $fieldBlock['content']))];
                                        }
                                        elseif ($fieldBlockType === 'list') {
                                            return [implode(" | ", $fieldBlock['content'])];
                                        }
                                        elseif ($fieldBlockType === 'nested_content_array') {
                                            return $fieldBlock['content'];
                                        }
                                        else {
                                            return [$fieldBlock['content']];
                                        }
                                    }
                                    , $this->mapParcoursExportWithValues($field, $parcours, 'xlsx', [
                                        'nbMaxComposantesInscription' => $nbMaxComposanteInscription
                                    ])['value']
                                ));
                            }
                            elseif($this->mapParcoursExportWithValues($field, $parcours)['type'] === 'array_list'){
                                return [implode(' - ', $this->mapParcoursExportWithValues($field, $parcours)['value'])];
                            }

                        }
                        , $fieldValueArray
                        )
                    )   
                );
            }
            , $parcoursData
        );

        /**
         * Mise à plat du résultat
         */
        $finalData = [];
        foreach($dataExcel as $p){
            $return = [];
            foreach($p as $d){
                if(is_array($d)){
                    $return = array_merge($return, $d);
                }
                else {
                    $return[] = $d;
                }
            }
            $finalData[] = $return;
        }

        $columnsIndex = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 
            'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'O', 'P', 'Q', 'R', 'S', 'T', 'U',
            'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB',
            'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI',
            'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP',
            'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW',
            'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD',
            'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 
            'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 
            'BS', 'BT', 'BU', 'BV', 'BW', 'BX', 'BY', 
            'BZ',
        ];

        $spreadSheet = new Spreadsheet();
        $activeWS = $spreadSheet->getActiveSheet();
        // Taille des colonnes
        foreach($columnsIndex as $colI){
            $activeWS->getColumnDimension($colI)->setAutoSize(true);
        }
        $activeWS->fromArray($headersExcel);
        $activeWS->fromArray($finalData, startCell: 'A2');


        $writer = new Xlsx($spreadSheet);


        $dateNow = new DateTime();
        $dateFormat = $dateNow->format("d-m-Y_H-i");

        $filename = "Export-Generique-Parcours-{$dateFormat}";

        $tmpFile = $this->fs->tempnam(__DIR__ . "/../../public/temp/", 'export_generique');
        $writer->save($tmpFile);
        $fileContent = file_get_contents($tmpFile);
        $this->fs->remove($tmpFile);

        return [$fileContent, $filename];
    }

    private function mapParcoursExportWithValues(
        string $fieldValue,
        ?Parcours $parcours,
        string $exportType = 'pdf',
        array $context = []
    ) {
        switch($fieldValue){
            case 'respParcours':
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'header_content' => [
                        'Responsable du parcours',
                        'Email',
                        'Co-responsable du parcours',
                        'Email'
                    ],
                    'value' => [
                        ...array_merge(
                        [
                            [
                                'libelle' => 'Responsable du parcours',
                                'content' =>  $parcours?->getRespParcours()?->getDisplay(),
                            ],
                            [
                                'libelle' => 'Email',
                                'content' => $parcours?->getRespParcours()?->getEmail()
                            ],
                        ],
                        $parcours?->getCoResponsable() ?
                            [
                                [
                                    'libelle' => 'Co-responsable du parcours',
                                    'content' =>  $parcours?->getCoResponsable()?->getDisplay()
                                ],
                                [
                                    'libelle' => 'Email',
                                    'content' => $parcours?->getCoResponsable()?->getEmail()
                                ]
                            ]
                            : 
                            [
                                ['libelle' => '', 'content' => ''],
                                ['libelle' => '', 'content' => ''] // Colonnes vides Excel
                            ]
                        )
                    ]
                ];
                break;
            case 'respFormation':
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'header_content' => [
                        'Responsable de la formation',
                        'Email',
                        'Co-responsable de la formation',
                        'Email'
                    ],
                    'value' => 
                    [
                        ...array_merge(
                        [
                            [
                                'libelle' => 'Responsable de la formation',
                                'content' =>  $parcours?->getFormation()->getResponsableMention()?->getDisplay()
                            ],
                            [
                                'libelle' => 'Email',
                                'content' => $parcours?->getFormation()->getResponsableMention()?->getEmail()
                            ]
                        ], 
                        $parcours?->getFormation()?->getCoResponsable() 
                        ? [
                            [
                                'libelle' => 'Co-responsable de la formation',
                                'content' =>  $parcours?->getFormation()->getCoResponsable()?->getDisplay()
                            ],
                            [
                                'libelle' => 'Email',
                                'content' => $parcours?->getFormation()->getCoResponsable()?->getEmail()
                            ]
                        ]
                        :   [
                                ['libelle' => '', 'content' => ''],
                                ['libelle' => '', 'content' => ''], // Colonnes vide Excel
                            ]
                        )
                    ]
                ];
                break;
            case 'resultatsAttendusParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Résultats attendus du parcours',
                    'value' => $parcours?->getResultatsAttendus()
                ];
                break;
            case 'objectifsParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Objectifs du parcours',
                    'value' => $parcours?->getObjectifsParcours()
                ];
                break;
            case 'objectifsFormation':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Organisation et objectifs de la formation',
                    'value' => $parcours?->getFormation()?->getObjectifsFormation()
                ];
                break;
            case 'competencesAcquises': 
                return [
                    'type' => 'nested_list',
                    'libelle' => 'Compétences Acquises',
                    'value' => array_map(
                        fn($bloc) => [
                            'nested_libelle' => $bloc->display(),
                            'nested_value' => array_map(
                                fn($comp) => $comp->display(),
                                $bloc->getCompetences()?->toArray() ?? []
                            )
                        ]
                        , $parcours?->getBlocCompetences()?->toArray() ?? []
                    )
                ];
                break;
            case 'poursuiteEtudes': 
                return [
                    'type' => 'longtext',
                    'libelle' => "Poursuites d'études envisageables",
                    'value' => $parcours?->getPoursuitesEtudes()
                ];
                break;
            case 'debouchesParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Débouchés',
                    'value' => $parcours?->getDebouches()
                ];
                break;
            case 'localisationParcours':
                return [
                    'type' => 'full_block',
                    'libelle' => 'Localisation du parcours',
                    'value' => [
                        [
                            'libelle' => "Localisation du parcours",
                            'content' => $parcours?->getLocalisation()?->getLibelle()
                        ],
                        [
                            'libelle' => "Régime(s) d'inscription",
                            'content' => $parcours?->getRegimeInscription() ?? [],
                            'type' => 'list_enum'
                        ],
                        [
                            'libelle' => "Modalités de l'alternance",
                            'content' => $parcours?->getModalitesAlternance() ?? "Pas d'alternance"
                        ],
                        [
                            'libelle' => "Composante d'inscription",
                            'content' => $parcours?->getComposanteInscription()?->getLibelle()
                        ],
                        [
                            'libelle' => "Adresse",
                            'content' => $exportType === 'pdf' 
                                ? $parcours?->getComposanteInscription()?->getAdresse()?->display()
                                : preg_replace('/<br>/', ' ', ($parcours?->getComposanteInscription()?->getAdresse()?->display() ?? ""))
                        ],
                        [
                            'libelle' => "Téléphone",
                            'content' => Tools::telFormat($parcours?->getComposanteInscription()?->getTelStandard())
                        ],
                        [
                            'libelle' => "Email",
                            'content' => $exportType === 'pdf' 
                                ? "<a href=\"mailto:{$parcours?->getComposanteInscription()?->getMailContact()}\">{$parcours?->getComposanteInscription()?->getMailContact()}</a>"
                                : $parcours?->getComposanteInscription()?->getMailContact()
                        ],
                        [
                            'libelle' => "Site web",
                            'content' => $exportType === 'pdf' 
                                ? "<a href=\"{$parcours?->getComposanteInscription()?->getUrlSite()}\">{$parcours?->getComposanteInscription()?->getUrlSite()}</a>"
                                : $parcours?->getComposanteInscription()?->getUrlSite()
                        ],
                    ]
                ];
                break;
            case 'identiteFormation':
                return [
                    'type' => 'full_block',
                    'libelle' => 'Identité de la formation',
                    'value' => [
                        [
                            'libelle' => 'Id formation',
                            'content' => "#{$parcours?->getFormation()?->getId()}"
                        ],
                        [
                            'libelle' => 'Type de diplôme',
                            'content' => $parcours?->getFormation()?->getTypeDiplome()?->getLibelle()
                        ],
                        [
                            'libelle' => 'Mention / Spécialité',
                            'content' => $parcours?->getFormation()?->getDisplay()
                        ],
                        [
                            'libelle' => 'Parcours',
                            'content' => array_map(
                                fn($p) => $p?->getLibelle() ?? ""
                                , $parcours?->getFormation()?->getParcours()?->toArray() ?? []
                            ),
                            'type' => 'list'
                        ],
                        [
                            'libelle' => 'Domaine',
                            'content' => $parcours?->getFormation()?->getDomaine()?->getLibelle()
                        ],
                        [
                            'libelle' => 'Composante porteuse',
                            'content' => $parcours?->getFormation()?->getComposantePorteuse()?->getLibelle()
                        ],
                        [
                            'libelle' => 'Inscription au RNCP',
                            'content' => $parcours?->getFormation()->isInRncp() ? $parcours?->getFormation()?->getCodeRNCP() : 'Non inscrit'
                        ],
                        [
                            'libelle' => "Niveau à l'entrée de la formation",
                            'content' => $parcours?->getFormation()?->getNiveauEntree()?->libelle()
                        ],
                        [
                            'libelle' => "Niveau à la sortie de la formation",
                            'content' => $parcours?->getFormation()?->getNiveauSortie()?->libelle()
                        ],
                    ]
                ];
                break;
            case 'rythmeFormation': 
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => 'Rythme de formation',
                            'content' => $parcours?->getRythmeFormation()?->getLibelle() ?? 'Non précisé'
                        ],
                        [
                            'libelle' => 'Précisions sur le rythme de formation',
                            'content' => $parcours?->getRythmeFormationTexte()
                        ]
                    ]
                ];
                break;
            case 'contenuParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Organisation de la formation',
                    'value' => $parcours?->getContenuFormation()
                ];
                break;
            case 'contenuFormation':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Contenu de la formation',
                    'value' => $parcours?->getFormation()?->getContenuFormation()
                ];
                break;
            case 'admissionParcours':
                return [
                    'type' => 'full_block',
                    'libelle' => 'Admission',
                    'value' => [
                        [
                            'libelle' => 'Niveau de français requis',
                            'content' => $parcours?->getNiveauFrancais()?->libelle()
                        ],
                        [
                            'libelle' => 'Prérequis recommandés',
                            'content' => $parcours?->getPrerequis()
                        ]
                    ]
                ];
                break;
            case 'codesRome':
                return [
                    'type' => 'array_list',
                    'libelle' => 'Code ROME',
                    'value' => array_map(fn($c) => $c['code'], $parcours?->getCodesRome() ?? [])
                ];
                break;
            case 'informationsInscription':
                return [
                    'type' => 'full_block',
                    'libelle' => 'Inscription',
                    'value' => [
                        [
                            'libelle' => "Localisation(s) de la mention/spécialité",
                            'content' => implode(', ', array_map(fn($v) => $v->getLibelle()
                            , $parcours?->getFormation()?->getLocalisationMention()?->toArray() ?? []))
                        ],
                        [
                            'libelle' => "Régime(s) d'inscription",
                            'content' => $parcours?->displayRegimeInscription()
                        ],
                        [
                            'libelle' => "Modalités de l'alternance",
                            'content' => $parcours?->getModalitesAlternance()
                        ],
                        [
                            'libelle' => "",
                            'type' => 'nested_content_array',
                            'type_value' => 'multipleComposanteInscription',
                            'header_content' => [
                                "Composante d'inscription",
                                "Adresse",
                                "Téléphone",
                                "Email",
                                "Site Web"
                            ],
                            'content' => (function() use($parcours, $exportType, $context){
                                $result = array_map(function($composante) use ($exportType) {
                                    return [
                                        $composante->getLibelle(),
                                        $exportType === 'pdf' 
                                            ? $composante->getAdresse()?->display()
                                            : preg_replace('/<br>/', ' ', ($composante->getAdresse()?->display() ?? "")),
                                        Tools::telFormat($composante->getTelStandard()),
                                        $exportType === 'pdf' 
                                            ? "<a href=\"mailto:{$composante->getMailContact()}\">" . $composante->getMailContact() . "</a>"
                                            : $composante->getMailContact(),
                                        $exportType === 'pdf' 
                                            ? "<a href=\"{$composante->getUrlSite()}\">" . $composante->getUrlSite() . "</a>"
                                            : $composante->getUrlSite()
                                    ];
                                }
                                , $parcours?->getFormation()->getComposantesInscription()?->toArray() ?? []);

                                if($exportType === 'xlsx'){
                                    $emptyCells = ["", "", "", "", ""]; // Cellules vides Excel
                                    for($i = count($result) * 5; $i < $context['nbMaxComposantesInscription'] * 5; $i += 5){
                                        $result = array_merge($result, $emptyCells);
                                    }
                                }

                                return $result;
                            })()
                        ]
                    ]
                ];
                break;
            case 'stageInfos':
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => 'Stage',
                            'content' => $parcours?->isHasStage() ? 'Oui' : 'Non'
                        ],
                        [
                            'libelle' => 'Heures Stage',
                            'content' => $parcours?->getNbHeuresStages()
                        ],
                        [
                            'libelle' => 'Modalités Stage',
                            'content' => $parcours?->getStageText()
                        ]
                    ]
                ];
                break;
            case 'projetInfos': 
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => 'Projet',
                            'content' => $parcours?->isHasProjet() ? 'Oui' : 'Non'
                        ],
                        [
                            'libelle' => 'Heures Projet',
                            'content' => $parcours?->getNbHeuresProjet()
                        ],
                        [
                            'libelle' => 'Modalités Projet',
                            'content' => $parcours?->getProjetText()
                        ]
                    ]
                ];
                break;

            case 'memoireInfos':
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => 'TER/mémoire',
                            'content' => $parcours?->isHasMemoire() ? 'Oui' : 'Non'
                        ],
                        [
                            'libelle' => 'Modalités TER',
                            'content' => $parcours?->getMemoireText()
                        ],
                    ]
                ];
                break;
            case 'composantePorteuse':
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => 'Composante',
                            'content' => $parcours?->getFormation()?->getComposantePorteuse()?->getLibelle()
                        ]
                    ]
                ];
                break;
            case 'typeDiplome';
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => 'Type Diplôme',
                            'content' => $parcours?->getFormation()?->getTypeDiplome()?->getLibelle()
                        ]
                    ]
                ];
                break;
            case 'modalitesEnseignement': 
                return [
                    'type' => 'full_block',
                    'libelle' => '',
                    'value' => [
                        [
                            'libelle' => "Modalités d'enseignement",
                            'content' => $parcours?->getModalitesEnseignement()?->libelle()
                        ]
                    ]
                ];
                break;
            case 'contactsPedagogiques': 
                return [
                    'type' => 'full_block',
                    'libelle' => 'Contacts pédagogiques',
                    'header_content' => [
                        'Responsable du parcours',
                        'Email',
                        'Co-responsable du parcours',
                        'Email',
                        'Coordonnées secrétariat',
                        'Contact'
                    ],
                    'value' => [
                        ...array_merge(
                        [
                            [
                                'libelle' => 'Responsable du parcours',
                                'content' => $parcours?->getRespParcours()?->getDisplay()
                            ],
                            [
                                'libelle' => 'Email',
                                'content' => $parcours?->getRespParcours()?->getEmail()
                            ]
                        ],
                        $parcours?->getCoResponsable() ?
                        [
                            [
                                'libelle' => 'Co-responsable du parcours',
                                'content' => $parcours?->getCoResponsable()?->getDisplay()
                            ],
                            [
                                'libelle' => 'Email',
                                'content' => $parcours?->getCoResponsable()?->getEmail()
                            ]
                        ]
                        : ($exportType === 'xlsx' 
                            ? [
                                ['libelle' => '', 'content' => ''], // Colonnes vides pour Excel
                                ['libelle' => '', 'content' => '']
                              ]
                            : []
                        ),
                        [
                            [
                                'libelle' => 'Coordonnées secrétariat',
                                'content' => $parcours?->getCoordSecretariat()
                            ]
                        ],
                        [
                            [
                                'libelle' => $exportType === 'pdf'
                                    ? (
                                        $parcours?->getContacts()?->first() instanceof Contact
                                        ? $parcours?->getContacts()?->first()?->getDenomination() 
                                        : ""
                                    )
                                    : 'Contact',
                                'content' => $exportType === 'pdf'
                                    ? 
                                    (
                                        $parcours?->getContacts()?->first() instanceof Contact        
                                        ? "<br>" . Tools::telFormat($parcours?->getContacts()?->first()?->getTelephone() ?? "")
                                            . "<br><br>" . "<a href=\"mailto:{$parcours?->getContacts()->first()?->getEmail()}\">"
                                            . $parcours?->getContacts()?->first()?->getEmail() . "</a>"
                                            . "<br><br>" . $parcours?->getContacts()?->first()?->getAdresse()?->display()
                                        : ""
                                      )
                                    : (
                                        $parcours?->getContacts()?->first() instanceof Contact 
                                        ? $parcours?->getContacts()?->first()->getDenomination()
                                            . " " . Tools::telFormat($parcours?->getContacts()?->first()?->getTelephone())
                                            . " " . $parcours?->getContacts()?->first()?->getEmail()
                                            . " " . preg_replace('/<br>/', ' ', ($parcours?->getContacts()?->first()?->getAdresse()?->display() ?? ""))
                                        : "" 
                                      )
                            ]
                        ]
                        )
                    ]
                ];
                break;
            case 'semestresOuverts':
                return [
                    'type' => 'full_block',
                    'libelle' => 'Semestres ouverts',
                    'value' => (function() use ($parcours) {
                        $semestreTab = [
                            ['libelle' => 'Semestre 1', 'content' => ''],
                            ['libelle' => 'Semestre 2', 'content' => ''],
                            ['libelle' => 'Semestre 3', 'content' => ''],
                            ['libelle' => 'Semestre 4', 'content' => ''],
                            ['libelle' => 'Semestre 5', 'content' => ''],
                            ['libelle' => 'Semestre 6', 'content' => ''],
                        ];

                        if($parcours !== null){
                            $semestreParcours = $this->em->getRepository(SemestreParcours::class)
                                ->findByParcours($parcours);

                            foreach($semestreParcours as $sp){
                                $semestreTab[$sp->getSemestre()->getOrdre() - 1]['content'] = 
                                    $sp->getSemestre()->isNonDispense() || !$sp->isOuvert() ? 'Fermé' : 'Ouvert';
                            }
                        }
                        return $semestreTab;
                    })()
                ];
                break;
            case 'nomParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => "Parcours",
                    'value' => $parcours?->getDisplay()
                ];
                break;
            case 'nomFormation':
                return [
                    'type' => 'longtext',
                    'libelle' => "Mention",
                    'value' => $parcours?->getFormation()?->getDisplay()
                ];
                break;
            case 'etatDpeParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Etat Parcours/Mention',
                    'value' => $parcours 
                        ? GetDpeParcours::getFromParcours($parcours)->getEtatReconduction()?->getLibelle() 
                        : ""
                ];
                break;
            case 'idParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => '# Parcours',
                    'value' => $parcours ? $parcours->getId() : ""
                ];
                break;
            case 'idFormation':
                return [
                    'type' => 'longtext',
                    'libelle' => '# Mention',
                    'value' => $parcours ? $parcours->getFormation()?->getId() : ""
                ];
                break;
            case 'villeParcours':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Lieu de formation',
                    'value' => $parcours?->getLocalisation()?->getLibelle() ?? ""
                ];
                break;
            case 'codeRNCP':
                return [
                    'type' => 'longtext',
                    'libelle' => 'RNCP',
                    'value' => $parcours?->getFormation()?->getCodeRNCP() ?? ""
                ];
                break;
            case 'dateValidationCFVU':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Validation CFVU',
                    'value' => $parcours 
                        ? $this->getHistorique->getHistoriqueParcoursLastStep(
                            GetDpeParcours::getFromParcours($parcours), 'cfvu'
                        )?->getDate()?->format('d/m/Y') ?? 'Non validé'
                        : ""
                ];
                break;
            case 'prerequisRecommandes':
                return [
                    'type' => 'longtext',
                    'libelle' => "Prérequis recommandés",
                    'value' => $parcours?->getPrerequis() ?? ""
                ];
                break;
            case 'niveauEntree':
                return [
                    'type' => 'longtext',
                    'libelle' => "Niveau d'entrée",
                    'value' => $parcours?->getFormation()?->getNiveauEntree()?->libelle() ?? ""
                ];
                break;
            case 'niveauSortie':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Niveau de sortie',
                    'value' => $parcours?->getFormation()?->getNiveauSortie()?->libelle() ?? ""
                ];
                break;
            case 'dateConseil':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Validation Composante',
                    'value' => $parcours 
                        ? $this->getHistorique->getHistoriqueParcoursLastStep(
                            GetDpeParcours::getFromParcours($parcours),
                            'conseil'
                        )?->getDate()?->format('d/m/Y') ?? 'Non validé' : ""
                ];
                break;
            case 'formationHasPV':
                return [
                    'type' => 'longtext',
                    'libelle' => 'Présence du PV',
                    'value' => $parcours 
                    ? ($this->getHistorique->getHistoriqueFormationHasPv($parcours->getFormation()) === true ? 'Oui' : 'Non')
                    : 'Non' 
                ];
                break;
            case 'villesFormation': 
                return [
                    'type' => 'longtext',
                    'libelle' => 'Lieux de formation',
                    'value' => implode(', ', array_map(
                        fn($v) => $v->getLibelle()
                        , $parcours?->getFormation()?->getLocalisationMention()?->toArray() ?? []))
                ];
                break;
        }
    }

    private function getFieldOrderForExportGenerique(){
        return [
            'composantePorteuse' => 1,
            'typeDiplome' => 2,
            'identiteFormation' => 3,
            'respFormation' => 4,
            'respParcours' => 5,
            'objectifsFormation' => 6,
            'contenuFormation' => 7,
            'contenuParcours' => 8,
            'objectifsParcours' => 9,
            'resultatsAttendusParcours' => 10,
            'rythmeFormation' => 11,
            'localisationParcours' => 12,
            'competencesAcquises' => 13,
            'admissionParcours' => 14,
            'informationsInscription' => 15,
            'poursuiteEtudes' => 16,
            'debouchesParcours' => 17,
            'codesRome' => 18,
            'modalitesEnseignement' => 19,
            'stageInfos' => 20,
            'projetInfos' => 21,
            'memoireInfos' => 22,
            'contactsPedagogiques' => 23,
            'semestresOuverts' => 24,
            'nomParcours' => 25,
            'nomFormation' => 26,
            'etatDpeParcours' => 27,
            'idParcours' => 28,
            'idFormation' => 29,
            'villeParcours' => 30,
            'codeRNCP' => 31,
            'dateValidationCFVU' => 32,
            'prerequisRecommandes' => 33,
            'niveauEntree' => 34,
            'niveauSortie' => 35,
            'dateConseil' => 36,
            'formationHasPV' => 37
        ];
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

    private function checkPredefinedTemplateExists(string $templateName){
        $isExisting = in_array($templateName, [
            'templateExportCapApogee'
        ]);
        if(!$isExisting){
            throw new NotFoundHttpException("Le template précisé n'a pas été trouvé.");
        }
    }

    private function getDefaultHeaderValue(?Parcours $parcours, bool $withHeader = true){
        if($parcours !== null && $withHeader){
            return [
                $parcours->getFormation()?->getTypeDiplome()->getLibelle(),
                $parcours->getFormation()?->getDisplay(),
                $parcours->getDisplay()
            ];
        }

        return [];
    }

    private function getPredefinedTemplate(string $templateName, array $parcoursIdArray, string $typeExport = 'xlsx'){
        switch($templateName){
            case 'templateExportCapApogee':
                return $this->getExportCapApogee($typeExport, $parcoursIdArray);
                break;
        }
    }   

    private function getExportCapApogee(string $typeExport, array $parcoursIdArray){
        if($typeExport === 'xlsx'){
            $excelData = [];
            foreach($parcoursIdArray as $id){
                // Parcours
                $dto = $this->em->getRepository(Parcours::class)->findOneById($id);
                $headersParcours = [
                    $dto->getFormation()?->getComposantePorteuse()?->getLibelle() ?? "",
                    $dto->getFormation()?->getTypeDiplome()?->getLibelle() ?? "",
                    $dto->getFormation()?->getDisplay(),
                    $dto->getLibelle()
                ];
                // Transformé ensuite en maquette
                $dto = $this->typeDiplomeR
                    ->fromParcours($dto)
                    ->calcul($dto, new OptionsCalculStructure(dataFromFicheMatiere: true));
                foreach($dto->semestres as $semestre){
                    $ordreSemestre = $semestre->semestre->getOrdre();
                    foreach($semestre->ues as $ue){
                        if($ue->ue->getNatureUeEc()?->isChoix()){
                            foreach($ue->uesEnfants() as $ueEnfant){
                                if($ueEnfant->ue->getNatureUeEc()?->isChoix()){
                                    foreach($ueEnfant->uesEnfants() as $ueEnfantDeuxieme){
                                        if($ueEnfantDeuxieme->ue->getNatureUeEc()?->isLibre() === false){
                                            $excelData[] = $this->getEcFromUeCapApogee(
                                                $ueEnfantDeuxieme, 
                                                $semestre->semestreParcours, 
                                                true,
                                                $ordreSemestre,
                                                $headersParcours
                                            );
                                        }
                                    }
                                }
                                else {
                                    $excelData[] = $this->getEcFromUeCapApogee(
                                        $ueEnfant, 
                                        $semestre->semestreParcours, 
                                        false, 
                                        $ordreSemestre,
                                        $headersParcours
                                    );
                                }
                            }
                        }
                        else {
                            $excelData[] = $this->getEcFromUeCapApogee(
                                $ue, 
                                $semestre->semestreParcours, 
                                false, 
                                $ordreSemestre,
                                $headersParcours
                            );
                        }
                    }
                }
            }

            $headersExcel = [
                'Composante',
                'Type Diplôme',
                'Mention',
                'Parcours',
                'Code Dip.' ,
                'VDI',
                'Code étape',
                'VET',
                'Semestre',
                'Code Semestre',
                'UE', 
                'Code UE',
                'ID Fiche EC/matière',
                'Fiche EC/matière',
                'Code élément',
                'CM',
                'TD',
                'TP',
                'MATI/MATM',
                'Option',
            ];

            $colIndex = [
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
                'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'
            ];

            $excelData = array_merge(...$excelData);

            return $this->getXlsxContent($headersExcel, $excelData, $colIndex);
        }
    }

    private function getXlsxContent(array $headers, array $data, array $autosizeColIndex = []){
        $spreadSheet = new Spreadsheet();
        $activeWS = $spreadSheet->getActiveSheet();
        $activeWS->fromArray($headers);
        $activeWS->fromArray($data, startCell: 'A2');

        $writer = new Xlsx($spreadSheet);
        $dateNow = (new DateTime())->format('d-m-Y_H-i');
        $filename = "Export-Generique-Parcours-{$dateNow}";

        foreach($autosizeColIndex as $col){
            $activeWS->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpFile = $this->fs->tempnam(__DIR__ . "/../../public/temp/", 'export_generique');
        $writer->save($tmpFile);
        $fileContent = file_get_contents($tmpFile);
        $this->fs->remove($tmpFile);

        return [$fileContent, $filename];
    }

    private function getEcCapApogeeData(
        StructureEc $ec,
        StructureUe $ue,
        ?SemestreParcours $semP,
        bool $isOption,
        int $ordreSemestre,
        array $prefixData = []
    ){
        return [
            ...$prefixData,
            $semP?->getCodeApogeeDiplome() ?? "",
            $semP?->getCodeApogeeVersionDiplome() ?? "",
            $semP?->getCodeApogeeEtapeAnnee() ?? "",
            $semP?->getCodeApogeeEtapeVersion() ?? "",
            "S{$ordreSemestre}",
            $semP?->getSemestre()?->getCodeApogee(),
            $ue->ue->display(),
            $ue->ue->getCodeApogee(),
            $ec->elementConstitutif->displayId(),
            $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? '-',
            $ec->elementConstitutif->displayCodeApogee(),
            $ec->heuresEctsEc->cmPres,
            $ec->heuresEctsEc->tdPres,
            $ec->heuresEctsEc->tpPres,
            $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? "-",
            $isOption ? 'Choix/option' : 'Obligatoire',
        ];
    }

    private function getEcFromUeCapApogee(
        StructureUe $ue, 
        ?SemestreParcours $semP, 
        bool $isOption,
        int $ordreSemestre,
        array $prefixData = []
    ){
        $return = [];
        foreach($ue->elementConstitutifs as $ec){
            if($ec->elementConstitutif->getNatureUeEc()?->isChoix()){
                foreach($ec->elementsConstitutifsEnfants as $ecEnfant){
                    $return[] = $this->getEcCapApogeeData($ecEnfant, $ue, $semP, true, $ordreSemestre, $prefixData);
                }
            }
            else {
                $return[] = $this->getEcCapApogeeData($ec, $ue, $semP, $isOption, $ordreSemestre, $prefixData);
            }
        }

        return $return;
    }

    private function checkCanExportData(string $dataExportName, string $exportType){
        $availableData = [
            'templateExportCapApogee' => ['pdf' => false, 'xlsx' => true]
        ];

        return $availableData[$dataExportName][$exportType];
    }
}