<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/AnneeUniversitaireController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\AnneeUniversitaire;
use App\Form\AnneeUniversitaireType;
use App\Repository\AnneeUniversitaireRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/annee/universitaire')]
class AnneeUniversitaireController extends AbstractController
{
    #[Route('/', name: 'app_annee_universitaire_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(AnneeUniversitaire::class)
            ->setPerPage(20)
            ->setDefaultSort('annee', 'desc')
            ->addColumn('libelle', [
                'label' => 'Libellé',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('annee', [
                'label' => 'Année',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addShowAction('app_annee_universitaire_show', [
                'modal' => true,
                'modal_title' => 'Voir une année universitaire',
            ])
            ->addEditAction('app_annee_universitaire_edit', [
                'modal' => true,
                'modal_title' => 'Modifier une année universitaire',
            ])
            ->addDuplicateAction('app_annee_universitaire_duplicate')
            ->addDeleteAction('app_annee_universitaire_delete')
            ->build();

        return $this->render('config/annee_universitaire/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_annee_universitaire_new', methods: ['GET', 'POST'])]
    public function new(
        TurboStreamResponseFactory   $turboStream,
        Request                      $request,
        AnneeUniversitaireRepository $anneeUniversitaireRepository
    ): Response
    {
        $anneeUniversitaire = new AnneeUniversitaire();
        $form = $this->createForm(
            AnneeUniversitaireType::class,
            $anneeUniversitaire,
            ['action' => $this->generateUrl('app_annee_universitaire_new')]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $anneeUniversitaireRepository->save($anneeUniversitaire, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('annee_universitaire.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'annee_universitaire' => $anneeUniversitaire,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}', name: 'app_annee_universitaire_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        AnneeUniversitaire         $annee_universitaire
    ): Response
    {
        $detail = $builder
            ->setEntity(AnneeUniversitaire::class)
            ->addField('id', ['label' => 'ID'])
            ->addField('libelle', ['label' => 'Libellé'])
            ->addField('annee', ['label' => 'Année'])
            ->addField('dpes.count()', ['label' => 'Nombre de campagnes de collecte'])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('annee_universitaire.show.title', [], 'modal'),
            'Année universitaire : ' . $annee_universitaire->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $annee_universitaire,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_annee_universitaire_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory   $turboStream,
        Request                      $request,
        AnneeUniversitaire           $annee_universitaire,
        AnneeUniversitaireRepository $anneeUniversitaireRepository
    ): Response {
        $form = $this->createForm(
            AnneeUniversitaireType::class,
            $annee_universitaire,
            [
                'action' => $this->generateUrl('app_annee_universitaire_edit', [
                    'id' => $annee_universitaire->getId()
                ])
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $anneeUniversitaireRepository->save($annee_universitaire, true);

            return $this->json(true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('annee_universitaire.edit.title', [], 'modal'),
            'Année universitaire : ' . $annee_universitaire->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            [
                'annee_universitaire' => $annee_universitaire,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/duplicate', name: 'app_annee_universitaire_duplicate', methods: ['GET'])]
    public function duplicate(
        AnneeUniversitaireRepository $anneeUniversitaireRepository,
        AnneeUniversitaire           $annee_universitaire
    ): Response {
        $annee_universitaireNew = clone $annee_universitaire;
        $annee_universitaireNew->setLibelle($annee_universitaire->getLibelle() . ' - Copie');
        $anneeUniversitaireRepository->save($annee_universitaireNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_annee_universitaire_delete', methods: ['DELETE'])]
    public function delete(
        Request                      $request,
        AnneeUniversitaire           $annee_universitaire,
        AnneeUniversitaireRepository $anneeUniversitaireRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $annee_universitaire->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $anneeUniversitaireRepository->remove($annee_universitaire, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
