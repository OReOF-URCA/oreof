<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\MentionProcess;
use App\Classes\Process\FicheMatiereProcess;
use App\Classes\Process\ParcoursProcess;
use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessFicheMatiere;
use App\Classes\verif\FormationValide;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use App\Enums\TypeModificationDpeEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProcessValidationMentionController extends BaseController
{
    private string $dir;

    public function __construct(
        private readonly EventDispatcherInterface      $eventDispatcher,
        private readonly EntityManagerInterface        $entityManager,
        private readonly ValidationProcess             $validationProcess,
        private readonly ValidationProcessFicheMatiere $validationProcessFicheMatiere,
        private readonly ParcoursProcess               $parcoursProcess,
        private readonly MentionProcess $mentionProcess,
        private readonly FicheMatiereProcess           $ficheMatiereProcess,
        KernelInterface                                $kernel
    ) {
        $this->dir = $kernel->getProjectDir() . '/public/uploads/conseils/';
    }

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
            $histo->setUser($this->getUser());

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
