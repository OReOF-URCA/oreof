<?php

declare(strict_types=1);

namespace App\Controller\Config;

use App\Controller\Traits\CsrfDeleteTrait;
use App\DataTable\AccreditationDataTable;
use App\DTO\TranslatableKey;
use App\Entity\Accreditation;
use App\Form\AccreditationType;
use App\Repository\AccreditationRepository;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/accreditation')]
class AccreditationController extends AbstractController
{
    use CsrfDeleteTrait;

    #[Route('/', name: 'app_accreditation_index', methods: ['GET', 'POST'])]
    public function index(
        AccreditationDataTable $table
    ): Response {
        return $this->render('config/accreditation/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_accreditation_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        AccreditationRepository $accreditationRepository
    ): Response {
        $accreditation = new Accreditation();
        $form = $this->createForm(AccreditationType::class, $accreditation, [
            'action' => $this->generateUrl('app_accreditation_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $accreditationRepository->save($accreditation, true);

            return $turboStream->streamToastSuccess('Période d\'accréditation créée avec succès', true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('accreditation.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            ['submitLabel' => 'Enregistrer']
        );
    }

    #[Route('/{id}/edit', name: 'app_accreditation_edit', methods: ['GET', 'POST'])]
    public function edit(
        Accreditation $accreditation,
        TurboStreamResponseFactory $turboStream,
        Request $request,
        AccreditationRepository $accreditationRepository
    ): Response {
        $form = $this->createForm(AccreditationType::class, $accreditation, [
            'action' => $this->generateUrl('app_accreditation_edit', ['id' => $accreditation->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $accreditationRepository->save($accreditation, true);

            return $turboStream->streamToastSuccess('Période d\'accréditation modifiée avec succès', true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('accreditation.edit.title', [], 'modal'),
            'Période ' . $accreditation->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            ['submitLabel' => 'Enregistrer']
        );
    }

    #[Route('/{id}', name: 'app_accreditation_delete', methods: ['DELETE'])]
    public function delete(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        Accreditation $accreditation,
        AccreditationRepository $accreditationRepository
    ): Response {
        if ($this->isDeleteTokenValid($accreditation, $this->getCsrfTokenFromRequest($request))) {
            $accreditationRepository->remove($accreditation, true);

            return $turboStream->streamToastSuccess('Période d\'accréditation supprimée avec succès', true);
        }

        return $turboStream->streamToastError('Erreur lors de la suppression', true);
    }
}
