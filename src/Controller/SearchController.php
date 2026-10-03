<?php

namespace App\Controller;

use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Navigation\NavigationSearchService;
use App\Repository\FicheMatiereRepository;
use App\Service\Recherche\RechercheParcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SearchController extends AbstractController
{
    #[Route('/navigation/search', name: 'app_navigation_search')]
    public function search(
        Request                 $request,
        NavigationSearchService $service
    ): JsonResponse
    {
        return $this->json(
            $service->search(
                $request->query->get('q', '')
            )
        );
    }

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
        FicheMatiereRepository $ficheMatiereRepository,
        RechercheParcours $rechercheParcours,
    ): RedirectResponse|Response
    {
        $campagneCollecte = $entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['defaut' => true]);

        $request = Request::createFromGlobals();
        $keyword_1 = $request->query->getString('keyword_1');
        $typeRecherche = $request->query->getString('searchType');

        $typeRechercheValide = $typeRecherche;
        if (in_array($typeRecherche, ['parcours', 'ficheMatiere']) === false) {
            $typeRechercheValide = 'parcours';
        }

        if (empty($keyword_1) || mb_strlen($keyword_1) <= 2) {
            $this->addFlash('toast', [
                'type' => 'error',
                'text' => 'Le mot-clé fourni doit faire au moins 3 caractères.'
            ]);

            return $this->redirectToRoute('app_search');
        }

        if ($typeRechercheValide === 'parcours' && !$rechercheParcours->estSignificative($keyword_1)) {
            $this->addFlash('toast', [
                'type' => 'error',
                'text' => 'Le mot-clé ne contient que des mots trop courants (le, de, des…) : précisez votre recherche.'
            ]);

            return $this->redirectToRoute('app_search');
        }

        if ($typeRechercheValide === 'parcours') {
            $dataTwigRenderer = [
                'typeRecherche' => 'parcours',
                'recherche' => $rechercheParcours->rechercher($keyword_1, $campagneCollecte),
            ];
        } else {
            $countFiche = $ficheMatiereRepository->findCountForKeyword($keyword_1, $campagneCollecte)[0]['nombre_total'];

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

    #[Route('/recherche/fiche_matiere/{page}/{mot_cle}', name: 'app_search_fiche_matiere_pagination')]
    public function searchFicheMatiereForKeywordAndPage(
        int $page,
        string $mot_cle,
        EntityManagerInterface $entityManager,
        FicheMatiereRepository $ficheMatiereRepository
    ): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $campagneCollecteDefaut = $entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['defaut' => true]);

        $data = $ficheMatiereRepository->findFicheMatiereWithKeywordAndPagination($mot_cle, $page, true, $campagneCollecteDefaut);

        return $this->json($data);
    }

    #[Route('/recherche/fiche_matiere/export/excel/{mot_cle}', name: 'app_search_fiche_matiere_export_excel')]
    public function exportFicheMatiereRecherche(
        string $mot_cle,
        EntityManagerInterface $entityManager,
        FicheMatiereRepository $ficheMatiereRepository,
        Filesystem $fs
    ) : Response {

        $campagne = $entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['defaut' => true]);

        $data = $ficheMatiereRepository->findFicheMatiereWithKeywordAndPagination($mot_cle, 0, false, $campagne);

        $data = array_map(function ($ficheMatiere) {
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

        foreach ($activeWorksheet->getRowIterator(2, count($data)) as $row) {
            foreach ($row->getCellIterator('D') as $cell) {
                $cell->getHyperlink()->setUrl(
                    $this->generateUrl(
                        'fiche_matiere_v2_voir',
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

        if ($fs->exists($path . $filename)) {
            $fs->remove($path . $filename);
        }

        // Création d'un fichier vide
        $fs->appendToFile($path . $filename, "");

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($path . $filename);

        $dataFile = file_get_contents($path . $filename);
        if ($dataFile === false) {
            throw new \RuntimeException('Impossible de lire le fichier Excel généré.');
        }

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
