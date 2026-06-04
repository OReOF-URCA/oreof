<?php

namespace App\Controller;

use App\Entity\Parcours;
use App\Service\TypeDiplomeResolver;
use App\Service\VersioningParcours;
use App\Service\VersioningStructure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StructureShowController extends AbstractController
{
    public function __construct(protected TypeDiplomeResolver $typeDiplomeResolver)
    {
    }

    #[Route('/structure/parcours/show/', name: 'app_structure_parcours_show')]
    public function parcoursShow(
        VersioningParcours $versioningParcours,
        Parcours $parcours,
        bool $hasLastVersion = false
    ): Response {

        $typeD = $this->typeDiplomeResolver->get($parcours?->getTypeDiplome());
        $dto = $typeD->calculStructureParcours($parcours);

        $structureDifferencesParcours = $versioningParcours->getStructureDifferencesBetweenParcoursAndLastCfvu($parcours);
        if ($structureDifferencesParcours !== null) {
            $diffStructure = (new VersioningStructure($structureDifferencesParcours, $dto))->calculDiff();
        }

        $diffStructureCampagnePrecedente = null;
        if($parcours->getParcoursOrigineCopie()){
            $version = $versioningParcours->getLastCfvuVersion($parcours->getParcoursOrigineCopie());
            $dtoAnneePrecedente = null;

            if ($version !== null) {
                $dtoAnneePrecedente = $versioningParcours->loadParcoursFromVersion($version)['dto'] ?? null;
            }
            if($dtoAnneePrecedente !== null){
                $diffStructureCampagnePrecedente = (new VersioningStructure($dtoAnneePrecedente, $dto))->calculDiff();
            }
        }

        if ($dto === null) {
            return $this->render('typeDiplome/formation/_structure_empty.html.twig', []);
        }

        return $this->render('typeDiplome/' . $typeD::TEMPLATE_FOLDER . '/structure/_structure.html.twig', [
            'parcours' => $parcours,
            'diffStructure' => $diffStructure ?? null,
            'diffStructureCampagne' => $diffStructureCampagnePrecedente ?? null,
            'dto' => $dto,
            'hasLastVersion' => $hasLastVersion,
        ]);
    }
}
