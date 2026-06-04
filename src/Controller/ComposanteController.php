<?php

namespace App\Controller;

use App\Classes\Json\ExtractTextFromJsonPatch;
use App\Classes\MyGotenbergPdf;
use App\Entity\Composante;
use App\Repository\ComposanteRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ParcoursVersioningRepository;
use App\Service\VersioningParcours;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Swaggest\JsonDiff\JsonDiff;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ComposanteController extends BaseController
{
    #[Route('/composante/{composante<\d+>}', name: 'app_composante')]
    public function index(Composante $composante): Response
    {
        //deprecated
        return $this->render('composante/index.html.twig', [
            'composante' => $composante,
        ]);
    }

    #[IsGranted("ROLE_PDF_DOWNLOADER")]
    #[Route('/composante/{composante_id}/synthese_modification/pdf', 'export_pdf_synthese_modification_composante')]
    public function exportSyntheseAsPdfForComposante(
        int $composante_id,
        VersioningParcours $versioningParcours,
        ParcoursRepository $parcoursRepository,
        ParcoursVersioningRepository $parcoursVersioningRepository,
        MyGotenbergPdf $myGotenbergPdf,
        EntityManagerInterface $entityManager,
    ) : Response {

        /**
         *
         *     /!\ WARNING /!\
         *
         *     ENSURE YOU HAVE ENOUGH SYSTEM RESOURCES
         *     FOR THIS ROUTE
         *
         */
         ini_set('memory_limit', '2048M');
         ini_set('max_execution_time', '240');
         /**
          *
          *    END WARNING
          *
          */

        $cmp = $entityManager->getRepository(Composante::class)->findOneById($composante_id);

        $patterns = [
            '\/heuresEctsFormation\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/heuresEctsEc\/',
            '\/semestres\/\d+\/heuresEctsSemestre\/',
            '\/semestres\/\d+\/ues\/\d+\/uesEnfants\/\d+',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementsConstitutifsEnfants\/\d+\/elementConstitutif\/ficheMatiere\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementsConstitutifsEnfants\/\d+\/heuresEctsEc\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementConstitutif\/natureUeEc\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/',
            '\/heuresEctsFormation\/',
        ];

        $patternsAIgnorer = [
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/mcccs\/\d+\/',
            '\/semestres\/\d+\/ues\/\d+\/heuresEctsUe\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementConstitutif\/mcccs\/\d+\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementRaccroche\/mcccs\/\d+\/',
            '\/semestres\/\d+\/ues\/\d+\/uesEnfants\/\d+\/elementConstitutifs\/\d+\/heuresEctsEc\/',
            '\/semestres\/\d+\/ues\/\d+\/uesEnfants\/\d+\/heuresEctsUe\/',
            '\/semestres\/\d+\/ues\/\d+\/heuresEctsUeEnfants\/\d+\/',
            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/heuresEctsEcEnfants\/',
        ];


        $allparcours = $parcoursRepository->findByTypeValidationAttenteCfvuAndComposante($this->getCampagneCollecte(), 'soumis_central', $cmp->getId());

        foreach ($allparcours as $parcours) {
            //créer la sauvegarde JSON
            //récupère JSON CFVU
            $lastVersion = $parcoursVersioningRepository->findLastVersion($parcours);
            $lastVersion = count($lastVersion) > 0 ? $lastVersion[0] : null;

            //on fait une copie de la version courante en json
            $jsonCourant = $versioningParcours->saveVersionOfParcoursCourant($parcours);
            $jsonCfvu = $versioningParcours->loadJsonCfvu($parcours, $lastVersion);

            $comp = $parcours->getFormation()?->getComposantePorteuse();
            if ($comp !== null) {
                $idP = $parcours->getId();
                $idComp = $comp->getId();
                $tDemandes[$comp->getId()][$idP] = [];

                $r = new JsonDiff(json_decode($jsonCfvu), json_decode($jsonCourant));
                $tDemandes[$idComp][$idP]['parcours']['display'] = $parcours->getLibelle();
                $tDemandes[$idComp][$idP]['parcours']['formation'] = $parcours->getFormation()?->getDisplayLong();
                $tDemandes[$idComp][$idP]['nbDiff'] = $r->getDiffCnt();
                $result['modified'] = [];
                $result['added'] = [];
                $result['removed'] = [];
                $hasPatch = false;
                foreach ($r->getPatch()->jsonSerialize() as $patch) {
                    if (ExtractTextFromJsonPatch::getLastItem($patch->path)) {
                        $key = $this->extractPatternsFromString($patterns, $patch->path, $patternsAIgnorer);
                        if ($key !== null) {
                            switch ($patch->op) {
                                case 'test':
                                    $hasPatch = true;
                                    $result['modified'][$key['path']][$key['key']]['libelle'] = ExtractTextFromJsonPatch::getLibelle($patch->path, $jsonCourant);
                                    $result['modified'][$key['path']][$key['key']]['texte'] = ExtractTextFromJsonPatch::getTextFromPath($patch);
                                    $result['modified'][$key['path']][$key['key']]['original'] = ExtractTextFromJsonPatch::getOriginalValueFromPatch($patch);
                                    break;
                                case 'replace':
                                    $hasPatch = true;
                                    $result['modified'][$key['path']][$key['key']]['nouveau'] = ExtractTextFromJsonPatch::getNewValueFromPatch($patch);
                                    break;
                                case 'add':
                                    $hasPatch = true;
                                    $result['added'][$key['path']][$key['key']]['nouveau'] = ExtractTextFromJsonPatch::getLibelle($patch->path, $jsonCourant);
                                    $result['added'][$key['path']][$key['key']]['libelle'] = ExtractTextFromJsonPatch::getTextFromPath($patch);
                                    break;
                                case 'remove':
                                    $hasPatch = true;
                                    $result['removed'][$key['path']][$key['key']]['origine'] = ExtractTextFromJsonPatch::getLibelle($patch->path, $jsonCfvu);
                                    $result['removed'][$key['path']][$key['key']]['libelle'] = ExtractTextFromJsonPatch::getTextFromPath($patch);
                                    break;
                            }
                        }
                    }
                }
                $tDemandes[$idComp][$idP]['patch'] = $result;
            }
        }
        if ($hasPatch) {
            $pdf = $myGotenbergPdf->render(
                template: "pdf/synthese_modifications_parcours.html.twig",
                context: [
                    'titre' => 'Liste des demandes de changement MCCC et maquettes',
                    'demandes' => $tDemandes,
                    'composante' => $cmp,
                    'dpe' => $this->getCampagneCollecte(),
                ],
                name: "synthese_changement_cfvu_" . $cmp->getSigle() . "_" . (new DateTime())->format('d-m-Y_H-i-s'),
            );

            return new Response(
                content: $pdf,
                status: 200,
                headers: [
                    'Content-Type' => 'application/pdf'
                ]
            );
        }else {
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => "Aucune modification de détectée. Le rapport n'a pas été généré",
            ]);

            return new RedirectResponse($this->generateUrl("app_homepage"), 302);
        }
    }

    private function extractPatternsFromString(array $patterns, string $string, array $patternsAIgnorer = []): ?array
    {
        // on regarde si c'est un pattern à ignorer
        foreach ($patternsAIgnorer as $pattern) {
            preg_match("/^(".$pattern.")/", $string, $matches);
            if (!empty($matches[0])) {
                return null;
            }
        }


        foreach ($patterns as $pattern) {
            preg_match("/^(".$pattern.")/", $string, $matches);
            if (!empty($matches[0])) {
                return ['path' => $matches[0],
                    'key' => substr($string, strlen($matches[0]))
                    ];
            }
        }

        return null;
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route("/composante/cfvu/list", "app_composante_list_for_cfvu")]
    public function listComposanteToDownloadCfvuChanges(
        ComposanteRepository $composanteRepository
    ): Response
    {

        $composantes = $composanteRepository->findAll();

        return $this->render("composante/cfvu_composante_list.html.twig", [
            'composantes' => $composantes
        ]);
    }
}
