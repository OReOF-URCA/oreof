<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/VilleController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\Ville;
use App\Form\VilleType;
use App\Repository\VilleRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/ville')]
class VilleController extends AbstractController
{
    #[Route('/', name: 'app_ville_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Ville::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé de la ville',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeApogee', [
                'label' => 'Code Apogée',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addShowAction('app_ville_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir une ville',
            ])
            ->addEditAction('app_ville_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier une ville',
            ])
            ->addDuplicateAction('app_ville_duplicate')
            ->addDeleteAction('app_ville_delete')
            ->build();
        return $this->render('config/ville/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_ville_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        VilleRepository            $villeRepository
    ): Response
    {
        $ville = new Ville();
        $form = $this->createForm(VilleType::class, $ville, [
            'action' => $this->generateUrl('app_ville_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $villeRepository->save($ville, true);
            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('ville.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'ville' => $ville,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_ville_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        Ville                      $ville
    ): Response
    {
        $detail = $builder
            ->setEntity(Ville::class)
            ->addField('libelle', ['label' => 'Libellé'])
            ->addField('codeApogee', ['label' => 'Code Apogée', 'empty_text' => 'Non renseigné'])
            ->addField('etablissement', [
                'label' => 'Établissement',
                'type' => 'entity',
                'format' => 'entity_badge',
                'entity_label' => 'libelle',
                'badge_class' => 'bg-info',
                'null_label' => 'Non défini',
                'null_badge_class' => 'bg-secondary',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('ville.show.title', [], 'modal'),
            'Ville : ' . $ville->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $ville,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_ville_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        Ville                      $ville,
        VilleRepository            $villeRepository
    ): Response
    {
        $form = $this->createForm(VilleType::class, $ville, [
            'action' => $this->generateUrl('app_ville_edit', ['id' => $ville->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $villeRepository->save($ville, true);
            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('ville.edit.title', [], 'modal'),
            'Ville : ' . $ville->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'ville' => $ville,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_ville_duplicate', methods: ['GET'])]
    public function duplicate(
        VilleRepository $villeRepository,
        Ville $ville
    ): Response {
        $villeNew = clone $ville;
        $villeNew->setLibelle($ville->getLibelle() . ' - Copie');
        $villeRepository->save($villeNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_ville_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Ville $ville,
        VilleRepository $villeRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $ville->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $villeRepository->remove($ville, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
