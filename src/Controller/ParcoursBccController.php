<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Entity\Parcours;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use App\Repository\ButApprentissageCritiqueRepository;
use App\Repository\CompetenceRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Utils\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParcoursBccController extends BaseController
{
    #[Route('/parcours/bcc/{parcours}', name: 'app_parcours_bcc')]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function index(
        Parcours          $parcours,
        Request $request,
        BreadcrumbService $breadcrumb
    ): Response
    {
        if ($parcours->getFormation() !== null) {
            $breadcrumb->add(
                $parcours->getFormation()->getDisplay(),
                'formation_v2_voir',
                ['slug' => $parcours->getFormation()->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_voir',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('BCC');

        $typeD = $this->typeDiplomeResolver->fromParcours($parcours);
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        $editable = $this->isGranted('EDIT', $parcours) && Access::isTabEditable($dpeParcours, 'competences');

        $bccData = $typeD->getBccCroiseData($parcours);
        $bccTemplate = $typeD->getBccCroiseTemplate();

        $semestresDisponibles = $bccData['semestresDisponibles'] ?? [];
        $firstSemestre = !empty($semestresDisponibles) ? (int)array_key_first($semestresDisponibles) : 1;
        $activeSemestre = $request->query->getInt('semestre', $firstSemestre);

        return $this->render('parcours_bcc/index.html.twig', [
            'parcours' => $parcours,
            'typeD' => $typeD,
            'dto' => $bccData['dto'] ?? null,
            'bccData' => $bccData,
            'bccTemplate' => $bccTemplate,
            'activeSemestre' => $activeSemestre,
            'editable' => $editable,
            'dpeParcours' => $dpeParcours,
        ]);
    }

    #[Route('/parcours/bcc/{parcours}/semestre/{semestre}', name: 'app_parcours_bcc_semestre', methods: ['GET'])]
    public function semestreTable(
        Parcours $parcours,
        int      $semestre,
        Request  $request
    ): Response
    {
        $typeD = $this->typeDiplomeResolver->fromParcours($parcours);
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        $editable = $this->isGranted('EDIT', $parcours) && Access::isTabEditable($dpeParcours, 'competences');

        $bccData = $typeD->getBccCroiseData($parcours, $semestre);
        $template = $typeD->getBccCroiseSemestreTemplate();

        $sData = $bccData['semestresData'][$semestre] ?? null;

        return $this->render($template, [
            'parcours' => $parcours,
            'typeD' => $typeD,
            'semOrdre' => $semestre,
            'sData' => $sData,
            'bccData' => $bccData,
            'dto' => $bccData['dto'] ?? null,
            'editable' => $editable,
            'dpeParcours' => $dpeParcours,
        ]);
    }

    #[Route('/parcours/bcc-but/{parcours}', name: 'app_parcours_bcc_but')]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function bccBut(
        Parcours          $parcours,
        Request $request,
        BreadcrumbService $breadcrumb
    ): Response
    {
        return $this->index($parcours, $request, $breadcrumb);
    }

    #[Route('/parcours/bcc/{parcours}/update-competence', name: 'app_parcours_bcc_update_competence', methods: ['POST'])]
    public function updateCompetence(
        EntityManagerInterface       $em,
        ElementConstitutifRepository $ecRepository,
        CompetenceRepository         $competenceRepository,
        Request                      $request,
        Parcours                     $parcours
    ): Response {
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        if (!$this->isGranted('EDIT', $parcours) || !Access::isTabEditable($dpeParcours, 'competences')) {
            return JsonReponse::error('Modification non autorisée dans l\'état actuel du parcours');
        }

        $ec = $ecRepository->find($request->request->get('ec'));
        $competence = $competenceRepository->find($request->request->get('competence'));
        if ($competence === null || $ec === null) {
            return JsonReponse::error('EC ou compétence introuvable');
        }

        if ($ec->getFicheMatiere() === null) {
            return JsonReponse::error('EC sans fiche matière');
        }

        if ($request->request->get('checked') === 'true') {
            if ($ec->getFicheMatiere()->getParcours() === $parcours) {
                $ec->getFicheMatiere()->addCompetence($competence);
            } else {
                $ec->addCompetence($competence);
            }
        } else {
            if ($ec->getFicheMatiere()->getParcours() === $parcours) {
                $ec->getFicheMatiere()->removeCompetence($competence);
            } else {
                $ec->removeCompetence($competence);
            }
        }

        $em->flush();
        return JsonReponse::success('Lien compétence/EC mis à jour');
    }

    #[Route('/parcours/bcc-but/{parcours}/update-competence', name: 'app_parcours_bcc_but_update_competence', methods: ['POST'])]
    public function updateButCompetence(
        EntityManagerInterface             $em,
        FicheMatiereRepository             $ficheMatiereRepository,
        ButApprentissageCritiqueRepository $butApprentissageCritiqueRepository,
        Request                            $request,
        Parcours $parcours
    ): Response {
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        if (!$this->isGranted('EDIT', $parcours) || !Access::isTabEditable($dpeParcours, 'competences')) {
            return JsonReponse::error('Modification non autorisée dans l\'état actuel du parcours');
        }

        $fiche = $ficheMatiereRepository->find($request->request->get('ec'));
        $ac = $butApprentissageCritiqueRepository->find($request->request->get('competence'));
        if ($ac === null || $fiche === null) {
            return JsonReponse::error('EC ou apprentissage critique introuvable');
        }

        if ($request->request->get('checked') === 'true') {
            $fiche->addApprentissagesCritique($ac);
        } else {
            $fiche->removeApprentissagesCritique($ac);
        }

        $em->flush();
        return JsonReponse::success('Lien Apprentissage critique/Ressource ou SAE mis à jour');
    }
}
