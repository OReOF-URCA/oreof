<?php

namespace App\Controller;

use App\Entity\Parcours;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ButCorrectionController extends AbstractController
{
    #[Route('/but/correction/{parcours}', name: 'app_but_correction')]
    public function index(
        Parcours $parcours
    ): Response
    {
        $tableau = [];
        foreach ($parcours->getSemestreParcours() as $sem) {
            $tableau[$sem->getSemestre()?->getId()] = [];
            foreach ($sem->getSemestre()?->getUes() as $ue) {
                foreach ($ue->getElementConstitutifs() as $ec) {
                    $code = $ec->getFicheMatiere()?->getSigle();
                    if (!array_key_exists($code, $tableau[$sem->getSemestre()?->getId()])) {
                        $tableau[$sem->getSemestre()?->getId()][$code] = [];
                    }

                    $tableau[$sem->getSemestre()?->getId()][$code][] = $ec;
                }
            }
        }


        return $this->render('but_correction/index.html.twig', [
            'parcours' => $parcours,
            'tableau' => $tableau,
        ]);
    }
}
