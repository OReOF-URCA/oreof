<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursProcessController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/02/2026 18:47
 */

namespace App\Controller\Parcours;

use App\Classes\ValidationProcess;
use App\Controller\BaseController;
use App\Entity\DpeParcours;
use App\Entity\User;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\DpeParcoursRepository;
use App\Utils\TurboStreamResponseFactory;
use App\Workflow\Form\MetaDrivenFormFactory;
use App\Workflow\Handler\TransitionHandlerRegistry;
use App\Workflow\Handler\TransitionHandlerInterface;
use App\Workflow\Metadata\WorkflowMetaMapper;
use App\Workflow\ModalView\TransitionModalViewBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parcours/v2/process', name: 'parcours_process')]
class ParcoursProcessController extends BaseController
{

    private string $dir;

    public function __construct(
        private MetaDrivenFormFactory      $metaDrivenFormFactory,
        private WorkflowMetaMapper         $workflowMetaMapper,
        private TransitionHandlerRegistry  $transitionHandlers,
        private TransitionModalViewBuilder $transitionModalViewBuilder,
        private readonly EventDispatcherInterface      $eventDispatcher,
//        private readonly EntityManagerInterface        $entityManager,
        private readonly ValidationProcess             $validationProcess,
//        private readonly ValidationProcessFicheMatiere $validationProcessFicheMatiere,
//        private readonly ParcoursProcess               $parcoursProcess,
//        private readonly FicheMatiereProcess           $ficheMatiereProcess,
//        KernelInterface                                $kernel
    )
    {
        //$this->dir = $kernel->getProjectDir() . '/public/uploads/conseils/';
    }

//    #[Route('/valide/{dpeParcours}/{transition}', name: '_valider')]
//    public function valide(
//        DpeParcours                $dpeParcours,
//        string                     $transition,
//        TurboStreamResponseFactory $turboStream,
//        ParcoursRepository         $parcoursRepository,
//        FicheMatiereRepository     $ficheMatiereRepository,
//        LheoXML                    $lheoXML,
//        //string                 $etape,
//        Request                    $request
//    ): Response
//    {
//        if ($request->isMethod('POST')) {
//            //return $this->parcoursProcess->valideParcours($parcours, $this->getUser(), $transition, $request, $fileName);
//
//            //            $html =  $this->renderView('modal/success.stream.html.twig');
//            //
//            //            return $turboStream->stream($html);
//
//            $response = $this->render('parcours_v2/turbo/success.stream.html.twig', [
//                'parcours' => $dpeParcours->getParcours(),
//                'formation' => $dpeParcours->getParcours()?->getFormation(),
//            ]);
//            //['Content-Type' => 'text/vnd.turbo-stream.html']
//            $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html');
//            return $response;
//        }
//
//
//        $id = $request->query->get('id');
//        $meta = $this->validationProcess->getMetaFromTransition($transition);
//
//        // construction du formulaire
//
//        $form = $this->createFormBuilder(null, [
//            'attr' => ['id' => 'modal_form'],
//            'translation_domain' => 'form'
//        ]);
//
//        if (array_key_exists('hasDate', $meta) && $meta['hasDate'] === true) {
//            $form->add('date_valide', DateType::class, []);
//        }
//
//        if (array_key_exists('hasUpload', $meta) && $meta['hasUpload'] === true) {
//            $form->add('file', null, [
//                'required' => false,
//            ])
//                ->add('laisserPasser', CheckboxType::class, [
//                    'required' => false,
//                ]);
//        }
//
//        if (array_key_exists('hasArgumentaire', $meta) && $meta['hasArgumentaire'] === true) {
//            $form->add('argumentaire', TextareaType::class, [
//                'required' => true,
//            ]);
//        }
//
//        $form = $form->getForm();
//
//
//        $validLheo = null;
//        $xmlErrorArray = [];
//
//        $laisserPasser = false;
//        //        switch ($type) {
//        //            case 'parcours':
//        //upload
//        $fileName = '';
//        if ($request->files->has('file') && $request->files->get('file') !== null) {
//            $file = $request->files->get('file');
//            $fileName = md5(uniqid('', true)) . '.' . $file->guessExtension();
//            $file->move(
//                $this->dir,
//                $fileName
//            );
//        }
//
////        $process = $this->validationProcess->getEtape($etape);
//        $parcours = $dpeParcours->getParcours();
//
//        if ($parcours === null) {
//            return JsonReponse::error('Parcours non trouvé');
//        }
//
//
//        //                if ($etape === 'cfvu') {
//        //                    $laisserPasser = $getHistorique->getHistoriqueFormationLastStep($objet, 'conseil');
//        //                }
//
//        if (array_key_exists('hasValidLheo', $meta) && $meta['hasValidLheo'] === true) {
//            $erreursChampsParcours = $lheoXML->checkTextValuesAreLongEnough($parcours);
//            $validLheo = $lheoXML->isValidLHEO($parcours);
//            if ($validLheo === false || count($erreursChampsParcours) > 0) {
//                $xmlErrorArray = [];
//                foreach (libxml_get_errors() as $xmlError) {
//                    $xmlErrorArray[] = $lheoXML->decodeErrorMessages($xmlError->message);
//                }
//                $xmlErrorArray = array_merge($xmlErrorArray, $erreursChampsParcours);
//                libxml_clear_errors();
//            }
//        }
//
//        $processData = $this->parcoursProcess->etatParcours($dpeParcours, $meta);
//
//
//        if ($request->isMethod('POST')) {
//            //return $this->parcoursProcess->valideParcours($parcours, $this->getUser(), $transition, $request, $fileName);
//
//            return $this->render('modal/success.stream.html.twig');
//        }
//
//
//        return $turboStream->streamOpenModalFromTemplates(
//            'Valider le parcours',
//            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
//            'parcours_v2/process/_valide.html.twig',
//            [
//                'objet' => $parcours,
//                'dpeParcours' => $dpeParcours,
//                'form' => $form->createView(),
//                'validLheo' => $validLheo,
//                'xmlErrorArray' => $xmlErrorArray,
//                'processData' => $processData ?? null,
//                'laisserPasser' => $laisserPasser,
//                'meta' => $meta,
//                'transition' => $transition,
//            ],
//            '_ui/_footer_submit_cancel.html.twig',
//            [
//                'submitLabel' => 'Valider le parcours',
//            ]
//        );
//    }

