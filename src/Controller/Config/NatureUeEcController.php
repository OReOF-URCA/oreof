<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/NatureUeEcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\NatureUeEc;
use App\Form\NatureUeEcType;
use App\Repository\NatureUeEcRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/nature-ue-ec')]
class NatureUeEcController extends AbstractController
{
    #[Route('/', name: 'app_nature_ue_ec_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(NatureUeEc::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('choix', [
                'label' => 'Choix ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('libre', [
                'label' => 'Libre ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addShowAction('app_nature_ue_ec_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir une nature d\'UE ou d\'EC',
            ])
            ->addEditAction('app_nature_ue_ec_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier une nature d\'UE ou d\'EC',
            ])
            ->addDuplicateAction('app_nature_ue_ec_duplicate')
            ->addDeleteAction('app_nature_ue_ec_delete')
            ->build();

        return $this->render('config/nature_ue_ec/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_nature_ue_ec_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request                    $request, NatureUeEcRepository $natureUeEcRepository): Response
    {
        $natureUeEc = new NatureUeEc();
        $form = $this->createForm(NatureUeEcType::class, $natureUeEc, [
            'action' => $this->generateUrl('app_nature_ue_ec_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $natureUeEcRepository->save($natureUeEc, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('nature_ue_ec.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'nature_ue_ec' => $natureUeEc,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_nature_ue_ec_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        NatureUeEc                 $natureUeEc
    ): Response
    {
        $detail = $builder
            ->setEntity(NatureUeEc::class)
            ->addField('libelle', ['label' => 'Libellé'])
            ->addField('descriptionCourte', [
                'label' => 'Description courte',
                'empty_text' => 'Non renseignée',
            ])
            ->addField('icone', [
                'label' => 'Icône',
                'empty_text' => 'Non renseignée',
            ])
            ->addField('choix', [
                'label' => 'Choix ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('libre', [
                'label' => 'Libre ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('nature_ue_ec.show.title', [], 'modal'),
            'Nature UE/EC : ' . $natureUeEc->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            ['entity' => $natureUeEc, 'detail' => $detail],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_nature_ue_ec_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request                    $request, NatureUeEc $natureUeEc, NatureUeEcRepository $natureUeEcRepository): Response
    {
        $form = $this->createForm(NatureUeEcType::class, $natureUeEc, [
            'action' => $this->generateUrl('app_nature_ue_ec_edit', ['id' => $natureUeEc->getId()]),

        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $natureUeEcRepository->save($natureUeEc, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('nature_ue_ec.edit.title', [], 'modal'),
            'Nature UE ou EC : ' . $natureUeEc->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'nature_ue_ec' => $natureUeEc,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }


    #[Route('/{id}/duplicate', name: 'app_nature_ue_ec_duplicate', methods: ['GET'])]
    public function duplicate(
        NatureUeEcRepository $natureUeEcRepository,
        NatureUeEc $natureUeEc
    ): Response {
        $natureUeEcNew = clone $natureUeEc;
        $natureUeEcNew->setLibelle($natureUeEc->getLibelle() . ' - Copie');
        $natureUeEcRepository->save($natureUeEcNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_nature_ue_ec_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        NatureUeEc $natureUeEc,
        NatureUeEcRepository $natureUeEcRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $natureUeEc->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $natureUeEcRepository->remove($natureUeEc, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
