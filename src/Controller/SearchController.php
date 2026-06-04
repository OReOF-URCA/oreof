<?php

namespace App\Controller;

use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeParcoursEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Utils\Tools;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SearchController extends AbstractController
{
    #[Route('/recherche/parcours', name: 'app_search')]
    public function index(): Response
    {
        return $this->render('search/index.html.twig', [
            'controller_name' => 'SearchController',
        ]);
    }

    #[Route('/recherche/mot_cle', name: 'app_search_action')]
    public function searchWithKeyword(
        EntityManagerInterface $entityManager,
    ): \Symfony\Component\HttpFoundation\RedirectResponse|Response
    {
        $campagneCollecte = $entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['defaut' => true]);

        $request = Request::createFromGlobals();
        $keyword_1 = $request->query->get('keyword_1');
        $typeRecherche = $request->query->get('searchType');

        $typeRechercheValide = $typeRecherche;
        if(in_array($typeRecherche, ['parcours', 'ficheMatiere']) === false){
            $typeRechercheValide = 'parcours';
        }

        if (empty($keyword_1) || mb_strlen($keyword_1) <= 2) {
            $this->addFlash('toast', [
                'type' => 'error',
                'text' => 'Le mot-clé fourni doit faire au moins 3 caractères.'
            ]);

            return $this->redirectToRoute('app_search');
        }

        if($typeRechercheValide === 'parcours'){

            $resultArrayBadge = [];
            $isParcoursParDefautArray = [];

            $parcoursArray = $entityManager->getRepository(Parcours::class)
                ->findWithKeyword($keyword_1, $campagneCollecte);
            $parcoursParDefautArray = $entityManager->getRepository(Parcours::class)
                ->findWithKeywordForDefaultParcours($keyword_1, $campagneCollecte);

            for($i = 0; $i < count($parcoursArray); $i++){
                $textContains = [];
                if( $this->isStringContainingText($keyword_1, $parcoursArray[$i]['contenuFormation'])) {
                    $textContains[] = 'contenuFormation';
                }
                if( $this->isStringContainingText($keyword_1, $parcoursArray[$i]['poursuitesEtudes'])) {
                    $textContains[] = 'poursuitesEtudes';
                }
                if( $this->isStringContainingText($keyword_1, $parcoursArray[$i]['objectifsParcours'])) {
                    $textContains[] = 'objectifsParcours';
                }
                if( $this->isStringContainingText($keyword_1, $parcoursArray[$i]['resultatsAttendus'])) {
                    $textContains[] = 'resultatsAttendus';
                }

                $parcours = $entityManager
                    ->getRepository(Parcours::class)
                    ->findOneById($parcoursArray[$i]['parcours_id']);

                $linkedFicheMatiere = $entityManager
                    ->getRepository(FicheMatiere::class)
                    ->findForParcoursWithKeyword($parcours, $keyword_1);

                $libelleMention = $entityManager
                    ->getRepository(Formation::class)
                    ->findOneById($parcoursArray[$i]['formation_id'])
                    ->getDisplayLong() ?? "";

                $typeParcoursLibelle = "";
                if($parcoursArray[$i]['type_parcours'] !== null){
                    $typeParcoursLibelle = $parcoursArray[$i]['type_parcours']->getLabel();
                }

                $resultArrayBadge[] =
                [
                    ...$textContains,
                    'fichesMatieres' => [...$linkedFicheMatiere],
                    'libelleMention' => $libelleMention,
                    'typeParcoursLibelle' => $typeParcoursLibelle
                ];

                $isParcoursParDefautArray[] = $parcoursArray[$i]['parcours_libelle'] === Parcours::PARCOURS_DEFAUT;
            }

            for($j = 0; $j < count($parcoursParDefautArray); $j++){
                $textContainsDefault = [];
                if($this->isStringContainingText($keyword_1, $parcoursParDefautArray[$j]['contenuFormation'])){
                    $textContainsDefault[] = 'contenuFormation';
                }
                if($this->isStringContainingText($keyword_1, $parcoursParDefautArray[$j]['resultatsAttendus'])){
                    $textContainsDefault[] = 'resultatsAttendus';
                }
                if($this->isStringContainingText($keyword_1, $parcoursParDefautArray[$j]['objectifsFormation'])){
                    $textContainsDefault[] = 'objectifsFormation';
                }
                if($this->isStringContainingText($keyword_1, $parcoursParDefautArray[$j]['poursuitesEtudes'])){
                    $textContainsDefault[] = 'poursuitesEtudes';
                }

                $parcoursDefaut = $entityManager
                    ->getRepository(Parcours::class)
                    ->findOneById($parcoursParDefautArray[$j]['parcours_id']);

                $linkedFicheMatiereDefault = $entityManager
                    ->getRepository(FicheMatiere::class)
                    ->findForParcoursWithKeyword($parcoursDefaut, $keyword_1);

                $libelleMentionParDefaut = $entityManager
                    ->getRepository(Formation::class)
                    ->findOneById($parcoursParDefautArray[$j]['formation_id'])
                    ->getDisplayLong() ?? "";

                $typeParcoursDefautLibelle = "";
                if($parcoursParDefautArray[$j]['type_parcours'] !== null){
                    $typeParcoursDefautLibelle = $parcoursParDefautArray[$j]['type_parcours']->getLabel();
                }

                $resultArrayBadge[] = [
                    ...$textContainsDefault,
                    'fichesMatieres' => [...$linkedFicheMatiereDefault],
                    'libelleMention' => $libelleMentionParDefaut,
                    'typeParcoursLibelle' => $typeParcoursDefautLibelle
                ];

                $isParcoursParDefautArray[] = $parcoursParDefautArray[$j]['parcours_libelle'] === Parcours::PARCOURS_DEFAUT;
            }

            $dataTwigRenderer = [
                'typeRecherche' => 'parcours',
                'parcoursArray' => [
                ...$parcoursArray,
                ...$parcoursParDefautArray
                ],
                'resultArrayBadge' => $resultArrayBadge,
                'isParcoursDefautArray' => $isParcoursParDefautArray
            ];
        }
        elseif($typeRechercheValide === 'ficheMatiere'){
            $countFiche = $entityManager
                ->getRepository(FicheMatiere::class)
                ->findCountForKeyword($keyword_1, $campagneCollecte)[0]['nombre_total'];

            $dataTwigRenderer = [
                'typeRecherche' => 'ficheMatiere',
                'nombreTotal' => $countFiche
            ];
        }

        return $this->render('search/search_result.html.twig', [
            'keyword_1' => $keyword_1,
            ...$dataTwigRenderer
        ]);
    }

    private function isStringContainingText(string $needle, string|null $haystack) : bool {
        if($haystack !== null){
            return
                mb_strstr(
                    mb_strtoupper(Tools::removeAccent($haystack)),
                    mb_strtoupper(Tools::removeAccent($needle))
                );
        }
        else {
            return false;
        }
    }

    #[Route('/recherche/fiche_matiere/{page}/{mot_cle}', name: 'app_search_fiche_matiere_pagination')]
    public function searchFicheMatiereForKeywordAndPage(
        int $page,
        string $mot_cle,
        EntityManagerInterface $entityManager
    ): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $campagneCollecteDefaut = $entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['defaut' => true]);

        $data = $entityManager->getRepository(FicheMatiere::class)
                ->findFicheMatiereWithKeywordAndPagination($mot_cle, $page, true, $campagneCollecteDefaut);

        return $this->json($data);
    }

    #[Route('/recherche/fiche_matiere/export/excel/{mot_cle}', name: 'app_search_fiche_matiere_export_excel')]
    public function exportFicheMatiereRecherche(
        string $mot_cle,
        EntityManagerInterface $entityManager,
        Filesystem $fs
    ) : Response {

        $campagne = $entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['defaut' => true]);

        $data = $entityManager->getRepository(FicheMatiere::class)
            ->findFicheMatiereWithKeywordAndPagination($mot_cle, 0, false, $campagne);

        $data = array_map(function($ficheMatiere){
            $libelleMention = $ficheMatiere['type_diplome_libelle'] ? $ficheMatiere['type_diplome_libelle'] . ' - ' : '';
            $libelleMention .= $ficheMatiere['mention_libelle'] . ' - ' . $ficheMatiere['parcours_libelle'];
            $libelleMention .= $ficheMatiere['parcours_sigle'] ? '(' . $ficheMatiere['parcours_sigle'] . ')': '';

            return [
                $libelleMention,
                $ficheMatiere['fiche_matiere_libelle'],
                $ficheMatiere['fiche_matiere_sigle'],
                $ficheMatiere['fiche_matiere_slug'],
            ];

        }, $data);

        $excelData = [
            ['Libellé du Parcours', 'Libellé de la fiche matière', 'Sigle de la fiche matière', 'Lien vers Oréof'],
            ...$data
        ];

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->fromArray($excelData);

        foreach($activeWorksheet->getRowIterator(2, count($data)) as $row){
            foreach($row->getCellIterator('D') as $cell){
                $cell->getHyperlink()->setUrl(
                    $this->generateUrl('app_fiche_matiere_show',
                        ['slug' => $cell->getValue()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    )
                );
            }
        }

        $activeWorksheet->getColumnDimension('A')->setAutoSize(true);
        $activeWorksheet->getColumnDimension('B')->setAutoSize(true);
        $activeWorksheet->getColumnDimension('C')->setAutoSize(true);
        $activeWorksheet->getColumnDimension('D')->setAutoSize(true);


        $now = (new DateTime())->format('d-m-Y');
        $path = __DIR__ . "/../../public/temp/";
        $filename =  "Export-recherche-fiche-matiere.xlsx";

        if($fs->exists($path . $filename)){
            $fs->remove($path . $filename);
        }

        // Création d'un fichier vide
        $fs->appendToFile($path . $filename, "");

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($path . $filename);

        $dataFile = file_get_contents($path . $filename);
        return new Response(
            $dataFile,
            200,
            [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => "attachment;filename=\"{$now}-{$mot_cle}-{$filename}\""
            ]
        );
    }
}
