<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\verif\FormationValide;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use App\Entity\User;
use App\Enums\TypeModificationDpeEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProcessValidationMentionController extends BaseController
{
    #[Route('/validation-mention/valide/{etape}/{formation}', name: 'app_validation_formation_valide')]
    public function valide(
        EntityManagerInterface $entityManager,
        Formation $formation,
        string                 $etape,
        Request                $request
    ): Response {
        $valideFormation = new FormationValide($formation);
        if ($request->isMethod('POST') && $valideFormation->isFormationValide()) {
            $formation->setEtatReconduction(TypeModificationDpeEnum::FORMATION_SOUMIS_SES);
            //creation de l'historique
            $histo = new HistoriqueFormation();
            $histo->setFormation($formation);
            $histo->setDate(new DateTime());
            $histo->setEtape($etape);
            $histo->setEtat('valide');
            $currentUser = $this->getUser();
            if (!$currentUser instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $histo->setUser($currentUser);

            $entityManager->persist($histo);
            $entityManager->flush();

            return JsonReponse::success('La formation a été validée avec succès.');
        }


        return $this->render('process_validation_mention/_valide.html.twig', [
            'formation' => $formation,
            'valide' => $valideFormation->valideFormation(),
            'isValid' => $valideFormation->isFormationValide(),
            'etape' => $etape,
        ]);
    }
}
