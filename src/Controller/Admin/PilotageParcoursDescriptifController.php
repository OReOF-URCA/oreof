<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DataTable\PilotageParcoursDescriptifDataTable;
use App\DTO\TranslatableKey;
use App\Entity\Parcours;
use App\Form\Admin\PilotageParcoursDescriptifType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\ParcoursRepository;
use App\Service\Parcours\ParcoursDescriptifAutomatiqueGenerator;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/pilotage-descriptifs', name: 'app_admin_pilotage_descriptifs_')]
#[IsGranted('ROLE_ADMIN')]
class PilotageParcoursDescriptifController extends BaseController
{
    public function __construct(
        private readonly ParcoursRepository                     $parcoursRepository,
        private readonly TurboStreamResponseFactory             $turboStream,
        private readonly ParcoursDescriptifAutomatiqueGenerator $descriptifGenerator,
        private readonly EntityManagerInterface                 $entityManager,
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    #[Route('', name: 'app_admin_pilotage_parcours_descriptifs', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'pilotage')]
    #[Breadcrumb(label: 'menu.admin.descriptifs_parcours')]
    public function index(
        PilotageParcoursDescriptifDataTable $table,
        Request                             $request,
    ): Response
    {
        $selectedCampagne = $this->dataUserSession->getCampagneCollecte();
        $stats = $this->parcoursRepository->getStatsDescriptifsByCampagne($selectedCampagne);

        return $this->render('admin/pilotage/descriptifs/index.html.twig', [
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
        $campagne = $this->dataUserSession->getCampagneCollecte();
        $form = $this->createForm(PilotageParcoursDescriptifType::class, $parcours, [
            'action' => $this->generateUrl('app_admin_pilotage_descriptifs_edit', ['id' => $parcours->getId()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            if ($request->getPreferredFormat() === 'turbo-stream' || $request->headers->get('Turbo-Frame') !== null || $request->isXmlHttpRequest()) {
                return $this->turboStream->streamToastSuccess('Descriptif du parcours mis à jour avec succès.', true);
            }

            $this->addFlash('success', 'Descriptif du parcours mis à jour avec succès.');
            return $this->redirectToRoute('app_admin_pilotage_descriptifs_index');
        }

        $suggestedAuto = $this->descriptifGenerator->computeDescriptif($parcours, $campagne);

        if ($request->getPreferredFormat() !== 'turbo-stream' && $request->headers->get('Turbo-Frame') === null && !$request->isXmlHttpRequest()) {
            return $this->render('admin/pilotage/descriptifs/edit.html.twig', [
                'parcours' => $parcours,
                'form' => $form->createView(),
                'suggestedAuto' => $suggestedAuto,
            ]);
        }

        return $this->turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('parcours_descriptifs.edit.title', [], 'modal'),
            $parcours->getFormation()?->getDisplayLong() ?: $parcours->getFormation()?->getDisplay(),
            'admin/pilotage/descriptifs/_modal_edit.html.twig',
            [
                'parcours' => $parcours,
                'form' => $form->createView(),
                'suggestedAuto' => $suggestedAuto,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer',
            ]
        );
    }
}
