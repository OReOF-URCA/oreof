<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/AnneeUniversitaireController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Controller\Traits\CsrfDeleteTrait;
use App\DataTable\CampagneCollecteDataTable;
use App\DTO\TranslatableKey;
use App\Entity\CampagneCollecte;
use App\Enums\CampagnePublicationTagEnum;
use App\Enums\ConfigurationPublicationEnum;
use App\Form\CampagneCollecteType;
use App\Form\ConfigurePublicationType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\CampagneCollecteRepository;
use App\Utils\TurboStreamResponseFactory;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/campagne-collecte')]
class CampagneCollecteController extends AbstractController
{
    use CsrfDeleteTrait;

    #[Route('/', name: 'app_campagne_collecte_index', methods: ['GET', 'POST'])]
    public function index(
        CampagneCollecteDataTable $table
    ): Response
    {
        return $this->render('config/campagne_collecte/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/liste', name: 'app_campagne_collecte_liste', methods: ['GET'])]
    public function liste(CampagneCollecteRepository $campagneCollecteRepository): Response
    {
        return $this->render('config/campagne_collecte/_liste.html.twig', [
            'campagne_collectes' => $campagneCollecteRepository->findAll(),
            'libelleConfigurationPublication' => [
                ConfigurationPublicationEnum::MAQUETTE->value => 'Maquette des enseignements',
                ConfigurationPublicationEnum::MCCC->value => 'MCCC (PDF)'
            ]
        ]);
    }

    #[Route('/ajouter', name: 'app_campagne_collecte_new', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'administration.campagne_collecte')]
    #[Breadcrumb(label: 'Création')]
    public function new(
        Request                    $request,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response
    {
        $campagne_collecte = new CampagneCollecte();
        $form = $this->createForm(
            CampagneCollecteType::class,
            $campagne_collecte,
            ['action' => $this->generateUrl('app_campagne_collecte_new')]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($campagne_collecte->getAnnee() === null && $campagne_collecte->getAnneeUniversitaire() !== null) {
                $campagne_collecte->setAnnee((int) $campagne_collecte->getAnneeUniversitaire()->getAnnee());
            }
            $campagneCollecteRepository->save($campagne_collecte, true);

            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Campagne de collecte créée avec succès',
                'title' => 'Succès',
            ]);

            return $this->redirectToRoute('app_campagne_collecte_show', [
                'id' => $campagne_collecte->getId()
            ]);
        }

        return $this->render('config/campagne_collecte/new.html.twig', [
            'campagne_collecte' => $campagne_collecte,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_campagne_collecte_show', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'administration.campagne_collecte')]
    #[Breadcrumb(label: 'Détail')]
    public function show(CampagneCollecte $campagne_collecte): Response
    {
        return $this->render('config/campagne_collecte/show.html.twig', [
            'campagne_collecte' => $campagne_collecte,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_campagne_collecte_edit', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'administration.campagne_collecte')]
    #[Breadcrumb(label: 'Modification')]
    public function edit(
        Request                    $request,
        CampagneCollecte           $campagne_collecte,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response {
        $form = $this->createForm(
            CampagneCollecteType::class,
            $campagne_collecte,
            [
                'action' => $this->generateUrl('app_campagne_collecte_edit', [
                    'id' => $campagne_collecte->getId()
                ])
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $campagneCollecteRepository->save($campagne_collecte, true);

            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Campagne de collecte modifiée avec succès'
            ]);

            return $this->redirectToRoute('app_campagne_collecte_show', [
                'id' => $campagne_collecte->getId()
            ]);
        }

        return $this->render('config/campagne_collecte/edit.html.twig', [
            'campagne_collecte' => $campagne_collecte,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_campagne_collecte_duplicate', methods: ['POST'])]
    public function duplicate(
        Request $request,
        TurboStreamResponseFactory $turboStream,
        CampagneCollecteRepository $campagneCollecteRepository,
        CampagneCollecte           $campagne_collecte
    ): Response {
        if ($this->isDuplicateTokenValid($campagne_collecte, $this->getCsrfTokenFromRequest($request))) {
            $campagne_collecteNew = clone $campagne_collecte;
            $campagne_collecteNew->setLibelle($campagne_collecte->getLibelle() . ' - Copie');
            $campagneCollecteRepository->save($campagne_collecteNew, true);

            return $turboStream->streamToastSuccess('Campagne de collecte dupliquée avec succès', true);
        }

        return $turboStream->streamToastError('Jeton CSRF invalide', true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_campagne_collecte_delete', methods: ['DELETE'])]
    public function delete(
        TurboStreamResponseFactory $turboStream,
        Request          $request,
        CampagneCollecte $campagne_collecte,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response {
        if ($this->isDeleteTokenValid($campagne_collecte, $this->getCsrfTokenFromRequest($request))) {
            $campagneCollecteRepository->remove($campagne_collecte, true);

            return $turboStream->streamToastSuccess('Campagne de collecte supprimée avec succès', true);
        }

        return $turboStream->streamToastError('Erreur lors de la suppression', true);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/configure/publication', name: 'app_campagne_collecte_configure_publication', methods: ['GET', 'POST'])]
    public function configurePublication(
        CampagneCollecte $campagneCollecte,
        Request $request,
        CampagneCollecteRepository $campagneRepository,
        TurboStreamResponseFactory $turboStream
    ) : Response {
        $form = $this->createForm(
            ConfigurePublicationType::class,
            $campagneCollecte,
            [
               'action' => $this->generateUrl('app_campagne_collecte_configure_publication', ['id' => $campagneCollecte->getId()]),
               'hasPublishedMccc' => $campagneCollecte->getPublicationOptions()[ConfigurationPublicationEnum::MCCC->value] ?? 'none',
               'hasPublishedMaquette' => $campagneCollecte->getPublicationOptions()[ConfigurationPublicationEnum::MAQUETTE->value] ?? 'none',
               'publicationTag' => $campagneCollecte->getPublicationTag() ?? 'none'
            ]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Configuration de la publication
            $configToSave = [];
            $jsonConfigValues = [
                ConfigurationPublicationEnum::MAQUETTE->value,
                ConfigurationPublicationEnum::MCCC->value
            ];
            foreach ($jsonConfigValues as $configValue) {
                if ($form->has($configValue)) {
                    $configToSave[$configValue] = $form->get($configValue)->getData();
                }
            }
            // Le tag ne peut être présent qu'une seule fois
            $publicationTag = $form->get('campagneTag')->getData();
            if (in_array($publicationTag, [CampagnePublicationTagEnum::ANNEE_COURANTE->value, CampagnePublicationTagEnum::ANNEE_SUIVANTE->value])) {
                $statusAlreadyTaken = $campagneRepository->findOneBy(['publicationTag' => $publicationTag]);
                if ($statusAlreadyTaken !== null && $statusAlreadyTaken->getId() !== $campagneCollecte->getId()) {
                    return $this->json(['message' => 'Ce statut est déjà utilisé'], 500);
                }
            }

            $campagneCollecte->setPublicationTag($publicationTag);
            $campagneCollecte->setPublicationOptions($configToSave);
            $campagneRepository->save($campagneCollecte, true);

            return $turboStream->streamToastSuccess(new TranslatableKey('campagne_collecte.configure_publication.success'), true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('campagne_collecte.configure_publication.title'),
            new TranslatableKey('campagne_collecte.configure_publication.description'),
            'config/campagne_collecte/_configure_publication.html.twig',
            [
                'campagneCollecte' => $campagneCollecte,
                'form' => $form->createView()
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }
}
