<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeEcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\Formation;
use App\Entity\TypeDiplome;
use App\Entity\TypeEc;
use App\Form\TypeEcType;
use App\Repository\TypeEcRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/type-ec')]
class TypeEcController extends AbstractController
{
    #[Route('/', name: 'app_type_ec_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(TypeEc::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé du type d\'EC',
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
            ->addColumn('formation', [
                'label' => 'Formation',
                'sortable' => true,
                'filterable' => true,
                'type' => 'entity',
                'entity' => Formation::class,
                'format' => 'entity_badge',
                'badge_class' => 'bg-info',
                'null_label' => 'Commune',
                'null_badge_class' => 'bg-success',
            ])
            ->addShowAction('app_type_ec_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un type d\'EC',
            ])
            ->addEditAction('app_type_ec_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier un type d\'EC',
            ])
            ->addDuplicateAction('app_type_ec_duplicate')
            ->addDeleteAction('app_type_ec_delete')
            ->build();

        return $this->render('config/type_ec/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_type_ec_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        TypeEcRepository $typeEcRepository
    ): Response
    {
        $typeEc = new TypeEc();
        $form = $this->createForm(TypeEcType::class, $typeEc, [
            'action' => $this->generateUrl('app_type_ec_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEcRepository->save($typeEc, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_ec.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'type_ec' => $typeEc,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_type_ec_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        TypeEc                     $typeEc
    ): Response
    {
        $detail = $builder
            ->setEntity(TypeEc::class)
            ->addField('libelle', [
                'label' => 'Libellé du type d\'EC',
            ])
            ->addField('typeDiplomes', [
                'label' => 'Type(s) de diplôme',
                'type' => 'collection',
                'format' => 'badges',
                'collection_property' => 'libelle',
                'badge_class' => 'bg-primary',
            ])
            ->addField('formation', [
                'label' => 'Formation (si spécifique)',
                'type' => 'entity',
                'format' => 'entity_badge',
                'entity_label' => '__toString',
                'badge_class' => 'bg-info',
                'null_label' => 'Commune',
                'null_badge_class' => 'bg-success',
            ])
            ->addField('type', [
                'label' => 'Type',
                'format' => 'enum_badge',
                'enum_label_method' => 'getLibelle',
                'enum_color_method' => 'getColor',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_ec.show.title', [], 'modal'),
            'Dans : type d\'EC ' . $typeEc->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $typeEc,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_type_ec_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        TypeEc $typeEc,
        TypeEcRepository $typeEcRepository
    ): Response
    {
        $form = $this->createForm(TypeEcType::class, $typeEc, [
            'action' => $this->generateUrl('app_type_ec_edit', ['id' => $typeEc->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEcRepository->save($typeEc, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_ec.edit.title', [], 'modal'),
            'Type d\'EC : ' . $typeEc->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'type_ec' => $typeEc,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_type_ec_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeEcRepository $typeEcRepository,
        TypeEc $typeEc
    ): Response {
        $typeEcNew = clone $typeEc;
        $typeEcNew->setLibelle($typeEc->getLibelle() . ' - Copie');
        $typeEcRepository->save($typeEcNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_ec_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeEc $typeEc,
        TypeEcRepository $typeEcRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeEc->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeEcRepository->remove($typeEc, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
