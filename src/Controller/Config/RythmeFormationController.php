<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/RythmeFormationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\RythmeFormation;
use App\Form\RythmeFormationType;
use App\Repository\RythmeFormationRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/rythme/formation')]
class RythmeFormationController extends AbstractController
{
    #[Route('/', name: 'app_rythme_formation_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {

        $table = $builder
            ->setEntity(RythmeFormation::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé du rythme de formation',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addShowAction('app_rythme_formation_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un rythme de formation',
            ])
            ->addEditAction('app_rythme_formation_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier un rythme de formation',
            ])
            ->addDuplicateAction('app_rythme_formation_duplicate')
            ->addDeleteAction('app_rythme_formation_delete')
            ->build();
        return $this->render('config/rythme_formation/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_rythme_formation_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        RythmeFormationRepository  $rythmeFormationRepository
    ): Response
    {
        $rythmeFormation = new RythmeFormation();
        $form = $this->createForm(RythmeFormationType::class, $rythmeFormation, [
            'action' => $this->generateUrl('app_rythme_formation_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rythmeFormationRepository->save($rythmeFormation, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('rythme_formation.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'rythme_formation' => $rythmeFormation,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_rythme_formation_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        RythmeFormation            $rythmeFormation
    ): Response
    {
        $detail = $builder
            ->setEntity(RythmeFormation::class)
            ->addField('libelle', ['label' => 'Libellé du rythme de formation'])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('rythme_formation.show.title', [], 'modal'),
            'Rythme de formation : ' . $rythmeFormation->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            ['entity' => $rythmeFormation, 'detail' => $detail],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_rythme_formation_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        RythmeFormation $rythmeFormation,
        RythmeFormationRepository $rythmeFormationRepository
    ): Response {
        $form = $this->createForm(RythmeFormationType::class, $rythmeFormation, [
            'action' => $this->generateUrl('app_rythme_formation_edit', ['id' => $rythmeFormation->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rythmeFormationRepository->save($rythmeFormation, true);

            return $this->json(true);
        }


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('rythme_formation.edit.title', [], 'modal'),
            'Rythme de formation : ' . $rythmeFormation->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'rythme_formation' => $rythmeFormation,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_rythme_formation_duplicate', methods: ['GET'])]
    public function duplicate(
        RythmeFormationRepository $rythmeFormationRepository,
        RythmeFormation $rythmeFormation
    ): Response {
        $rythmeFormationNew = clone $rythmeFormation;
        $rythmeFormationNew->setLibelle($rythmeFormation->getLibelle() . ' - Copie');
        $rythmeFormationRepository->save($rythmeFormationNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_rythme_formation_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        RythmeFormation $rythmeFormation,
        RythmeFormationRepository $rythmeFormationRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $rythmeFormation->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $rythmeFormationRepository->remove($rythmeFormation, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
