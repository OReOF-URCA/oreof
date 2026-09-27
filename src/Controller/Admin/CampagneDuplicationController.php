<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Campagne\CampagneDuplicationDTO;
use App\Form\Admin\CampagneDuplicationType;
use App\Repository\AnneeUniversitaireRepository;
use App\Repository\CampagneCollecteRepository;
use App\Service\Campagne\CampagneDuplicationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/duplication-campagne')]
#[IsGranted('ROLE_ADMIN')]
class CampagneDuplicationController extends BaseController
{
    public function __construct(
        private readonly CampagneDuplicationService $campagneDuplicationService,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
        private readonly AnneeUniversitaireRepository $anneeUniversitaireRepository,
    ) {
    }

    #[Route('', name: 'app_admin_duplication_campagne_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $allCampagnes = $this->campagneCollecteRepository->findBy([], ['id' => 'DESC']);
        $sourceId = $request->query->getInt('source_id', 0);

        $sourceCampagne = null;
        if ($sourceId > 0) {
            $sourceCampagne = $this->campagneCollecteRepository->find($sourceId);
        }

        if ($sourceCampagne === null) {
            $sourceCampagne = $this->dataUserSession->getCampagneCollecte() ?? ($allCampagnes[0] ?? null);
        }

        if ($sourceCampagne === null) {
            $this->addFlash('warning', 'Aucune campagne de collecte disponible comme source.');
            return $this->redirectToRoute('app_section_administration');
        }

        // Audit pré-bascule
        $audit = $this->campagneDuplicationService->audit($sourceCampagne);

        // Préparation du DTO avec propositions automatiques basées sur la source
        $dto = new CampagneDuplicationDTO();
        $dto->sourceCampagne = $sourceCampagne;

        $sourceYear = $sourceCampagne->getAnnee() ?? (int) date('Y');
        $targetYear = $sourceYear + 1;
        $targetNextYear = $targetYear + 1;

        $dto->libelleAnneeUniversitaire = sprintf('%d-%d', $targetYear, $targetNextYear);
        $dto->anneeUniversitaire = $targetYear;
        $dto->libelleCampagne = sprintf('%d-%d', $targetYear, $targetNextYear);
        $dto->anneeCampagne = $targetYear;
        $dto->slugSuffix = '-' . $targetYear;
        $dto->codeApogeeCampagne = (string) ($sourceCampagne->getCodeApogee() ?? '6');
        $dto->couleur = 'primary';

        $form = $this->createForm(CampagneDuplicationType::class, $dto);
        $form->handleRequest($request);

        $simulationResult = null;
        $duplicationResult = null;

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->addFlash('danger', 'Veuillez corriger les erreurs dans le formulaire.');
            } else {
                // Si changement de source demandé via le form sans soumettre d'action
                if ($request->request->has('change_source')) {
                    return $this->redirectToRoute('app_admin_duplication_campagne_index', [
                        'source_id' => $dto->sourceCampagne->getId(),
                    ]);
                }

                // Mode Simulation (Dry Run)
                if ($request->request->has('simulate') || $request->query->has('simulate')) {
                    $selectedSource = $dto->sourceCampagne ?? $sourceCampagne;
                    $simulationAudit = $this->campagneDuplicationService->audit($selectedSource);
                    $existingCampagne = $this->campagneCollecteRepository->findOneBy(['libelle' => (string) $dto->libelleCampagne]);
                    $existingAnnee = $this->anneeUniversitaireRepository->findOneBy(['libelle' => (string) $dto->libelleAnneeUniversitaire]);

                    $simulationResult = [
                        'audit' => $simulationAudit,
                        'dto' => $dto,
                        'sourceCampagne' => $selectedSource,
                        'existingCampagne' => $existingCampagne,
                        'existingAnnee' => $existingAnnee,
                    ];
                }

                // Mode Exécution réelle
                if ($request->request->has('execute')) {
                    $duplicationResult = $this->campagneDuplicationService->duplicate($dto);
                    if ($duplicationResult->success) {
                        $this->addFlash('success', sprintf(
                            'La campagne %s a été dupliquée avec succès ! (%d éléments créés en %s s)',
                            $duplicationResult->targetCampagne?->getLibelle(),
                            $duplicationResult->getTotalCreated(),
                            $duplicationResult->executionTimeSeconds
                        ));

                        return $this->render('admin/duplication/result.html.twig', [
                            'result' => $duplicationResult,
                            'sourceCampagne' => $dto->sourceCampagne,
                        ]);
                    } else {
                        $this->addFlash('danger', 'Des erreurs sont survenues pendant la duplication de la campagne.');
                    }
                }
            }
        }

        return $this->render('admin/duplication/index.html.twig', [
            'allCampagnes' => $allCampagnes,
            'sourceCampagne' => $sourceCampagne,
            'audit' => $audit,
            'form' => $form->createView(),
            'simulationResult' => $simulationResult,
            'duplicationResult' => $duplicationResult,
        ]);
    }
}
