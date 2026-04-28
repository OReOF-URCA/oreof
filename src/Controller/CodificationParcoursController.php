<?php

namespace App\Controller;

use App\Entity\Parcours;
use App\Repository\ElementConstitutifRepository;
use App\Repository\SemestreRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\UeRepository;
use App\Service\TypeDiplomeResolver;
use App\TypeDiplome\Source\LicenceTypeDiplome;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CodificationParcoursController extends AbstractController
{
    #[Route('/codification/parcours/modifier/{parcours}', name: 'app_codification_parcours_modifier')]
    public function modifier(
        TypeDiplomeResolver $typeDiplomeResolver,
        EntityManagerInterface $em,
        ElementConstitutifRepository $ecRepository,
        UeRepository $ueRepository,
        SemestreRepository $semestreRepository,
        Parcours $parcours,
        Request $request
    ): Response {

        if ($request->isMethod('POST')) {
            //on parcours toutes les requests pour récupérer les données
            //selon le nom du champs on va chercher une UE ou une EC ou un Semestre
            //on met à jour le code apogée de l'objet correspondant
            //on persiste l'objet
            //on flush

            foreach ($request->request->all() as $key => $value) {
                if ($value !== 'Aucun code Apogée') {
                    //   dump($req);
                    // foreach ($req as $key => $value) {
                    $id = explode('_', $key)[1];
                    if (str_starts_with($key, 'ue') !== false) {
                        $ue = $ueRepository->find($id);
                        if ($ue !== null) {
                            if ($ue->getHasBeenEditedManually() === false && $ue->getCodeApogee() !== $value) {
                                $ue->setCodeApogee($value);
                                $ue->setHasBeenEditedManually(true);
                            }
                        }
                    } elseif (str_starts_with($key, 'ec') !== false) {
                        $ec = $ecRepository->find($id);

                        if ($ec !== null) {
                            if ($ec->getHasBeenEditedManually() === false && $ec->getCodeApogee() !== $value) {
                                $ec->setCodeApogee($value);
                                $ec->setHasBeenEditedManually(true);
                            }
                        }
                    } elseif (str_starts_with($key, 'semestre') !== false) {
                        $semestre = $semestreRepository->find($id);
                        if ($semestre !== null) {
                            if ($semestre->getHasBeenEditedManually() === false && $semestre->getCodeApogee() !== $value) {
                                $semestre->setCodeApogee($value);
                                $semestre->setHasBeenEditedManually(true);
                            }
                        }
                    }
                }
                $em->flush(); // sauvegarde à chaque itération pour éviter de perdre des données
            }

        }

        $typeD = $typeDiplomeResolver->getFromParcours($parcours);
        $dto = $typeD->calculStructureParcours($parcours, true, false);

        return $this->render('codification_parcours/modifier.html.twig', [
            'parcours' => $parcours,
            'formation' => $parcours->getFormation(),
            'dto' => $dto,
        ]);
    }
}
