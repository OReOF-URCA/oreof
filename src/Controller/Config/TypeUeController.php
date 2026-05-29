<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeUeController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\TypeDiplome;
use App\Entity\TypeUe;
use App\Form\TypeUeType;
use App\Repository\TypeUeRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/type-ue')]
class TypeUeController extends AbstractController
{
    #[Route('/', name: 'app_type_ue_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(TypeUe::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé du type d\'UE',
                'sortable' => true,
                'filterable' => true,
            ])

            // Colonne avec collection de relations
            ->addColumn('typeDiplomes', [
                'label' => 'Type(s) de diplôme',
                'filterable' => true,
                'type' => 'collection',
                'format' => 'badges',
                'collection_property' => 'libelle',
                'badge_class' => 'bg-primary',
                'entity' => TypeDiplome::class,
                'entity_label' => 'libelle',
                'searchable' => false,
            ])
            ->addShowAction('app_type_ue_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un type d\'UE',
            ])
            ->addEditAction('app_type_ue_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier un type d\'UE',
            ])
            ->addDuplicateAction('app_type_ue_duplicate')
            ->addDeleteAction('app_type_ue_delete')
            ->build();

        return $this->render('config/type_ue/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_type_ue_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        TypeUeRepository $typeUeRepository
    ): Response
    {
        $typeUe = new TypeUe();
        $form = $this->createForm(TypeUeType::class, $typeUe, [
            'action' => $this->generateUrl('app_type_ue_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeUeRepository->save($typeUe, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_ue.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'type_ue' => $typeUe,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_type_ue_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        TypeUe                     $typeUe): Response
    {
        $detail = $builder
            ->setEntity(TypeUe::class)
            ->addField('libelle', [
                'label' => 'Libellé du type d\'UE',
            ])
            ->addField('typeDiplomes', [
                'label' => 'Type(s) de diplôme',
                'type' => 'collection',
                'format' => 'badges',
                'collection_property' => 'libelle',
            ])
            ->addField('type', [
                'label' => 'Type',
                'format' => 'enum_badge',
                'enum_label_method' => 'getLibelle',
                'enum_color_method' => 'getBadge',
            ])
            ->build();


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_ue.show.title', [], 'modal'),
            'Dans : type d\'UE ' . $typeUe->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $typeUe,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_type_ue_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        TypeUe $typeUe,
        TypeUeRepository $typeUeRepository
    ): Response
    {
        $form = $this->createForm(TypeUeType::class, $typeUe, [
            'action' => $this->generateUrl('app_type_ue_edit', ['id' => $typeUe->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeUeRepository->save($typeUe, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_ue.edit.title', [], 'modal'),
            'Type d\'UE : ' . $typeUe->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'type_ue' => $typeUe,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_type_ue_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeUeRepository $typeUeRepository,
        TypeUe $typeUe
    ): Response {
        $typeUeNew = clone $typeUe;
        $typeUeNew->setLibelle($typeUe->getLibelle() . ' - Copie');
        $typeUeRepository->save($typeUeNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_ue_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeUe $typeUe,
        TypeUeRepository $typeUeRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeUe->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeUeRepository->remove($typeUe, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
