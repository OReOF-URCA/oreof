<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/EtablissementController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Controller\BaseController;
use App\DTO\TranslatableKey;
use App\Entity\Adresse;
use App\Entity\Etablissement;
use App\Form\EtablissementType;
use App\Repository\EtablissementRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/etablissement')]
class EtablissementController extends BaseController
{
    #[Route('/', name: 'app_etablissement_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Etablissement::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé de l\'établissement',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('adresse', [
                'label' => 'Adresse',
                'sortable' => true,
                'filterable' => true,
                'type' => 'entity',
                'entity' => Adresse::class,
                'entity_label' => 'display',
                'is_html' => true,
            ])
            ->addShowAction('app_etablissement_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un établissement',
            ])
            ->addEditAction('app_etablissement_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier un établissement',
            ])
            ->addDeleteAction('app_etablissement_delete')
            ->build();

        return $this->render('config/etablissement/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_etablissement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EtablissementRepository $etablissementRepository): Response
    {
        $etablissement = new Etablissement();
        $form = $this->createForm(EtablissementType::class, $etablissement, [
            'action' => $this->generateUrl('app_etablissement_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etablissementRepository->save($etablissement, true);

            $this->addFlashBag('success', 'Établissement créé avec succès');
            return $this->redirectToRoute('app_etablissement_index');
        }

        return $this->render('config/etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
            'titre' => "Création d'un établissement"
        ]);
    }

    #[Route('/{id}', name: 'app_etablissement_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        Etablissement              $etablissement
    ): Response
    {
        $detail = $builder
            ->setEntity(Etablissement::class)
            ->addField('libelle', ['label' => 'Libellé'])
            ->addField('adresse.display', [
                'label' => 'Adresse',
                'format' => 'html',
                'empty_text' => 'Non définie',
            ])
            ->addField('numeroSIRET', ['label' => 'Numéro SIRET', 'empty_text' => 'Non renseigné'])
            ->addField('numeroActivite', ['label' => 'Numéro d\'activité', 'empty_text' => 'Non renseigné'])
            ->addField('emailCentral', ['label' => 'Email central', 'empty_text' => 'Non renseigné'])
            ->addField('etablissementInformation.calendrierUniversitaire', [
                'label' => 'Calendrier universitaire',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('etablissementInformation.calendrierInscription', [
                'label' => 'Calendrier d\'inscription',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('etablissementInformation.informationsPratiques', [
                'label' => 'Informations pratiques',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('etablissementInformation.restauration', [
                'label' => 'Restauration',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('etablissementInformation.hebergement', [
                'label' => 'Hébergement',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('etablissementInformation.transport', [
                'label' => 'Transport',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('etablissement.show.title', [], 'modal'),
            'Établissement : ' . $etablissement->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $etablissement,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_etablissement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request                 $request,
        Etablissement           $etablissement,
        EtablissementRepository $etablissementRepository
    ): Response {
        $form = $this->createForm(
            EtablissementType::class,
            $etablissement,
            [
                'action' => $this->generateUrl('app_etablissement_edit', ['id' => $etablissement->getId()]),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etablissement = $form->getData();
            $etablissementRepository->save($etablissement, true);

            $this->addFlashBag('success', 'Établissement modifié avec succès');
            return $this->redirectToRoute('app_etablissement_index');
        }

        return $this->render('config/etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
            'titre' => "Modification d'un établissement"
        ]);
    }

    #[Route('/{id}', name: 'app_etablissement_delete', methods: ['POST'])]
    public function delete(
        Request                 $request,
        Etablissement           $etablissement,
        EtablissementRepository $etablissementRepository
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $etablissement->getId(), $request->request->get('_token'))) {
            $etablissementRepository->remove($etablissement, true);
        }

        return $this->redirectToRoute('app_etablissement_index', [], Response::HTTP_SEE_OTHER);
    }
}