    #[Route('/{type}/{dpeParcours}/{transition}', name: '_apply')]
    public function applyProcess(
        Request $request,
        TurboStreamResponseFactory $turboStream,
        DpeParcours                $dpeParcours,
        string                     $transition,
    ): Response
    {
        $rawMeta = $this->validationProcess->getMetaFromTransition($transition);
        $metaDto = $this->workflowMetaMapper->fromArray($rawMeta);

        $view = $this->transitionModalViewBuilder->build($transition, $dpeParcours, $rawMeta);

//        if ($view?->mode === 'report') {
//            $formId = $metaDto->form?->formId ?? 'modal_form';
//            $form = $this->metaDrivenFormFactory->createEmpty($formId);
//        } else {
            // si pas de form dans metadata : form vide
            if ($metaDto->form === null) {
                $form = $this->metaDrivenFormFactory->createEmpty();
            } else {
                $form = $this->metaDrivenFormFactory->create($metaDto->form, $transition);
            }
        // }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Blocage “report”
            if ($view?->mode === 'report' && $view?->canSubmit === false) {
                return $turboStream->streamToastError('Le traitement est bloqué par un contrôle.', false);
            }

            if ($form->isValid()) {
                try {
                    $user = $this->getCurrentUserOrFail();
                    $handlerCode = $metaDto->handlerCode ?? $transition; // fallback possible
                    $handler = $this->transitionHandlers->get($handlerCode);
                    if (!$handler instanceof TransitionHandlerInterface) {
                        throw new \LogicException(sprintf('Handler DPE attendu pour "%s".', $handlerCode));
                    }

                    $handler->handle(
                        $dpeParcours,
                        $user,
                        $metaDto,
                        $transition,
                        (array)$form->getData());
                    // l'étape c'est la clé du tableau $dpeParcours->getEtatValidation()
                    $etape = array_keys($dpeParcours->getEtatValidation())[0] ?? 'inconnue';
                    $histoEvent = new HistoriqueParcoursEvent($dpeParcours->getParcours(), $user, $etape, $metaDto->type, $request);
                    $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);

                } catch (\Throwable $e) {
                    return $turboStream->stream('parcours_v2/turbo/apply_error.stream.html.twig', [
                        'dpeParcours' => $dpeParcours,
                        'transition' => $transition,
                        'type' => $metaDto->type,
                        'message' => $e->getMessage()
                    ]);
                }

                // toast success + refresh
                return $turboStream->stream('parcours_v2/turbo/apply_success.stream.html.twig', [
                    'dpeParcours' => $dpeParcours,
                    'transition' => $transition,
                    'type' => $metaDto->type,
                ]);
            }
        }

