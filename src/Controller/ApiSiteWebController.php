<?php

namespace App\Controller;

use App\Classes\GetHistorique;
use App\Entity\DpeParcours;
use App\Repository\FormationRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiSiteWebController extends AbstractController
{
    #[Route('/api/site/web', name: 'app_api_site_web')]
    public function index(
        FormationRepository $formatinRepository,
    ): JsonResponse
    {
        // DATE DE PUBLICATION : 30-09-2024
        $datePublication = new DateTime();
        $datePublication->setDate(2025, 9, 30);
        $datePublication->setTime(0, 0);

        $data = [];
        $formations = $formatinRepository->findAll();

        // COMPTEUR DES PARCOURS A ENVOYER
        $countParcours = 0;

        foreach ($formations as $formation) {
            /**
             *
             * NE PRENDRE QUE LES PARCOURS QUI SONT ÉTIQUETÉS COMME PUBLIÉS
             *  - Filtrer sur l'état de validation 'DpeParcours'
             *
             */
            $tParcours = [];
            foreach ($formation->getParcours() as $parcours) {
                $isPubliable = false;

                if($parcours->getDpeParcours()?->last() instanceof DpeParcours){
                    $etatValidation = $parcours->getDpeParcours()?->last()->getEtatValidation();
                    $campagneCollecte = $parcours->getDpeParcours()?->last()->getCampagneCollecte()?->getId();
                    if (($etatValidation === ['valide_a_publier' => 1] || $etatValidation === ['publie' => 1]) && $campagneCollecte === 2) {
                        $isPubliable = true;
                        ++$countParcours;
                    }
                }

                if($isPubliable){
                    $tParcours[] = [
                        'id' => $parcours->getId(),
                        'libelle' => $parcours->getDisplay(),
                        'url' => $this->generateUrl('app_parcours_export_json_urca', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
                    ];
                }
            }

            if(count($tParcours) > 0){
                $data[] = [
                    'id' => $formation->getId(),
                    'libelle' => $formation->getDisplayLong(),
                    'parcours' => $tParcours,
                    //todo: on pourrait ajouter la version. Le Lheo doit dépendre de la version
                    // 'dateValidation' => $getHistorique->getHistoriqueFormationLastStep($formation, 'publication')?->getDate()?->format('Y-m-d H:i:s') ?? null,
                    'dateValidation' => $datePublication->format('Y-m-d H:i:s'),
                ];
            }
        }

        // VERIFICATION NOMBRE DE PARCOURS ENVOYÉS
        // dump("Nombre de parcours envoyés : {$countParcours}.");exit;


        return new JsonResponse($data);
    }

    #[Route('/api/site/web/versioning_json/', name: 'api_site_web_versioning_json')]
    public function indexVersioningJson(
        Filesystem $fs
    ) : Response {

        $filename = "api_json_urca_versioning.json";
        $path = __DIR__ . "/../../public/api_json/";

        if($fs->exists($path . $filename) === true){
            $api = file_get_contents($path . $filename);
            return new JsonResponse($api, json: true);
        }

        return new JsonResponse(["error" => "API File does not exist."]);
    }

}
