<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/MentionController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\DTO\MentionDto;
use App\DTO\TranslatableKey;
use App\Entity\Mention;
use App\Entity\TypeDiplome;
use App\Form\MentionDtoType;
use App\Repository\DomaineRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Service\MentionService;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour la gestion des mentions.
 */
#[Route('/administration/mention')]
class MentionController extends AbstractController
{
    public function __construct(
        private readonly MentionService        $mentionService,
        private readonly DomaineRepository     $domaineRepository,
        private readonly TypeDiplomeRepository $typeDiplomeRepository
    )
    {
    }

    /**
     * Affiche la page d'index des mentions.
     */
    #[Route('/', name: 'app_mention_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(Mention::class)
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
            ->addColumn('codeApogee', [
                'label' => 'Code Apogée',
                'sortable' => true,
                'filterable' => true,
            ])

            // Colonne avec relation
            ->addColumn('typeDiplome.libelle', [
                'label' => 'Type de diplôme',
                'sortable' => true,
                'filterable' => true,
                'type' => 'entity',
                'entity' => TypeDiplome::class,
                'entity_label' => 'libelle',
            ])
            ->addColumn('domaines', [
                'label' => 'Domaine(s)',
                'type' => 'collection',
                'format' => 'badges',
                'collection_property' => 'libelle',
                'badge_class' => 'bg-primary',
                'searchable' => false,
            ])
            ->addColumn('utilise', [
                'label' => 'Utilisé ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
                'searchable' => false,
                'sort_expression' => 'SIZE(e.formations)',
                'filter_expression' => 'CASE WHEN SIZE(e.formations) > 0 THEN 1 ELSE 0 END',
            ])
            ->addShowAction('app_mention_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir une mention',
            ])
            ->addEditAction('app_mention_edit', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Modifier une mention',
            ])
            ->addDuplicateAction('app_mention_duplicate')
            ->addDeleteAction('app_mention_delete')
            ->build();

        return $this->render(
            'config/mention/index.html.twig',
            ['table' => $table]
        );
    }

    /**
     * Affiche le modal de codification des mentions.
     */
    #[Route('/codification-modal', name: 'app_mention_codification_modal', methods: ['GET'])]
    public function codificationModal(): Response
    {
        return $this->render('config/mention/_codificationModal.html.twig', [
            'typeDiplomes' => $this->typeDiplomeRepository->findBy([], ['libelle' => 'ASC']),
            'domaines' => $this->domaineRepository->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    /**
     * Génère les codes Apogée pour les mentions selon les critères spécifiés.
     */
    #[Route('/codification', name: 'app_mention_codification', methods: ['POST'])]
    public function codification(Request $request): Response
    {
        $success = $this->mentionService->generateCodification($request);

        if (!$success) {
            $this->addFlash('error', 'Une erreur est survenue lors de la génération des codes.');
        } else {
            $this->addFlash('success', 'Les codes ont été générés avec succès.');
        }

        return $this->redirectToRoute('app_mention_index');
    }

    /**
     * Affiche le formulaire de création d'une nouvelle mention.
     */
    #[Route('/new', name: 'app_mention_new', methods: ['GET', 'POST'])]
    public function new(TurboStreamResponseFactory $turboStream, Request $request): Response
    {
        $mentionDto = MentionDto::createEmpty();
        $form = $this->createForm(MentionDtoType::class, $mentionDto, [
            'action' => $this->generateUrl('app_mention_new'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->mentionService->createMention($mentionDto);
                $this->addFlash('success', 'La mention a été créée avec succès.');
                return $this->json(['success' => true]);
            } catch (Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('mention.new.title', [], 'modal'),
            '',
            '_ui/_modal_new_generic.html.twig',
            [
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    /**
     * Affiche les détails d'une mention.
     */
    #[Route('/{id}', name: 'app_mention_show', methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        int                        $id): Response
    {
        try {
            $mention = $this->mentionService->getMentionById($id);

            $detail = $builder->setEntity(Mention::class)
                ->addField('libelle', [
                    'label' => 'Libellé de la mention',
                ])
                ->addField('sigle', [
                    'label' => 'Sigle',
                ])
                ->addField('codeApogee', [
                    'label' => 'Code Apogée',
                    'empty_text' => 'Non renseigné',
                ])
                ->addField('typeDiplome.libelle', [
                    'label' => 'Type de diplôme',
                ])
                ->addField('domaines', [
                    'label' => 'Domaine(s)',
                    'type' => 'collection',
                    'format' => 'badges',
                    'collection_property' => 'libelle',
                    'badge_class' => 'bg-primary',
                ])
                ->addField('utilise', [
                    'label' => 'Utilisé ?',
                    'type' => 'boolean',
                    'format' => 'boolean',
                ])
                ->build();


            return $turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('mention.show.title', [], 'modal'),
                'Dans : mention ' . $mention->getLibelle(),
                '_ui/_modal_show_generic.html.twig',
                [
                    'entity' => $mention,
                    'detail' => $detail,
                ],
                '_ui/_footer_cancel.html.twig',
                []
            );
        } catch (NotFoundHttpException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_mention_index');
        }
    }

    /**
     * Affiche le formulaire d'édition d'une mention existante.
     */
    #[Route('/{id}/edit', name: 'app_mention_edit', methods: ['GET', 'POST'])]
    public function edit(TurboStreamResponseFactory $turboStream, Request $request, int $id): Response
    {
        try {
            $mention = $this->mentionService->getMentionById($id);
            if (!$mention) {
                throw $this->createNotFoundException(sprintf('Mention avec ID %d non trouvée', $id));
            }

            $mentionDto = MentionDto::createFromEntity($mention);

            $form = $this->createForm(MentionDtoType::class, $mentionDto, [
                'action' => $this->generateUrl('app_mention_edit', ['id' => $id]),
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->mentionService->updateMention($mention, $mentionDto);
                    $this->addFlash('success', 'La mention a été mise à jour avec succès.');
                    return $this->json(['success' => true]);
                } catch (Exception $e) {
                    return $this->json([
                        'success' => false,
                        'error' => $e->getMessage()
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            return $turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('mention.edit.title', [], 'modal'),
                'Mention : ' . $mention->getLibelle(),
                '_ui/_modal_new_generic.html.twig',
                [
                    'mention' => $mention,
                    'form' => $form->createView(),
                ],
                '_ui/_footer_submit_cancel.html.twig',
                []
            );
        } catch (NotFoundHttpException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_mention_index');
        }
    }

    /**
     * Duplique une mention existante.
     */
    #[Route('/{id}/duplicate', name: 'app_mention_duplicate', methods: ['GET'])]
    public function duplicate(int $id): JsonResponse
    {
        try {
            $mention = $this->mentionService->getMentionById($id);
            $this->mentionService->duplicateMention($mention);

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Supprime une mention existante.
     */
    #[Route('/{id}', name: 'app_mention_delete', methods: ['DELETE'])]
    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            $mention = $this->mentionService->getMentionById($id);

            if ($this->isCsrfTokenValid(
                'delete' . $mention->getId(),
                JsonRequest::getValueFromRequest($request, 'csrf')
            )) {
                $success = $this->mentionService->deleteMention($mention);

                if ($success) {
                    return $this->json(['success' => true]);
                }

                return $this->json([
                    'success' => false,
                    'error' => 'Impossible de supprimer cette mention. Elle est peut-être utilisée par des formations.'
                ], Response::HTTP_BAD_REQUEST);
            }

            return $this->json([
                'success' => false,
                'error' => 'Token CSRF invalide'
            ], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundHttpException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
