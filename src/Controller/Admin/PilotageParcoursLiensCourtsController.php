<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DataTable\PilotageParcoursLiensCourtsDataTable;
use App\DTO\TranslatableKey;
use App\Entity\Parcours;
use App\Form\Admin\PilotageParcoursLienCourtType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\ParcoursRepository;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/pilotage-liens-courts', name: 'app_admin_pilotage_liens_courts_')]
#[IsGranted('ROLE_ADMIN')]
class PilotageParcoursLiensCourtsController extends BaseController
{
    public function __construct(
        private readonly ParcoursRepository         $parcoursRepository,
        private readonly TurboStreamResponseFactory $turboStream,
        private readonly EntityManagerInterface     $entityManager,
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        PilotageParcoursLiensCourtsDataTable $table,
        Request                              $request,
    ): Response
    {
        $selectedCampagne = $this->dataUserSession->getCampagneCollecte();
        $stats = $this->parcoursRepository->getStatsLiensCourtsByCampagne($selectedCampagne);

        return $this->render('admin/pilotage/liens_courts/index.html.twig', [
            'table' => $table,
            'selectedCampagne' => $selectedCampagne,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request  $request,
        Parcours $parcours,
    ): Response
    {
        $form = $this->createForm(PilotageParcoursLienCourtType::class, $parcours, [
            'action' => $this->generateUrl('app_admin_pilotage_liens_courts_edit', ['id' => $parcours->getId()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            if ($request->getPreferredFormat() === 'turbo-stream' || $request->headers->get('Turbo-Frame') !== null || $request->isXmlHttpRequest()) {
                return $this->turboStream->streamToastSuccess('Lien court du parcours mis à jour avec succès.', true);
            }

            $this->addFlash('success', 'Lien court du parcours mis à jour avec succès.');
            return $this->redirectToRoute('app_admin_pilotage_liens_courts_index');
        }

        if ($request->getPreferredFormat() !== 'turbo-stream' && $request->headers->get('Turbo-Frame') === null && !$request->isXmlHttpRequest()) {
            return $this->render('admin/pilotage/liens_courts/edit.html.twig', [
                'parcours' => $parcours,
                'form' => $form->createView(),
            ]);
        }

        return $this->turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('parcours_liens_courts.edit.title', [], 'modal'),
            $parcours->getFormation()?->getDisplayLong() ?: $parcours->getFormation()?->getDisplay(),
            'admin/pilotage/liens_courts/_modal_edit.html.twig',
            [
                'parcours' => $parcours,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer',
            ]
        );
    }
}
