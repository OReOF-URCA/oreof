<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeEpreuveController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\TypeDiplome;
use App\Entity\TypeEpreuve;
use App\Form\TypeEpreuveType;
use App\Repository\TypeEpreuveRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/type-epreuve')]
class TypeEpreuveController extends AbstractController
{
    #[Route('/', name: 'app_type_epreuve_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(TypeEpreuve::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé du type d\'épreuve',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('sigle', [
                'label' => 'Code',
                'sortable' => true,
                'filterable' => true,
            ])
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
            ->addColumn('hasDuree', [
                'label' => 'Avec durée ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasJustification', [
                'label' => 'Avec justification ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addShowAction('app_type_epreuve_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un type d\'épreuve',
            ])
            ->addEditAction('app_type_epreuve_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier un type d\épreuve',
            ])
            ->addDuplicateAction('app_type_epreuve_duplicate')
            ->addDeleteAction('app_type_epreuve_delete')
            ->build();

        return $this->render(
            'config/type_epreuve/index.html.twig',
            [
                'table' => $table,
            ]
        );
    }

    #[Route('/new', name: 'app_type_epreuve_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        TypeEpreuveRepository $typeEpreuveRepository
    ): Response
    {
        $typeEpreuve = new TypeEpreuve();
        $form = $this->createForm(TypeEpreuveType::class, $typeEpreuve, [
            'action' => $this->generateUrl('app_type_epreuve_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEpreuveRepository->save($typeEpreuve, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_epreuve.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'type_epreuve' => $typeEpreuve,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_type_epreuve_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        TypeEpreuve                $typeEpreuve
    ): Response
    {
        $detail = $builder
            ->setEntity(TypeEpreuve::class)
            ->addField('libelle', ['label' => 'Libellé du type d\'épreuve'])
            ->addField('sigle', [
                'label' => 'Sigle',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('typeDiplomes', [
                'label' => 'Type(s) de diplôme',
                'type' => 'collection',
                'format' => 'badges',
                'collection_property' => 'libelle',
                'badge_class' => 'bg-primary',
            ])
            ->addField('hasDuree', [
                'label' => 'Durée ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasJustification', [
                'label' => 'Justification ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_epreuve.show.title', [], 'modal'),
            'Type d\'épreuve : ' . $typeEpreuve->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            ['entity' => $typeEpreuve, 'detail' => $detail],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_type_epreuve_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        TypeEpreuve $typeEpreuve,
        TypeEpreuveRepository $typeEpreuveRepository
    ): Response
    {
        $form = $this->createForm(TypeEpreuveType::class, $typeEpreuve, [
            'action' => $this->generateUrl('app_type_epreuve_edit', ['id' => $typeEpreuve->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEpreuveRepository->save($typeEpreuve, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_epreuve.edit.title', [], 'modal'),
            'Type d\'épreuve : ' . $typeEpreuve->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'type_epreuve' => $typeEpreuve,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_type_epreuve_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeEpreuveRepository $typeEpreuveRepository,
        TypeEpreuve $typeEpreuve
    ): Response {
        $typeEpreuveNew = clone $typeEpreuve;
        $typeEpreuveNew->setLibelle($typeEpreuve->getLibelle() . ' - Copie');
        $typeEpreuveRepository->save($typeEpreuveNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_epreuve_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeEpreuve $typeEpreuve,
        TypeEpreuveRepository $typeEpreuveRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeEpreuve->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeEpreuveRepository->remove($typeEpreuve, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
