<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/API/EctsController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\API;

use App\Classes\GetUeEcts;
use App\Entity\Parcours;
use App\Entity\Ue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EctsController extends AbstractController
{
    #[Route('/api/ects/ue/{ue}/{parcours}', name: 'api_ects_ue')]
    #[IsGranted('ROLE_USER')]
    public function getComposante(
        Ue       $ue,
        Parcours $parcours,
    ): Response {
        $ectsSemestre = 0;
        $typeDiplome = $parcours->getTypeDiplome();
        $semestre = $ue->getSemestre();

        if ($semestre === null || $typeDiplome === null) {
            throw $this->createNotFoundException();
        }
        $totalEctsUe = GetUeEcts::getEcts($ue, $parcours, $typeDiplome);

        $uesInSemestre = $semestre->getUes();

        foreach ($uesInSemestre as $u) {
            if ($u->getUeParent() === null) {
                $ectsSemestre += GetUeEcts::getEcts($u, $parcours, $typeDiplome);
            }
        }
        return $this->json(
            [
                'ue' => $totalEctsUe,
                'semestre' => $ectsSemestre,
                'idSemestre' => $semestre->getId(),
            ]
        );
    }
}
