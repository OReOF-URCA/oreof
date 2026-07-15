<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/PlateformeAdmissionController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Controller\Traits\CsrfDeleteTrait;
use App\DTO\TranslatableKey;
use App\Entity\PlateformeAdmission;
use App\Form\PlateformeAdmissionType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\PlateformeAdmissionRepository;
use App\Service\DetailBuilder;
use App\Service\DataTableBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/plateforme-admission')]
class PlateformAdmissionController extends AbstractController
{
    use CsrfDeleteTrait;
    #[Route('/', name: 'app_plateforme_adminission_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(PlateformeAdmission::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé de la plateforme d\'admission',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('code', [
                'label' => 'Code/Sigle',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('active', [
                'label' => 'Active ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addShowAction('app_plateforme_adminission_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un type de diplôme',
            ])
            ->addEditAction('app_plateforme_adminission_edit', [
                'modal' => false,
            ])
            ->addDuplicateAction('app_plateforme_adminission_duplicate')
            ->addDeleteAction('app_plateforme_adminission_delete')
            ->build();
        return $this->render('config/plateforme_adminission/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_plateforme_adminission_new', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'administration.plateforme_adminission')]
    #[Breadcrumb(label: 'Création')]
    public function new(
        Request                       $request,
        EntityManagerInterface        $em,
        PlateformeAdmissionRepository $plateformeAdmissionRepository
    ): Response
    {
        $plateformeAdmission = new PlateformeAdmission();
        $form = $this->createForm(PlateformeAdmissionType::class, $plateformeAdmission, [
            'action' => $this->generateUrl('app_plateforme_adminission_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($plateformeAdmission);
            $em->flush();
            // Abandon de la fenêtre modale
            // return $this->json(true);

            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Création de la plateforme réussie',
                'title' => 'Succès',
            ]);
            return $this->redirectToRoute('app_plateforme_adminission_index');
        }

        return $this->render('config/plateforme_adminission/new.html.twig', [
            'plateforme_adminission' => $plateformeAdmission,
            'form' => $form->createView(),
            'titre' => "Création d'un type de diplôme"
        ]);
    }

    #[Route('/{id}', name: 'app_plateforme_adminission_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        PlateformeAdmission        $plateformeAdmission,
        DetailBuilder              $builder
    ): Response
    {
        $detail = $builder
            ->setEntity(PlateformeAdmission::class)
            ->addField('libelle', [
                'label' => 'Libellé du type de diplôme',
            ])
            ->addField('code', [
                'label' => 'Code/Sigle',
            ])
            ->addField('active', [
                'label' => 'active ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('color', [
                'label' => 'Couleur',
                'empty_text' => 'Non définie',
            ])
            ->addField('configuration', [
                'label' => 'Configuration',
                'type' => 'json',
                'format' => 'json',
                'empty_text' => 'Aucune configuration',
            ])
            ->addField('definitionChamps', [
                'label' => 'Définition des champs spécifiques',
                'type' => 'json',
                'format' => 'json',
                'empty_text' => 'Aucun champ spécifique défini',
            ])
            ->build();


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('plateforme_adminission.show.title', [], 'modal'),
            'Dans : type diplôme ' . $plateformeAdmission->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $plateformeAdmission,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_plateforme_adminission_edit', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'administration.plateforme_adminission')]
    #[Breadcrumb(label: 'Modification')]
    public function edit(
        Request                       $request,
        PlateformeAdmission           $plateformeAdmission,
        PlateformeAdmissionRepository $plateformeAdmissionRepository
    ): Response
    {
        $form = $this->createForm(PlateformeAdmissionType::class, $plateformeAdmission, [
            'action' => $this->generateUrl('app_plateforme_adminission_edit', ['id' => $plateformeAdmission->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plateformeAdmissionRepository->save($plateformeAdmission, true);
            // Abandon de la fenêtre modale
            // return $this->json(true);
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Type Diplôme modifié avec succès',
                'title' => 'Succès',
            ]);
            return $this->redirectToRoute('app_plateforme_adminission_index');
        }

        return $this->render('config/plateforme_adminission/new.html.twig', [
            'plateforme_adminission' => $plateformeAdmission,
            'form' => $form->createView(),
            'titre' => "Modification d'un type de diplôme"
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_plateforme_adminission_duplicate', methods: ['GET'])]
    public function duplicate(
        TurboStreamResponseFactory $turboStream,
        PlateformeAdmissionRepository $plateformeAdmissionRepository,
        PlateformeAdmission           $plateformeAdmission
    ): Response
    {
        $plateformeAdmissionNew = clone $plateformeAdmission;
        $plateformeAdmissionNew->setLibelle($plateformeAdmission->getLibelle() . ' - Copie');
        $plateformeAdmissionRepository->save($plateformeAdmissionNew, true);
        return $turboStream->streamToastSuccess('Plateforme d\'admission dupliquée avec succès', true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_plateforme_adminission_delete', methods: ['DELETE'])]
    public function delete(
        TurboStreamResponseFactory $turboStream,
        Request                       $request,
        PlateformeAdmission           $plateformeAdmission,
        PlateformeAdmissionRepository $plateformeAdmissionRepository
    ): Response
    {
        if ($this->isDeleteTokenValid($plateformeAdmission, $this->getCsrfTokenFromRequest($request))) {
            $plateformeAdmissionRepository->remove($plateformeAdmission, true);

            return $turboStream->streamToastSuccess('Plateforme d\'admission supprimée avec succès', true);
        }

        return $turboStream->streamToastError('Erreur lors de la suppression', true);
    }
}
