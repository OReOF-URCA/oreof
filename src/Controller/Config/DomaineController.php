<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/DomaineController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\Domaine;
use App\Form\DomaineType;
use App\Repository\DomaineRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/domaine')]
class DomaineController extends AbstractController
{
    #[Route('/', name: 'app_domaine_index', methods: ['GET'])]
    public function index(
        DatatableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Domaine::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé de la mention',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('sigle', [
                'label' => 'Sigle',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('nbMentions', [
                'label' => 'Nombre de mentions',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeApogee', [
                'label' => 'Code Apogée',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addShowAction('app_domaine_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un domaine',
            ])
            ->addEditAction('app_domaine_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier un domaine',
            ])
            ->addDuplicateAction('app_domaine_duplicate')
            ->addDeleteAction('app_domaine_delete')
            ->build();

        return $this->render('config/domaine/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_domaine_new', methods: ['GET', 'POST'])]
    public function new(TurboStreamResponseFactory $turboStream,
                        Request                    $request, DomaineRepository $domaineRepository): Response
    {
        $domaine = new Domaine();
        $form = $this->createForm(DomaineType::class, $domaine, [
            'action' => $this->generateUrl('app_domaine_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $domaineRepository->save($domaine, true);
            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('domaine.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'domaine' => $domaine,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_domaine_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        Domaine                    $domaine
    ): Response
    {
        $detail = $builder->setEntity(Domaine::class)
            ->addField('libelle', [
                'label' => 'Libellé de la mention',
            ])
            ->addField('sigle', [
                'label' => 'Sigle',
            ])
            ->addField('nbMentions', [
                'label' => 'Nombre de mentions',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addField('codeApogee', [
                'label' => 'Code Apogée',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('domaine.show.title', [], 'modal'),
            'Dans : mention ' . $domaine->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $domaine,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_domaine_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request                    $request, Domaine $domaine, DomaineRepository $domaineRepository): Response
    {
        $form = $this->createForm(DomaineType::class, $domaine, [
            'action' => $this->generateUrl('app_domaine_edit', ['id' => $domaine->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $domaineRepository->save($domaine, true);
            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('domaine.edit.title', [], 'modal'),
            'Domaine : ' . $domaine->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'domaine' => $domaine,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_domaine_duplicate', methods: ['GET'])]
    public function duplicate(
        DomaineRepository $domaineRepository,
        Domaine $domaine
    ): Response {
        $domaineNew = clone $domaine;
        $domaineNew->setLibelle($domaine->getLibelle() . ' - Copie');
        $domaineRepository->save($domaineNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_domaine_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Domaine $domaine,
        DomaineRepository $domaineRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $domaine->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $domaineRepository->remove($domaine, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
