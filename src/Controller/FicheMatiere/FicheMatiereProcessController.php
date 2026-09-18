<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursProcessController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/02/2026 18:47
 */

namespace App\Controller\FicheMatiere;

use App\Classes\ValidationProcessFicheMatiere;
use App\Controller\BaseController;
use App\Entity\FicheMatiere;
use App\Entity\User;
use App\Events\HistoriqueFicheMatiereEvent;
use App\Utils\TurboStreamResponseFactory;
use App\Workflow\Form\MetaDrivenFormFactory;
use App\Workflow\Metadata\WorkflowMetaMapper;
use App\Workflow\ModalView\TransitionModalFicheMatiereViewBuilder;
use Dannebicque\WorkflowOperationsBundle\Operation\OperationContextNormalizer;
use Dannebicque\WorkflowOperationsBundle\Operation\WorkflowOperationExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/fiche-matiere/v2/process', name: 'fiche_matiere_process')]
#[IsGranted('ROLE_ADMIN')]
class FicheMatiereProcessController extends BaseController
{
    public function __construct(
        private readonly MetaDrivenFormFactory                  $metaDrivenFormFactory,
        private readonly WorkflowMetaMapper                     $workflowMetaMapper,
        private readonly TransitionModalFicheMatiereViewBuilder $transitionModalViewBuilder,
        private readonly WorkflowOperationExecutor              $operationExecutor,
        private readonly OperationContextNormalizer             $operationContextNormalizer,
        private readonly EntityManagerInterface                 $entityManager,
        private readonly EventDispatcherInterface               $eventDispatcher,
        private readonly ValidationProcessFicheMatiere          $validationProcess,
        #[Target('fiche')]
        private readonly WorkflowInterface                      $ficheWorkflow,
    ) {
    }


    #[Route('/{type}/{ficheMatiere}/{transition}', name: '_apply')]
    public function applyProcess(
        Request                    $request,
        TurbostreamResponseFactory $turboStream,
        FicheMatiere               $ficheMatiere,
        string                     $transition,
    ): Response
    {
        $rawMeta = $this->validationProcess->getMetaFromTransition($transition);
        $metaDto = $this->workflowMetaMapper->fromArray($rawMeta);

        $view = $this->transitionModalViewBuilder->build($transition, $ficheMatiere, $rawMeta);

        if ($view?->mode === 'report') {
            $formId = $metaDto->form?->formId ?? 'modal_form';
            $form = $this->metaDrivenFormFactory->createEmpty($formId);
        } else {
            // si pas de form dans metadata : form vide
            if ($metaDto->form === null) {
                $form = $this->metaDrivenFormFactory->createEmpty();
            } else {
                $form = $this->metaDrivenFormFactory->create($metaDto->form, $transition);
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($view?->mode === 'report' && $view?->canSubmit === false) {
                $message = implode(' ', array_column($view->messages, 'message'));

                return $turboStream->stream('fiche_matiere_v2/turbo/apply_error.stream.html.twig', [
                    'ficheMatiere' => $ficheMatiere,
                    'transition' => $transition,
                    'type' => $metaDto->type,
                    'message' => '' !== trim($message)
                        ? $message
                        : 'Le traitement est bloqué par un contrôle.',
                ]);
            } elseif ($form->isValid()) {
                try {
                    $user = $this->getUser();
                    if (!$user instanceof User) {
                        throw new \LogicException('Utilisateur non authentifié.');
                    }

                    $previousPlace = array_keys($ficheMatiere->getEtatFiche())[0] ?? 'inconnue';
                    $formData = $this->extractFormData($form->getData());

                    $this->operationExecutor->execute(
                        $this->ficheWorkflow,
                        $ficheMatiere,
                        $transition,
                        $this->operationContextNormalizer->normalize(
                            workflow: $this->ficheWorkflow,
                            transitionName: $transition,
                            actor: $user,
                            input: $formData,
                        )->withRuntime(['previous_place' => $previousPlace]),
                    );

                    $histoEvent = new HistoriqueFicheMatiereEvent(
                        $ficheMatiere,
                        $user,
                        $previousPlace,
                        $metaDto->type,
                        $request,
                    );
                    $this->eventDispatcher->dispatch($histoEvent, HistoriqueFicheMatiereEvent::ADD_HISTORIQUE_FICHE_MATIERE);
                    $this->entityManager->flush();

                } catch (\Throwable $e) {
                    return $turboStream->stream('fiche_matiere_v2/turbo/apply_error.stream.html.twig', [
                        'ficheMatiere' => $ficheMatiere,
                        'transition' => $transition,
                        'type' => $metaDto->type,
                        'message' => $e->getMessage()
                    ]);
                }

                // toast success + refresh
                return $turboStream->stream('fiche_matiere_v2/turbo/apply_success.stream.html.twig', [
                    'ficheMatiere' => $ficheMatiere,
                    'transition' => $transition,
                    'type' => $metaDto->type,
                ]);
            }
        }

// rendu modal
        return $turboStream->streamOpenModalFromTemplates(
            'modal_title.' . $transition . '.' . $metaDto->type,
            'Fiche matière : ' . $ficheMatiere->getLibelle(),
            'fiche_matiere_v2/process/_apply.html.twig',
            [
                'ficheMatiere' => $ficheMatiere,
                'metaDto' => $metaDto,
                'transition' => $transition,
                'view' => $view,
                'form' => $form?->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'modal_submit.' . $transition . '.' . $metaDto->type,
                'submitDisabled' => ($view?->mode === 'report' && $view->canSubmit === false),
            ]
        );
    }

    /** @return array<string, mixed> */
    private function extractFormData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {
            return get_object_vars($data);
        }

        return [];
    }
}
