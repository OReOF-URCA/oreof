<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\MyGotenbergPdf;
use App\Classes\Process\ChangeRfProcess;
use App\Classes\ValidationProcessChangeRf;
use App\DTO\ChangeRf;
use App\Entity\Formation;
use App\Enums\EtatChangeRfEnum;
use App\Enums\TypeRfEnum;
use App\Exception\FileUploadException;
use App\Form\ChangeRfFormationType;
use App\Repository\ChangeRfRepository;
use App\Repository\ComposanteRepository;
use App\Service\DataTableBuilder;
use App\Service\SecureUploadService;
use App\Utils\TurboStreamResponseFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use App\DTO\TranslatableKey;
use Dannebicque\WorkflowOperationsBundle\Exception\OperationNotExecutableException;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationContextNormalizer;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationExecutor;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationInspector;

class FormationResponsableController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $changeRfWorkflow,
        private readonly ValidationProcessChangeRf $validationProcess,
        private readonly ChangeRfProcess $changeRfProcess,
        private readonly SecureUploadService $secureUploadService,
        private readonly WorkflowOperationExecutor $operationExecutor,
        private readonly WorkflowOperationInspector $operationInspector,
        private readonly OperationContextNormalizer $operationContextNormalizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/formation/change-responsable/ajout/{formation}', name: 'app_formation_change_rf')]
    public function index(
        TurboStreamResponseFactory $turboStream,
        ChangeRfRepository $changeRfRepository,
        Formation $formation,
        Request $request
    ): Response {
        $changeRf = new ChangeRf();

        $form = $this->createForm(ChangeRfFormationType::class, $changeRf, [
            'action' => $this->generateUrl(
                'app_formation_change_rf',
                ['formation' => $formation->getId()]
            ),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $user = $datas->getUser();
            $commentaire = $datas->getCommentaire();

            if ($datas->getTypeRf() === TypeRfEnum::RF) {
                $oldResp = $formation->getResponsableMention();
            } else {
                $oldResp = $formation->getCoResponsable();
            }

            $exist = $changeRfRepository->findBy([
                'formation' => $formation,
                'campagneCollecte' => $this->getCampagneCollecte(),
                'nouveauResponsable' => $user,
                'typeRf' => $datas->getTypeRf(),
                'ancienResponsable' => $oldResp
            ]);

            if (count($exist) !== 0) {
                return JsonReponse::error('Une demande de changement de responsable de formation existe déjà pour ce (co-)responsable, cette formation et ce type de (co-)responsable.');
            }

            $newRf = new \App\Entity\ChangeRf();
            $newRf->setCampagneCollecte($this->getCampagneCollecte());
            $newRf->setFormation($formation);
            $newRf->setNouveauResponsable($user);
            $newRf->setTypeRf($datas->getTypeRf());
            $newRf->setDatePriseFonction($datas->getDatePriseFonction());
            $newRf->setCommentaire($commentaire);
            $newRf->setDateDemande(new DateTime());
            // Une nouvelle demande est immédiatement soumise au conseil.
            $newRf->setEtatDemande(['soumis_conseil' => 1]);
            $newRf->setAncienResponsable($oldResp);

            $this->entityManager->persist($newRf);
            $this->entityManager->flush();


            // Message de toast
            $toastMessage = 'Le changement de responsable de formation a bien été enregistré.';

            return $turboStream->stream('formation_v2/change_rf/success.stream.html.twig', [
                'toastMessage' => $toastMessage,
                'formation' => $formation,
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            'formation.change_rf.title',
            'Dans : formation ' . $formation->getDisplay(),
            'formation_v2/change_rf/_demande.html.twig',
            [
                'form' => $form->createView(),
                'formation' => $formation,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Valider la demande',
            ]
        );
    }

    #[Route('/formation/change-responsable/suppression/{demande}', name: 'app_formation_change_rf_suppression', methods:['POST', 'DELETE'])]
    public function suppressionDemande(
        \App\Entity\ChangeRf $demande,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $formation = $demande->getFormation();

        $this->entityManager->remove($demande);
        $this->entityManager->flush();

        if ($this->isTurboFrameRequest()) {
            return $turboStream->stream('formation_v2/change_rf/success.stream.html.twig', [
                'toastMessage' => 'La demande de changement de (co-)responsable de formation a bien été supprimée.',
                'formation' => $formation,
            ]);
        }

        $this->addFlashBag('success', 'La demande de changement de (co-)responsable de formation a bien été supprimée.');

        return $this->redirectToRoute('app_formation_show', [
            'slug'=> $formation?->getSlug()
        ]);
    }

    #[Route('/formation/change-responsable/liste', name: 'app_formation_responsable_liste')]
    public function listeDemande(
        DataTableBuilder $builder
    ): Response {
        $campagneCollecte = $this->getCampagneCollecte();

        if ($campagneCollecte === null) {
            $builder->addBaseWhere('1 = 0');
        } else {
            $builder
                ->addBaseWhere('IDENTITY(e.campagneCollecte) = :campagneCollecteId')
                ->addBaseParameter('campagneCollecteId', $campagneCollecte->getId());
        }

        return $this->render('formation_responsable/liste.html.twig', [
            'table' => $builder
                ->setEntity(\App\Entity\ChangeRf::class)
                ->setPerPage(20)
                ->setDefaultSort('dateDemande', 'desc')
                ->addBaseWhere('JSON_CONTAINS(e.etatDemande, :etatDemande) = 1')
                ->addBaseParameter('etatDemande', json_encode([EtatChangeRfEnum::soumis_cfvu->value => 1]))
                ->addColumn('formation.composantePorteuse.libelle', [
                    'label' => 'Composante',
                    'sortable' => true,
                    'filterable' => true,
                    'searchable' => false,
                    'sort_expression' => 'composantePorteuse_1.libelle',
                    'filter_expression' => 'composantePorteuse_1.libelle',
                ])
                ->addColumn('formation.id', [
                    'label' => 'Formation',
                    'sortable' => false,
                    'filterable' => false,
                    'searchable' => false,
                    'template' => 'formation_responsable/_datatable_formation.html.twig',
                    'class' => 'min-w-[18rem]',
                ])
                ->addColumn('typeRf', [
                    'label' => 'CO-RF / RF',
                    'sortable' => true,
                    'filterable' => true,
                    'searchable' => false,
                    'type' => 'select',
                    'choices' => [
                        TypeRfEnum::RF->value => 'Responsable',
                        TypeRfEnum::CORF->value => 'Co-responsable',
                    ],
                    'template' => 'formation_responsable/_datatable_type_rf.html.twig',
                ])
                ->addColumn('ancienResponsable.display', [
                    'label' => 'Ancien Co/RF',
                    'sortable' => true,
                    'filterable' => true,
                    'searchable' => true,
                ])
                ->addColumn('nouveauResponsable.display', [
                    'label' => 'Nouveau Co/RF',
                    'sortable' => true,
                    'filterable' => true,
                    'searchable' => true,
                ])
                ->addColumn('dateDemande', [
                    'label' => 'Date demande',
                    'sortable' => true,
                    'filterable' => true,
                    'searchable' => false,
                    'type' => 'date',
                    'format' => 'date',
                ])
                ->build(),
        ]);
    }

    #[Route('/formation/change-responsable/export', name: 'app_formation_responsable_liste_export')]
    public function listeDemandeExport(
        Request $request,
        MyGotenbergPdf $myGotenbergPdf,
        ChangeRfRepository $changeRfRepository,
        ComposanteRepository $composanteRepository
    ): Response {

        $demandes = $changeRfRepository->findByTypeValidation(EtatChangeRfEnum::soumis_cfvu->value, $this->getCampagneCollecte());

        if ($request->query->has('composante')) {
            $composanteId = $request->query->get('composante');
            $composantes = $composanteRepository->findBy(['id' => $composanteId]);
        } else if ($this->isGranted('ROLE_ADMIN')) {
            $composantes = $composanteRepository->findAll();
        } else {
            throw new AccessDeniedException("Vous n'avez pas les droits pour accéder à cette page.");
        }

        $tDemandes = [];
        foreach ($composantes as $composante) {
            $tDemandes[$composante->getId()] = [];
        }

        foreach ($demandes as $demande) {
            $formation = $demande->getFormation();
            if ($formation === null || $formation->getComposantePorteuse() === null) {
                continue;
            }

            $composanteId = $formation->getComposantePorteuse()->getId();
            $formationId = $formation->getId();

            $tDemandes[$composanteId][$formationId]['formation'] = $formation;
            $tDemandes[$composanteId][$formationId]['demandes'][] = $demande;
        }

        foreach ($this->getCampagneCollecte()->getTimelineDates() as $date) {
            if ($date->isCfvu()) {
                $dateCfvu = $date->getDate();
                break;
            }
        }


        return $myGotenbergPdf->render('pdf/formation_responsable_liste.html.twig', [
            'titre' => 'Demandes de modification de responsable de formation',
            'demandes' => $tDemandes,
            'dateCfvu' => $dateCfvu ?? null,
            'composantes' => $composantes,
            'dpe' => $this->getCampagneCollecte()
        ], 'synthese_changement_rf_'.(new DateTime())->format('d-m-Y_H-i-s'));
    }

    #[Route('/formation/change-responsable/validation-demande/valider/{transition}/{etape}/{demande}',
        name: 'app_validation_change_rf_valider'
    )]
    public function validationChangeRf(
        Request $request,
        string $transition,
        string $etape,
        \App\Entity\ChangeRf $demande,
        TurboStreamResponseFactory $turboStream,
    ): Response {

        if ($demande === null) {
            return JsonReponse::error('Demande non trouvée');
        }

        $inspection = $this->operationInspector->inspect($this->changeRfWorkflow, $demande, $transition);
        if (!$inspection->canExecute()) {
            return $this->operationErrorResponse(
                $turboStream,
                'Cette opération n’est plus disponible ou vous n’êtes pas autorisé à l’exécuter.',
            );
        }

        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $process = $this->validationProcess->getEtape($etape);
        $processData = $this->changeRfProcess->etatChangeRf($demande, $process);

        $form = $this->createForm($this->resolveOperationFormType($meta), null, [
            'meta' => $meta,
            'transition' => $transition,
            'process' => $process,
            'processData' => $processData ?? null,
            'action' => $this->generateUrl('app_validation_change_rf_valider', [
                'transition' => $transition,
                'etape' => $etape,
                'demande' => $demande->getId(),
            ]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = (array) $form->getData();
            $fileName = '';
            $originalFileName = null;
            $uploadedFile = $form->has('file') ? $form->get('file')->getData() : null;
            if ($uploadedFile !== null) {
                try {
                    $upload = $this->secureUploadService->upload($uploadedFile, 'conseils');
                } catch (FileUploadException $exception) {
                    return JsonReponse::error($exception->getPublicMessage());
                }

                $fileName = $upload->getStoredFilename();
                $originalFileName = $upload->getOriginalFilename();
            }
            unset($formData['file']);

            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new AccessDeniedException('Un utilisateur authentifié est requis.');
            }

            $previousPlace = array_key_first($this->changeRfWorkflow->getMarking($demande)->getPlaces());

            try {
                $this->operationExecutor->execute(
                    $this->changeRfWorkflow,
                    $demande,
                    $transition,
                    $this->operationContextNormalizer->normalize(
                        workflow: $this->changeRfWorkflow,
                        transitionName: $transition,
                        actor: $user,
                        input: $formData,
                    )->withRuntime([
                        'previous_place' => (string) $previousPlace,
                        'file_name' => $fileName,
                        'original_file_name' => $originalFileName,
                    ]),
                );
            } catch (OperationNotExecutableException) {
                return $this->operationErrorResponse($turboStream, 'Cette opération n’est plus disponible ou vous n’êtes pas autorisé à l’exécuter.');
            } catch (\Throwable $exception) {
                $this->logger->error('Échec de l’opération changeRf.', [
                    'transition' => $transition,
                    'demande' => $demande->getId(),
                    'exception' => $exception,
                ]);

                return $this->operationErrorResponse($turboStream, 'La transition n’a pas pu être appliquée : '.$exception->getMessage());
            }

            if ($this->isTurboFrameRequest()) {
                return $turboStream->stream('formation_v2/change_rf/success.stream.html.twig', [
                    'toastMessage' => 'La demande a bien été validée.',
                    'formation' => $demande->getFormation(),
                ]);
            }

            return JsonReponse::success('La demande a bien été validée.');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;
        if ($form->isSubmitted()) {
            $this->logger->warning('Formulaire de validation ChangeRf invalide.', [
                'transition' => $transition,
                'demande' => $demande->getId(),
                'errors' => (string) $form->getErrors(true, false),
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('validation.changeRf.valider.title'),
            new TranslatableKey('validation.changeRf.valider.subtitle'),
            'formation_responsable/_valide.html.twig',
            [
                'demande' => $demande,
                'process' => $process,
                'etape' => $etape,
                'processData' => $processData ?? null,
                'meta' => $meta,
                'transition' => $transition,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
            ],
            $status,
        );
    }

    #[Route(
        '/formation/change-responsable/{key}-lot-confirme/{etape}/{transition}',
        name: 'app_validation_change_rf_confirme_lot'
    )]
    public function validationLotChangeRf(
        ChangeRfRepository $changeRfRepository,
        Request $request,
        string $etape,
        string $key,
        string $transition
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->json(['success' => false, 'message' => 'Méthode non autorisée.'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedException('Un utilisateur authentifié est requis.');
        }

        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $form = $this->createForm($this->resolveOperationFormType($meta), null, [
            'meta' => $meta,
            'transition' => $transition,
            'bulk' => true,
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->json(['success' => false, 'message' => 'Les données du formulaire sont incomplètes ou invalides.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $formData = (array) $form->getData();
        $fileName = '';
        $originalFileName = null;
        $uploadedFile = $form->has('file') ? $form->get('file')->getData() : null;
        if ($uploadedFile !== null) {
            try {
                $upload = $this->secureUploadService->upload($uploadedFile, 'conseils');
                $fileName = $upload->getStoredFilename();
                $originalFileName = $upload->getOriginalFilename();
            } catch (FileUploadException $exception) {
                return $this->json(['success' => false, 'message' => $exception->getPublicMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $ids = array_filter(explode(',', (string) ($formData['demandes'] ?? '')));
        unset($formData['demandes'], $formData['file']);
        $processed = 0;
        $rejected = 0;

        foreach ($ids as $demandeId) {
            $demande = $changeRfRepository->find($demandeId);
            if (null === $demande) {
                ++$rejected;
                continue;
            }

            $previousPlace = array_key_first($this->changeRfWorkflow->getMarking($demande)->getPlaces());
            try {
                $context = $this->operationContextNormalizer->normalize(
                    workflow: $this->changeRfWorkflow,
                    transitionName: $transition,
                    actor: $user,
                    input: $formData,
                )->withRuntime([
                    'previous_place' => (string) $previousPlace,
                    'file_name' => $fileName,
                    'original_file_name' => $originalFileName,
                ]);
                $this->operationExecutor->execute($this->changeRfWorkflow, $demande, $transition, $context);
                ++$processed;
            } catch (\Throwable $exception) {
                ++$rejected;
                $this->logger->error('Échec d’une opération changeRf en lot.', [
                    'transition' => $transition,
                    'demande' => $demande->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        return $this->json([
            'success' => $processed > 0 && 0 === $rejected,
            'processed' => $processed,
            'rejected' => $rejected,
            'message' => 0 === $rejected
                ? sprintf('%d demande(s) traitée(s).', $processed)
                : sprintf('%d demande(s) traitée(s), %d rejetée(s).', $processed, $rejected),
        ]);
    }

    #[Route('/formation/change-responsable/{key}-lot/{etape}/{transition}',
        name: 'app_validation_change_rf_lot')]
    public function transitionLotChangeRf(
        Request                   $request,
        string                    $key,
        string                    $etape,
        string                    $transition
    ): Response
    {
        //on récupère la transition concernée et sa configuration pour construire le formulaire
        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $demandes = (string) $request->query->get('parcours', '');
        $form = $this->createForm($this->resolveOperationFormType($meta), null, [
            'meta' => $meta,
            'transition' => $transition,
            'bulk' => true,
            'demandes' => $demandes,
            'action' => $this->generateUrl('app_validation_change_rf_confirme_lot', [
                'key' => $key,
                'etape' => $etape,
                'transition' => $transition,
            ]),
            'method' => 'POST',
        ]);

        return $this->render('formation_responsable/_lot.html.twig', [
            'key' => $key,
            'etape' => $etape,
            'transition' => $transition,
            'meta' => $meta,
            'form' => $form->createView(),
        ]);
    }

    #[Route(
        '/formation/change-responsable/validation-demande/reserver/{transition}/{etape}/{demande}',
        name: 'app_validation_change_rf_reserver'
    )]
    public function reserverChangeRf(
        Request $request,
        string $transition,
        string $etape,
        \App\Entity\ChangeRf $demande,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $inspection = $this->operationInspector->inspect($this->changeRfWorkflow, $demande, $transition);
        if (!$inspection->canExecute()) {
            return $this->operationErrorResponse($turboStream, 'Cette opération n’est plus disponible ou vous n’êtes pas autorisé à l’exécuter.');
        }

        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $process = $this->validationProcess->getEtape($etape);
        $processData = $this->changeRfProcess->etatChangeRf($demande, $process);
        $form = $this->createForm($this->resolveOperationFormType($meta), null, [
            'meta' => $meta,
            'transition' => $transition,
            'process' => $process,
            'processData' => $processData,
            'action' => $this->generateUrl('app_validation_change_rf_reserver', [
                'transition' => $transition,
                'etape' => $etape,
                'demande' => $demande->getId(),
            ]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new AccessDeniedException('Un utilisateur authentifié est requis.');
            }

            $formData = (array) $form->getData();
            $previousPlace = array_key_first($this->changeRfWorkflow->getMarking($demande)->getPlaces());

            try {
                $context = $this->operationContextNormalizer->normalize(
                    workflow: $this->changeRfWorkflow,
                    transitionName: $transition,
                    actor: $user,
                    input: $formData,
                )->withRuntime(['previous_place' => (string) $previousPlace]);
                $this->operationExecutor->execute($this->changeRfWorkflow, $demande, $transition, $context);
            } catch (OperationNotExecutableException) {
                return $this->operationErrorResponse($turboStream, 'Cette opération n’est plus disponible ou vous n’êtes pas autorisé à l’exécuter.');
            } catch (\Throwable $exception) {
                $this->logger->error('Échec de l’opération changeRf.', [
                    'transition' => $transition,
                    'demande' => $demande->getId(),
                    'exception' => $exception,
                ]);

                return $this->operationErrorResponse($turboStream, 'La transition n’a pas pu être appliquée : '.$exception->getMessage());
            }

            return $turboStream->stream('formation_v2/change_rf/success.stream.html.twig', [
                'toastMessage' => 'La demande a bien été réservée.',
                'formation' => $demande->getFormation(),
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            'Émettre une réserve',
            null !== $demande->getFormation() ? 'Dans : formation '.$demande->getFormation()->getDisplay() : null,
            'formation_responsable/_valide.html.twig',
            [
                'demande' => $demande,
                'process' => $process,
                'etape' => $etape,
                'processData' => $processData,
                'meta' => $meta,
                'transition' => $transition,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [],
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function resolveOperationFormType(array $metadata): string
    {
        $formType = $metadata['form']['type'] ?? null;
        if (!is_string($formType) || !is_subclass_of($formType, AbstractType::class)) {
            throw new \LogicException('A valid form.type metadata entry is required for this workflow operation.');
        }

        return $formType;
    }

    private function operationErrorResponse(TurboStreamResponseFactory $turboStream, string $message): Response
    {
        return $turboStream->streamOpenModalFromTemplates(
            'Opération impossible',
            null,
            'formation_responsable/_operation_error.html.twig',
            ['message' => $message],
            '_ui/_footer_cancel.html.twig',
            [],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