// rendu modal
        return $turboStream->streamOpenModalFromTemplates(
            'modal_title.' . $transition . '.' . $metaDto->type,
            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
            'parcours_v2/process/_apply.html.twig',
            [
                'dpeParcours' => $dpeParcours,
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


//        $meta = $this->validationProcess->getMetaFromTransition($transition);
//        dump($meta);
//
//        switch ($transition) {
//            case 'reouvrir_mccc':
//                $title = 'Rouvrir le parcours pour modification des MCCC/BCC/Maquette avec CFVU';
//                $form = $this->createFormBuilder(null, [
//                    'attr' => ['id' => 'modal_form'],
//                    'translation_domain' => 'form'
//                ])
//                    ->add('argumentaire_demande_reouverture', TextareaType::class, [
//                        'required' => true,
//                    ])->getForm();
//                break;
//            case 'reouvrir_sans_cfvu':
//                $title = 'Rouvrir le parcours pour modification sans CFVU';
//                $form = $this->createFormBuilder(null, [
//                    'attr' => ['id' => 'modal_form']
//                ])
//                    ->add('argumentaire_demande_reouverture_sans_cfvu', TextareaType::class, [
//                        'required' => false,
//                    ])->getForm();
//                break;
//                case 'reouvrir_avant_publie':
//                $title = 'Rouvrir le parcours pour modification sans CFVU, avant publication';
//                $form = $this->createFormBuilder(null, [
//                    'attr' => ['id' => 'modal_form']
//                ])
//                    ->add('argumentaire_demande_reouverture_sans_cfvu', TextareaType::class, [
//                        'required' => false,
//                    ])->getForm();
//                break;
//        }
//
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            switch ($transition) {
//                case 'reouvrir_mccc':
//                   // traitement 1
//                    break;
//                case 'reouvrir_sans_cfvu':
//                    // traitement 1
//                    break;
//                case 'reouvrir_avant_publie':
//                    // traitement 1
//                    break;
//            }
//
//            // Si traitement OK => Toast + update, sinon NOK => TOAST + explication erreur
//        }
//
//        return $turboStream->streamOpenModalFromTemplates(
//            $title ?? 'Rouvrir le parcours',
//            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
//            'parcours_v2/process/_reouvrir.html.twig',
//            [
//                'parcours' => $dpeParcours->getParcours(),
//                'dpeParcours' => $dpeParcours,
//                'meta' => $meta,
//                'transition' => $transition,
//                'form' => $form->createView(),
//            ],
//            '_ui/_footer_submit_cancel.html.twig',
//            [
//                'submitLabel' => 'Valider la demande',
//            ]
//        );
    }

    #[Route('/lot/{transition}', name: '_apply_lot')]
    public function applyLotProcess(
        Request                    $request,
        TurboStreamResponseFactory $turboStream,
        DpeParcoursRepository      $dpeParcoursRepository,
        string                     $transition,
    ): Response
    {
        $selectedIds = $this->parseSelectedParcoursIds($request);

        if ($selectedIds === []) {
            return $turboStream->streamToastError('Aucun parcours sélectionné.', true);
        }

        $firstDpeParcours = null;
        foreach ($selectedIds as $id) {
            $candidate = $dpeParcoursRepository->find($id);
            if ($candidate instanceof DpeParcours) {
                $firstDpeParcours = $candidate;
                break;
            }
        }

        if (!$firstDpeParcours instanceof DpeParcours) {
            return $turboStream->streamToastError('Aucun parcours valide trouvé.', true);
        }

        $rawMeta = $this->validationProcess->getMetaFromTransition($transition);
        $metaDto = $this->workflowMetaMapper->fromArray($rawMeta);
        $view = $this->transitionModalViewBuilder->build($transition, $firstDpeParcours, $rawMeta);

        $form = $metaDto->form === null
            ? $this->metaDrivenFormFactory->createEmpty()
            : $this->metaDrivenFormFactory->create($metaDto->form, $transition);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($view?->mode === 'report' && $view?->canSubmit === false) {
                return $turboStream->streamToastError('Le traitement est bloqué par un contrôle de cohérence.', false);
            }

            if ($form->isValid()) {
                try {
                    $user = $this->getCurrentUserOrFail();
                    $handlerCode = $metaDto->handlerCode ?? $transition;
                    $processedCount = 0;
                    $processedParcours = [];

                    foreach ($selectedIds as $id) {
                        $dpeParcours = $dpeParcoursRepository->find($id);
                        if (!$dpeParcours instanceof DpeParcours) {
                            continue;
                        }

                        $handler = $this->transitionHandlers->get($handlerCode);
                        if (!$handler instanceof TransitionHandlerInterface) {
                            throw new \LogicException(sprintf('Handler DPE attendu pour "%s".', $handlerCode));
                        }

                        $handler->handle(
                            $dpeParcours,
                            $user,
                            $metaDto,
                            $transition,
                            (array)$form->getData()
                        );

                        $etape = array_keys($dpeParcours->getEtatValidation())[0] ?? 'inconnue';
                        $histoEvent = new HistoriqueParcoursEvent($dpeParcours->getParcours(), $user, $etape, $metaDto->type, $request);
                        $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);

                        $parcours = $dpeParcours->getParcours();
                        $formation = $parcours?->getFormation();
                        $processedParcours[] = [
                            'id' => $dpeParcours->getId(),
                            'parcours' => $parcours?->getDisplay() ?? 'Parcours inconnu',
                            'formation' => $formation?->getDisplay() ?? 'Formation inconnue',
                        ];
                        ++$processedCount;
                    }

                    return $turboStream->stream('parcours_v2/turbo/apply_lot_success.stream.html.twig', [
                        'transition' => $transition,
                        'type' => $metaDto->type,
                        'count' => $processedCount,
                        'processedParcours' => $processedParcours,
                    ]);
                } catch (\Throwable $e) {
                    return $turboStream->stream('parcours_v2/turbo/apply_lot_error.stream.html.twig', [
                        'transition' => $transition,
                        'type' => $metaDto->type,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $turboStream->streamOpenModalFromTemplates(
            'modal_title.' . $transition . '.' . $metaDto->type,
            $selectedIds === []
                ? 'Aucun parcours sélectionné'
                : sprintf('%d parcours sélectionné%s', count($selectedIds), count($selectedIds) > 1 ? 's' : ''),
            'parcours_v2/process/_apply_lot.html.twig',
            [
                'metaDto' => $metaDto,
                'transition' => $transition,
                'view' => $view,
                'form' => $form->createView(),
                'selectedParcours' => implode(',', $selectedIds),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'modal_submit.' . $transition . '.' . $metaDto->type,
                'submitDisabled' => ($view?->mode === 'report' && $view->canSubmit === false),
            ]
        );
    }

    /**
     * @return list<int>
     */
    private function parseSelectedParcoursIds(Request $request): array
    {
        $bag = $request->isMethod('POST') ? $request->request : $request->query;

        // Tente la lecture en tant que tableau (parcours[]=1&parcours[]=2)
        try {
            $raw = $bag->all('parcours');
            if ($raw !== []) {
                return array_values(array_unique(array_filter(array_map('intval', $raw), static fn(int $id) => $id > 0)));
            }
        } catch (\UnexpectedValueException) {
            // La valeur est une chaîne, on la traite ci-dessous
        }

        // Lecture en tant que chaîne scalaire (valeur séparée par des virgules)
        $raw = $bag->get('parcours');
        if (is_string($raw) && $raw !== '') {
            $parts = array_map('trim', explode(',', $raw));
            return array_values(array_unique(array_filter(array_map('intval', $parts), static fn(int $id) => $id > 0)));
        }

        return [];
    }

    private function getCurrentUserOrFail(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur non authentifié.');
        }

        return $user;
    }

//    #[Route('/refuser/{dpeParcours}/{transition}', name: '_refuser')]
//    public function refuser(
//        TurboStreamResponseFactory $turboStream,
//        DpeParcours                $dpeParcours,
//        string                     $transition,
//    ): Response
//    {
//        $meta = $this->validationProcess->getMetaFromTransition($transition);
//
//        $form = $this->createFormBuilder(null, [
//            'attr' => ['id' => 'modal_form'],
//            'translation_domain' => 'form'
//        ]);
//
//        if (array_key_exists('hasDate', $meta) && $meta['hasDate'] === true) {
//            $form->add('date_refus', DateType::class, []);
//        }
//
//        $form->add('argumentaire_refus', TextareaType::class, []);
//        $form = $form->getForm();
//
//        return $turboStream->streamOpenModalFromTemplates(
//            'Refuser le parcours',
//            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
//            'parcours_v2/process/_refuser.html.twig',
//            [
//                'parcours' => $dpeParcours->getParcours(),
//                'dpeParcours' => $dpeParcours,
//                'meta' => $meta,
//                'transition' => $transition,
//                'form' => $form->createView(),
//            ],
//            '_ui/_footer_submit_cancel.html.twig',
//            [
//                'submitLabel' => 'Valider le parcours',
//            ]
//        );
//    }

    #[Route('/reserver/{dpeParcours}/{transition}', name: '_reserver')]
    public function reserver(
        TurboStreamResponseFactory $turboStream,
        DpeParcours                $dpeParcours,
        string                     $transition,
    ): Response
    {
        $meta = $this->validationProcess->getMetaFromTransition($transition);


        $form = $this->createFormBuilder(null, [
            'attr' => ['id' => 'modal_form'],
            'translation_domain' => 'form'
        ]);

        if (array_key_exists('hasDate', $meta) && $meta['hasDate'] === true) {
            $form->add('date_reserve', DateType::class, []);
        }

        $form->add('argumentaire_reserve', TextareaType::class, []);
        $form = $form->getForm();

        return $turboStream->streamOpenModalFromTemplates(
            'Emettre des réservers sur le parcours',
            'Parcours : ' . $dpeParcours->getParcours()?->getDisplay(),
            'parcours_v2/process/_reserver.html.twig',
            [
                'parcours' => $dpeParcours->getParcours(),
                'dpeParcours' => $dpeParcours,
                'meta' => $meta,
                'form' => $form->createView(),
                'transition' => $transition,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Valider le parcours',
            ]
        );
    }
}
