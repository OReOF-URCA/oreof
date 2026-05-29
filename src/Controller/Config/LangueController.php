<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/LangueController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\Langue;
use App\Form\LangueType;
use App\Repository\LangueRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/langue')]
class LangueController extends AbstractController
{
    #[Route('/', name: 'app_langue_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Langue::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé de la langue',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeIso', [
                'label' => 'Code ISO',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addShowAction('app_langue_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir une langue',
            ])
            ->addEditAction('app_langue_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier une langue',
            ])
            ->addDuplicateAction('app_langue_duplicate')
            ->addDeleteAction('app_langue_delete')
            ->build();
        return $this->render('config/langue/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_langue_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory $turboStream,
        Request                    $request, LangueRepository $langueRepository): Response
    {
        $langue = new Langue();
        $form = $this->createForm(LangueType::class, $langue, [
            'action' => $this->generateUrl('app_langue_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $langueRepository->save($langue, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('langue.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'langue' => $langue,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_langue_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        Langue                     $langue
    ): Response
    {
        $detail = $builder
            ->setEntity(Langue::class)
            ->addField('libelle', ['label' => 'Libellé de la langue'])
            ->addField('codeIso', [
                'label' => 'Code ISO',
                'empty_text' => 'Non renseigné',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('langue.show.title', [], 'modal'),
            'Langue : ' . $langue->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            ['entity' => $langue, 'detail' => $detail],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_langue_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request                    $request, Langue $langue, LangueRepository $langueRepository): Response
    {
        $form = $this->createForm(LangueType::class, $langue, [
            'action' => $this->generateUrl('app_langue_edit', ['id' => $langue->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $langueRepository->save($langue, true);

            return $this->json(true);
        }


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('composante.edit.title', [], 'modal'),
            'Langue : ' . $langue->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'langue' => $langue,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_langue_duplicate', methods: ['GET'])]
    public function duplicate(
        LangueRepository $langueRepository,
        Langue $langue
    ): Response {
        $langueNew = clone $langue;
        $langueNew->setLibelle($langue->getLibelle() . ' - Copie');
        $langueRepository->save($langueNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_langue_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Langue $langue,
        LangueRepository $langueRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $langue->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $langueRepository->remove($langue, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
