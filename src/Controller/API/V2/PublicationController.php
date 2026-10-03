<?php

namespace App\Controller\API\V2;

use App\Entity\Parcours;
use App\Repository\ParcoursVersioningRepository;
use App\Service\ParcoursExportV2;
use App\Service\VersioningParcours;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/site/web/v2/parcours', name: 'api_site_web_v2_parcours_')]
final class PublicationController extends AbstractController
{
    #[Route('/{parcours}/maquette', name: 'maquette', methods: ['GET'])]
    public function maquette(
        Parcours $parcours,
        ParcoursExportV2 $parcoursExport,
    ): JsonResponse {
        return $this->json($parcoursExport->exportMaquetteJson($parcours));
    }

    #[Route('/{parcours}/maquette/validee-cfvu', name: 'maquette_validee_cfvu', methods: ['GET'])]
    public function maquetteValideeCfvu(
        Parcours $parcours,
        ParcoursExportV2 $parcoursExport,
        VersioningParcours $versioningParcours,
        ParcoursVersioningRepository $parcoursVersioningRepository,
    ): JsonResponse {
        $versions = $parcoursVersioningRepository->findLastCfvuVersion($parcours);

        if ($versions === []) {
            return $this->json(
                ['error' => 'no valid version available'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $version = $versions[0];
        $versionData = $versioningParcours->loadParcoursFromVersion($version);

        return $this->json($parcoursExport->exportLastValidVersionMaquetteJson(
            $versionData['dto'],
            $versionData['parcours'],
            $version->getParcours()->getId(),
            $version->getParcours()->getFormation()?->getId(),
            fermetureEmpty: true,
        ));
    }
}
