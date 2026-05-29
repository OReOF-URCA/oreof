<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/ActualiteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\Actualite;
use App\Form\ActualiteType;
use App\Repository\ActualiteRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/actualite')]
class ActualiteController extends AbstractController
{
    #[Route('/', name: 'app_actualite_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Actualite::class)
            ->setPerPage(20)
            ->setDefaultSort('datePublication', 'desc')
            ->addColumn('titre', [
                'label' => 'Titre',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('datePublication', [
                'label' => 'Date',
                'sortable' => true,
                'filterable' => true,
                'format' => 'datetime',
            ])
            ->addColumn('affiche', [
                'label' => 'Publié ?',
                'sortable' => true,
                'filterable' => true,
                'format' => 'boolean',
                'type' => 'boolean',
            ])
            ->addShowAction('app_actualite_show', [
                'modal' => true,
                'modal_title' => 'Voir une actualité',
            ])
            ->addEditAction('app_actualite_edit', [
                'modal' => true,
                'modal_title' => 'Modifier une actualité',
            ])
            ->addDuplicateAction('app_actualite_duplicate')
            ->addDeleteAction('app_actualite_delete')
            ->build();

        return $this->render('config/actualite/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_actualite_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        ActualiteRepository        $actualiteRepository
    ): Response
    {
        $actualite = new Actualite();
        $form = $this->createForm(ActualiteType::class, $actualite, [
            'action' => $this->generateUrl('app_actualite_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actualiteRepository->save($actualite, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('actualite.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'actualite' => $actualite,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_actualite_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        Actualite                  $actualite
    ): Response
    {
        $detail = $builder
            ->setEntity(Actualite::class)
            ->addField('id', ['label' => 'ID'])
            ->addField('titre', ['label' => 'Titre'])
            ->addField('datePublication', ['label' => 'Date de publication', 'format' => 'datetime'])
            ->addField('texte', ['label' => 'Texte', 'format' => 'html'])
            ->addField('affiche', ['label' => 'Affiché', 'format' => 'boolean'])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('actualite.show.title', [], 'modal'),
            'Actualité : ' . $actualite->getTitre(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $actualite,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_actualite_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        Actualite                  $actualite,
        ActualiteRepository        $actualiteRepository
    ): Response
    {
        $form = $this->createForm(ActualiteType::class, $actualite, [
            'action' => $this->generateUrl('app_actualite_edit', ['id' => $actualite->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actualiteRepository->save($actualite, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('actualite.edit.title', [], 'modal'),
            'Actualité : ' . $actualite->getTitre(),
            '_ui/_modal_new_generic.html.twig',
            [
                'actualite' => $actualite,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_actualite_duplicate', methods: ['GET'])]
    public function duplicate(
        ActualiteRepository $actualiteRepository,
        Actualite $actualite
    ): Response {
        $actualiteNew = clone $actualite;
        $actualiteNew->setTitre($actualite->getTitre() . ' - Copie');
        $actualiteRepository->save($actualiteNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_actualite_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Actualite $actualite,
        ActualiteRepository $actualiteRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $actualite->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $actualiteRepository->remove($actualite, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
