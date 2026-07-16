<?php

namespace App\Controller;

use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\Service\TypeDiplomeResolver;
use App\Service\VersioningParcours;
use App\Utils\CleanTexte;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ParcoursExportMaquetteSiteWebController extends AbstractController
{
    #[Route('/parcours/{parcours}/maquette/v2/export-json-urca', name: 'app_parcours_export_maquette_json_urca_v2_niveau')]
    public function exportMaquetteJson(
        Parcours                $parcours,
        TypeDiplomeResolver $typeDiplomeResolver,
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $typeDiplomeResolver->get($typeDiplome);
        // dump($typeD);
        $data = $typeD->exportJsonApi($parcours);

        // Utilisation de la version validée
        /*
        if($versioningP->getLastVersionOrLastYearCfvu($parcours) === null) {
            return new JsonResponse('No valid version available for this ID.');
        }
        $dto = $versioningP->loadParcoursFromVersion(
            $versioningP->getLastVersionOrLastYearCfvu($parcours)
        )['dto'];
        */

        return $this->json($data);
    }
}
