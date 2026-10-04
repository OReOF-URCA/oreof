<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FormationExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\MyGotenbergPdf;
use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Service\ParcoursExport;
use App\Service\VersioningParcours;
use App\Repository\ParcoursVersioningRepository;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\CleanTexte;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ParcoursExportController extends AbstractController
{
    public function __construct(
        private readonly MyGotenbergPdf $myPdf
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws TypeDiplomeNotFoundException
     * @throws Exception
     */
    #[Route('/parcours/{parcours}/export-pdf', name: 'app_parcours_export')]
    public function export(
        TypeDiplomeResolver $typeDiplomeResolver,
        Parcours                $parcours,
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $typeDiplomeResolver->fromTypeDiplome($typeDiplome);


        return $this->myPdf->render('pdf/parcours.html.twig', [
            'formation' => $parcours->getFormation(),
            'typeDiplome' => $typeDiplome,
            'parcours' => $parcours,
            'hasParcours' => $parcours->getFormation()?->isHasParcours(),
            'titre' => 'Détails du parcours ' . $parcours->getDisplay(),
            'dto' => $typeD->calcul($parcours),
        ], 'Parcours_' . $parcours->getDisplay());
    }

    #[Route('/parcours/{parcours}/versioning/export-pdf', name: 'app_parcours_export_pdf_versioning')]
    public function exportPdfVersioning(
        Parcours $parcours,
        ParcoursVersioningRepository $parcoursVersioningRepository,
        VersioningParcours $versioningParcours
    ): Response {
        $lastCfvuVersion = $parcoursVersioningRepository->findLastCfvuVersion($parcours);
        $lastCfvuVersion = count($lastCfvuVersion) > 0 ? $lastCfvuVersion[0] : null;

        if ($lastCfvuVersion !== null) {
            $versionData = $versioningParcours->loadParcoursFromVersion($lastCfvuVersion);
            $parcoursVersionData = $versionData['parcours'];
            $dtoVersionData = $versionData['dto'];

            return $this->myPdf->render('pdf/parcours.html.twig', [
                'formation' => $parcoursVersionData->getFormation(),
                'typeDiplome' => $parcoursVersionData->getTypeDiplome(),
                'parcours' => $parcoursVersionData,
                'hasParcours' => $parcoursVersionData->getFormation()->isHasParcours(),
                'titre' => 'Détails du parcours ' . $parcoursVersionData->getLibelle(),
                'dto' => $dtoVersionData,
                'isVersioning' => true
            ], 'Parcours_' . $parcours->getLibelle());
        }

        return $this->json(['error' => 'no valid version available'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/parcours/{parcours}/maquette/validee_cfvu/export-json', name: 'app_parcours_export_maquette_json_validee_cfvu')]
    public function exportMaquetteValideeJson(
        Parcours $parcours,
        ParcoursExport $parcoursExport,
        VersioningParcours $versioningParcours,
        ParcoursVersioningRepository $parcoursVersioningRepository
    ) : Response {
        $lastCfvuVersion = $parcoursVersioningRepository->findLastCfvuVersion($parcours);

        if(count($lastCfvuVersion) === 0){
            return $this->json(["error" => "no valid version available"]);
        }

        $versionData = $versioningParcours->loadParcoursFromVersion($lastCfvuVersion[0]);
        $parcours_id = $lastCfvuVersion[0]->getParcours()->getId();
        $formation_id = $lastCfvuVersion[0]->getParcours()->getFormation()->getId();

        $json = $parcoursExport->exportLastValidVersionMaquetteJson(
            $versionData['dto'],
            $versionData['parcours'],
            $parcours_id,
            $formation_id,
            fermetureEmpty: true
        );

        return $this->json($json);
    }

    #[Route('/parcours/{parcours}/maquette-minimum/export-json', name: 'app_parcours_export_maquette_json_minimum')]
    public function exportMaquetteJsonMinimum(Parcours $parcours) : Response {
        $volumesVide = ['presentiel' => 0, 'distanciel' => 0];

        $jsonData = [
            'path' => $this->generateUrl(
                'app_parcours_export_maquette_json_minimum', 
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'id' => $parcours->getId(),
            'formationId' => $parcours->getFormation()?->getId(),
            'formation' => $parcours->getFormation()?->getDisplay() ?? '',
            'parcours' => $parcours->isParcoursDefaut() ? '' : $parcours->getLibelle() ?? '',
            'typeDiplome' => $parcours->getFormation()?->getTypeDiplome()?->getLibelle() ?? '',
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle() ?? '',
            'volumes' => [
                'CM' => $volumesVide,
                'TD' => $volumesVide,
                'TP' => $volumesVide,
                'autonomie' => 0
            ],
            'ects' => 0,
            'semestres' => []
        ];

        return $this->json($jsonData);
    }

    #[Route('/parcours/{parcours}/maquette/export-json', name: 'app_parcours_export_maquette_json')]
    public function exportMaquetteJson(
        Parcours $parcours,
        ParcoursExport $parcoursExport,
    ): Response {
        return $this->json($parcoursExport->exportMaquetteJson($parcours));
    }
}
