<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ProcessValidationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/09/2025 20:58
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Classes\Process\FicheMatiereProcess;
use App\Classes\Process\ParcoursProcess;
use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessFicheMatiere;
use App\DTO\TranslatableKey;
use App\Enums\TypeModificationDpeEnum;
use App\Events\HistoriqueFormationEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Exception\FileUploadException;
use App\Repository\DpeParcoursRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Service\LheoXML;
use App\Service\SecureUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use App\Utils\TurboStreamResponseFactory;

class ProcessValidationController extends BaseController
{
    public function __construct(
        private readonly EventDispatcherInterface      $eventDispatcher,
        private readonly EntityManagerInterface        $entityManager,
        private readonly ValidationProcess             $validationProcess,
        private readonly ValidationProcessFicheMatiere $validationProcessFicheMatiere,
        private readonly ParcoursProcess               $parcoursProcess,
        private readonly FicheMatiereProcess           $ficheMatiereProcess,
        private readonly SecureUploadService $secureUploadService,
        private readonly \Symfony\Component\Workflow\WorkflowInterface $dpeFormationWorkflow,
    ) {
    }

    #[Route('/validation/valide/{etape}', name: 'app_validation_valider')]
    public function valide(
        ParcoursRepository     $parcoursRepository,
        FicheMatiereRepository $ficheMatiereRepository,
        FormationRepository    $formationRepository,
        TurboStreamResponseFactory $turboStream,
        LheoXML                $lheoXML,
        string                 $etape,
        Request                $request
    ): Response {
        $type = $request->query->get('type');
        $transition = $request->query->get('transition');
        $id = $request->query->get('id');

        if ($type === 'formation') {
            if ($transition !== 'transmettre') {
                if (!$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                    throw $this->createAccessDeniedException('Accès interdit aux responsables de formation/parcours.');
                }
            } else {
                $campagne = $this->getCampagneCollecte();
                if (!$campagne->isPeriodActive() && !$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                    throw $this->createAccessDeniedException('La campagne de collecte est fermée.');
                }
            }
        }

        if ($type === 'formation') {
            $placeMeta = $this->dpeFormationWorkflow->getMetadataStore()->getPlaceMetadata($etape);
            $process = [
                'label' => $placeMeta['label'] ?? $etape,
                'process' => $placeMeta['process'] ?? true,
                'color' => $placeMeta['color'] ?? 'info',
            ];
        } else {
            $process = $this->validationProcess->getEtape($etape);
        }

        $meta = $this->getTransitionMeta($type, $transition);

        $validLheo = null;
        $xmlErrorArray = [];


        $laisserPasser = false;
        switch ($type) {
            case 'parcours':
                $fileName = '';
                $fileNameNote = '';
                $fileOriginalName = null;
                $fileNoteOriginalName = null;

                try {
                    $uploadedFile = $this->secureUploadService->uploadFromRequest($request, 'file', 'conseils');
                    if ($uploadedFile !== null) {
                        $fileName = $uploadedFile->getStoredFilename();
                        $fileOriginalName = $uploadedFile->getOriginalFilename();
                    }

                    $uploadedFileNote = $this->secureUploadService->uploadFromRequest($request, 'fileNote', 'conseils');
                    if ($uploadedFileNote !== null) {
                        $fileNameNote = $uploadedFileNote->getStoredFilename();
                        $fileNoteOriginalName = $uploadedFileNote->getOriginalFilename();
                    }
                } catch (FileUploadException $exception) {
                    return JsonReponse::error($exception->getPublicMessage());
                }

                $process = $this->validationProcess->getEtape($etape);
                $objet = $parcoursRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $parcours = GetDpeParcours::getFromParcours($objet);

                //                if ($etape === 'cfvu') {
                //                    $laisserPasser = $getHistorique->getHistoriqueFormationLastStep($objet, 'conseil');
                //                }

                if ($parcours === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }
                if (array_key_exists('hasValidLheo', $meta) && $meta['hasValidLheo'] === true) {
                    $erreursChampsParcours = $lheoXML->checkTextValuesAreLongEnough($objet);
                    $validLheo = $lheoXML->isValidLHEO($objet);
                    if ($validLheo === false || count($erreursChampsParcours) > 0) {
                        $xmlErrorArray = [];
                        foreach (libxml_get_errors() as $xmlError) {
                            $xmlErrorArray[] = $lheoXML->decodeErrorMessages($xmlError->message);
                        }
                        $xmlErrorArray = array_merge($xmlErrorArray, $erreursChampsParcours);
                        libxml_clear_errors();
                    }
                }

                $processData = $this->parcoursProcess->etatParcours($parcours, $process);//todo: process??

                if ($request->isMethod('POST')) {
                    return $this->parcoursProcess->valideParcours(
                        $parcours,
                        $this->getUser(),
                        $transition,
                        $request,
                        $fileName,
                        $fileNameNote,
                        $fileOriginalName,
                        $fileNoteOriginalName,
                    );
                }

                break;
            case 'formation':
                $objet = $formationRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Formation non trouvée');
                }

                $campagne = $this->getCampagneCollecte();
                $dpeFormation = $this->entityManager->getRepository(\App\Entity\DpeFormation::class)->findOneBy([
                    'formation' => $objet,
                    'campagneCollecte' => $campagne,
                ]);

                if ($dpeFormation === null) {
                    $dpeFormation = new \App\Entity\DpeFormation();
                    $dpeFormation->setFormation($objet);
                    $dpeFormation->setCampagneCollecte($campagne);
                    $dpeFormation->setEtatValidation(['brouillon' => 1]);
                    $this->entityManager->persist($dpeFormation);
                    $this->entityManager->flush();
                }

                if ($request->isMethod('POST')) {
                    $histoEvent = new \App\Events\HistoriqueFormationEvent($objet, $this->getUser(), $etape, 'valide', $request);
                    $this->eventDispatcher->dispatch($histoEvent, \App\Events\HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);

                    $motifs = [];
                    if ($request->request->has('laisserPasser')) {
                        $motifs['laisserPasser'] = $request->request->get('laisserPasser');
                    }
                    if ($request->request->has('argumentaire')) {
                        $motifs['motif'] = $request->request->get('argumentaire');
                    }

                    $this->dpeFormationWorkflow->apply($dpeFormation, $transition, $motifs);
                    $this->entityManager->flush();

                    if ($this->isTurboFrameRequest()) {
                        return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
                            'message' => 'Validation de l\'offre enregistrée',
                        ]);
                    }

                    return JsonReponse::success('Validation de l\'offre enregistrée');
                }

                $processData = new \App\DTO\ProcessData();
                $processData->place = $this->dpeFormationWorkflow->getMarking($dpeFormation);
                $processData->transitions = $this->dpeFormationWorkflow->getEnabledTransitions($dpeFormation);

                break;
            case 'ficheMatiere':
                $process = $this->validationProcessFicheMatiere->getEtape($etape);

                $objet = $ficheMatiereRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Fiche matière non trouvée');
                }

                $processData = $this->ficheMatiereProcess->etatFicheMatiere($objet, $process);

                if ($request->isMethod('POST')) {
                    return $this->ficheMatiereProcess->valideFicheMatiere($objet, $this->getUser(), $process, $etape, $request);
                }
                break;
        }
        $otherFormations = [];
        $existingPvs = [];
        $existingNotes = [];
        if ($type === 'formation' && isset($objet)) {
            $otherFormations = $this->entityManager->getRepository(\App\Entity\Formation::class)->findBy([
                'composantePorteuse' => $objet->getComposantePorteuse(),
            ]);
            $composante = $objet->getComposantePorteuse();
            if ($composante !== null) {
                $existingPvs = $this->entityManager->getRepository(\App\Entity\DocumentConseil::class)->findBy([
                    'composante' => $composante,
                    'type' => 'pv',
                ]);
                $existingNotes = $this->entityManager->getRepository(\App\Entity\DocumentConseil::class)->findBy([
                    'composante' => $composante,
                    'type' => 'note_explicative',
                ]);
            }
        }

        $viewData = [
            'objet' => $objet,
            'process' => $process,
            'type' => $type,
            'id' => $id,
            'validLheo' => $validLheo,
            'xmlErrorArray' => $xmlErrorArray,
            'etape' => $etape,
            'processData' => $processData ?? null,
            'laisserPasser' => $laisserPasser,
            'meta' => $meta,
            'transition' => $transition,
            'otherFormations' => $otherFormations,
            'existingPvs' => $existingPvs,
            'existingNotes' => $existingNotes,
            'isTurbo' => $this->isTurboFrameRequest(),
        ];

        $footer = '_ui/_footer_submit_cancel.html.twig';
        if (isset($process['check']) && $process['check'] === true) {
            if (!isset($processData) || $processData->valid !== true) {
                $footer = '_ui/_footer_cancel.html.twig';
            }
        }

        $subtitle = null;
        if ($objet !== null) {
            if (method_exists($objet, 'getDisplay')) {
                $subtitle = ucfirst($type === 'formation' ? 'offre' : $type) . ' : ' . $objet->getDisplay();
            } elseif (method_exists($objet, 'getLibelle')) {
                $subtitle = ucfirst($type === 'formation' ? 'offre' : $type) . ' : ' . $objet->getLibelle();
            }
        }

        return $turboStream->streamOpenModalFromTemplates(
            $meta['label'] ?? 'Validation',
            $subtitle,
            'process_validation/_valide.html.twig',
            $viewData,
            $footer
        );
    }

    #[Route('/validation/refuse/{etape}', name: 'app_validation_refuser')]
    public function refuse(
        ParcoursRepository  $parcoursRepository,
        FormationRepository $formationRepository,
        TurboStreamResponseFactory $turboStream,
        string              $etape,
        Request             $request
    ): Response {
        $type = $request->query->get('type');
        $transition = $request->query->get('transition');
        $id = $request->query->get('id');

        if ($type === 'formation') {
            if (!$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Accès interdit aux responsables de formation/parcours.');
            }
        }

        if ($type === 'formation') {
            $placeMeta = $this->dpeFormationWorkflow->getMetadataStore()->getPlaceMetadata($etape);
            $process = [
                'label' => $placeMeta['label'] ?? $etape,
                'process' => $placeMeta['process'] ?? true,
                'color' => $placeMeta['color'] ?? 'info',
            ];
        } else {
            $process = $this->validationProcess->getEtape($etape);
        }

        $meta = $this->getTransitionMeta($type, $transition);

        switch ($type) {
            case 'formation':
                $objet = $formationRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Formation non trouvée');
                }

                $campagne = $this->getCampagneCollecte();
                $dpeFormation = $this->entityManager->getRepository(\App\Entity\DpeFormation::class)->findOneBy([
                    'formation' => $objet,
                    'campagneCollecte' => $campagne,
                ]);

                if ($dpeFormation === null) {
                    return JsonReponse::error('Validation de formation non initialisée');
                }

                if ($request->isMethod('POST')) {
                    $histoEvent = new \App\Events\HistoriqueFormationEvent($objet, $this->getUser(), $etape, 'refuse', $request);
                    $this->eventDispatcher->dispatch($histoEvent, \App\Events\HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);

                    $motifs = [];
                    if ($request->request->has('argumentaire')) {
                        $motifs['motif'] = $request->request->get('argumentaire');
                    }

                    $this->dpeFormationWorkflow->apply($dpeFormation, $transition, $motifs);
                    $this->entityManager->flush();

                    if ($this->isTurboFrameRequest()) {
                        return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
                            'message' => 'Refus de l\'offre enregistré',
                        ]);
                    }

                    return JsonReponse::success('Refus de l\'offre enregistré');
                }

                $processData = new \App\DTO\ProcessData();
                $processData->place = $this->dpeFormationWorkflow->getMarking($dpeFormation);
                $processData->transitions = $this->dpeFormationWorkflow->getEnabledTransitions($dpeFormation);

                break;
            case 'parcours':
                $objet = $parcoursRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $parcours = GetDpeParcours::getFromParcours($objet);

                if ($parcours === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $processData = $this->parcoursProcess->etatParcours($parcours, $process);//todo: process?

                if ($request->isMethod('POST')) {
                    return $this->parcoursProcess->refuseParcours($parcours, $this->getUser(), $transition, $request);
                }
                break;
            case 'ficheMatiere':
                $objet = $formationRepository->find($id);
                if ($objet === null) {
                    return JsonReponse::error('Fiche EC/matière non trouvée');
                }
                //                $place = $dpeWorkflow->getMarking($objet);
                //                $transitions = $dpeWorkflow->getEnabledTransitions($objet);
                break;
        }

        $viewData = [
            'process' => $process,
            'type' => $type,
            'id' => $id,
            'etape' => $etape,
            'objet' => $objet,
            'processData' => $processData ?? null,
            'meta' => $meta,
            'transition' => $transition,
            'isTurbo' => $this->isTurboFrameRequest(),
        ];

        if ($this->isTurboFrameRequest()) {
            return $turboStream->streamOpenModalFromTemplates(
                $meta['label'] ?? 'Refus / Retour modifications',
                null,
                'process_validation/_refuse.html.twig',
                $viewData,
                '_ui/_footer_submit_cancel.html.twig'
            );
        }

        return $this->render('process_validation/_refuse.html.twig', $viewData);
    }

    #[Route('/validation/reserve/{etape}', name: 'app_validation_reserver')]
    public function reserve(
        FicheMatiereRepository $ficheMatiereRepository,
        ParcoursRepository     $parcoursRepository,
        FormationRepository    $formationRepository,
        TurboStreamResponseFactory $turboStream,
        string                 $etape,
        Request                $request
    ): Response {
        $type = $request->query->get('type');
        $transition = $request->query->get('transition');
        $id = $request->query->get('id');

        if ($type === 'formation') {
            if (!$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Accès interdit aux responsables de formation/parcours.');
            }
        }

        if ($type === 'formation') {
            $placeMeta = $this->dpeFormationWorkflow->getMetadataStore()->getPlaceMetadata($etape);
            $process = [
                'label' => $placeMeta['label'] ?? $etape,
                'process' => $placeMeta['process'] ?? true,
                'color' => $placeMeta['color'] ?? 'info',
            ];
        } else {
            $process = $this->validationProcess->getEtape($etape);
        }

        $meta = $this->getTransitionMeta($type, $transition);

        switch ($type) {
            case 'formation':
                $objet = $formationRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Formation non trouvée');
                }

                $campagne = $this->getCampagneCollecte();
                $dpeFormation = $this->entityManager->getRepository(\App\Entity\DpeFormation::class)->findOneBy([
                    'formation' => $objet,
                    'campagneCollecte' => $campagne,
                ]);

                if ($dpeFormation === null) {
                    return JsonReponse::error('Validation de formation non initialisée');
                }

                if ($request->isMethod('POST')) {
                    $histoEvent = new \App\Events\HistoriqueFormationEvent($objet, $this->getUser(), $etape, 'reserve', $request);
                    $this->eventDispatcher->dispatch($histoEvent, \App\Events\HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);

                    $motifs = [];
                    if ($request->request->has('argumentaire')) {
                        $motifs['motif'] = $request->request->get('argumentaire');
                    }

                    $this->dpeFormationWorkflow->apply($dpeFormation, $transition, $motifs);
                    $this->entityManager->flush();

                    if ($this->isTurboFrameRequest()) {
                        return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
                            'message' => 'Réserve de l\'offre enregistrée',
                        ]);
                    }

                    return JsonReponse::success('Réserve de l\'offre enregistrée');
                }

                $processData = new \App\DTO\ProcessData();
                $processData->place = $this->dpeFormationWorkflow->getMarking($dpeFormation);
                $processData->transitions = $this->dpeFormationWorkflow->getEnabledTransitions($dpeFormation);

                break;
            case 'parcours':
                $objet = $parcoursRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $parcours = GetDpeParcours::getFromParcours($objet);

                if ($parcours === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $processData = $this->parcoursProcess->etatParcours($parcours, $process);//todo: process?

                if ($request->isMethod('POST')) {
                    return $this->parcoursProcess->reserveParcours($parcours, $this->getUser(), $transition, $request);
                }
                break;
            case 'ficheMatiere':
                $process = $this->validationProcessFicheMatiere->getEtape($etape);

                $objet = $ficheMatiereRepository->find($id);

                if ($objet === null) {
                    return JsonReponse::error('Fiche matière non trouvée');
                }

                $processData = $this->ficheMatiereProcess->etatFicheMatiere($objet, $process);

                if ($request->isMethod('POST')) {
                    return $this->ficheMatiereProcess->reserveFicheMatiere($objet, $this->getUser(), $process, $etape, $request);
                }
                break;
        }

        $viewData = [
            'process' => $process,
            'objet' => $objet,
            'processData' => $processData ?? null,
            'type' => $type,
            'id' => $id,
            'etape' => $etape,
            'transition' => $transition,
            'meta' => $meta,
            'isTurbo' => $this->isTurboFrameRequest(),
        ];

        if ($this->isTurboFrameRequest()) {
            return $turboStream->streamOpenModalFromTemplates(
                $meta['label'] ?? 'Réserve / Demande de modifications',
                null,
                'process_validation/_reserve.html.twig',
                $viewData,
                '_ui/_footer_submit_cancel.html.twig'
            );
        }

        return $this->render('process_validation/_reserve.html.twig', $viewData);
    }

    #[Route('/validation/edit/{type}/{id}', name: 'app_validation_edit')]
    public function edit(
        DpeParcoursRepository $dpeParcoursRepository,
        ParcoursRepository    $parcoursRepository,
        FormationRepository   $formationRepository,
        Request               $request,
        string                $type,
        int                   $id
    ): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $place = $data['etat_dpe'];

            //mise à jour du workflow
            switch ($type) {
                case 'formation':
                    $objet = $formationRepository->find($id);

                    if ($objet === null) {
                        return JsonReponse::error('Formation non trouvée');
                    }

                    if ($objet->isHasParcours() === false) {
                        //formation sans parcours
                        $dpe = $dpeParcoursRepository->findOneBy(['parcours' => $objet->getParcours()->first(), 'campagneCollecte' => $this->getCampagneCollecte()]);
                        if ($dpe === null) {
                            return JsonReponse::error('Formation non trouvée');
                        }
                        $dpe->setEtatValidation([$place => 1]);
                        $histoEvent = new HistoriqueFormationEvent($objet, $this->getUser(), $data['etat'], 'valide', $request);
                        $this->eventDispatcher->dispatch($histoEvent, HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);
                        $this->entityManager->flush();
                        return JsonReponse::success('Validation modifiée');
                    }

                    break;
                case 'parcours':
                    //récupérer la transition de départ en fonction de la place selectionnée

                    $objet = $parcoursRepository->find($id);
                    $dpe = $dpeParcoursRepository->findOneBy(['parcours' => $objet, 'campagneCollecte' => $this->getCampagneCollecte()]);
                    if ($objet === null) {
                        return JsonReponse::error('Parcours non trouvé');
                    }

                    $dpe->setEtatValidation([$place => 1]);
                    //mettre à jour l'historique
                    $histoEvent = new HistoriqueParcoursEvent($objet, $this->getUser(), $place, 'valide', $request);
                    $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                    $this->entityManager->flush();
                    return JsonReponse::success('Validation modifiée');
            }

            return JsonReponse::error('Erreur lors de la modification de l\'état de validation');
        }

        return $this->render('process_validation/_edit.html.twig', [
            'etats' => $this->validationProcess->getProcess(),
            'type' => $type,
            'id' => $id,
        ]);
    }

    #[Route('/type_modif_dpe/edit/{type}/{id}', name: 'app_type_modif_dpe_edit')]
    public function typeModifDpeEdit(
        DpeParcoursRepository $dpeParcoursRepository,
        ParcoursRepository    $parcoursRepository,
        FormationRepository   $formationRepository,
        Request               $request,
        string                $type,
        int                   $id
    ): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $place = $data['type_modif_dpe'];

            //mise à jour du workflow
            switch ($type) {
                case 'formation':
                    $objet = $formationRepository->find($id);

                    if ($objet === null) {
                        return JsonReponse::error('Formation non trouvée');
                    }

                    if ($objet->isHasParcours() === false) {
                        //formation sans parcours
                        $dpe = $dpeParcoursRepository->findOneBy(['parcours' => $objet->getParcours()->first(), 'campagneCollecte' => $this->getCampagneCollecte()]);
                        if ($dpe === null) {
                            return JsonReponse::error('Formation non trouvée');
                        }
                        $dpe->setEtatReconduction(TypeModificationDpeEnum::from($place));
                        $this->entityManager->flush();
                        return JsonReponse::success('Validation modifiée');
                    }

                    break;
                case 'parcours':
                    //récupérer la transition de départ en fonction de la place selectionnée

                    $objet = $parcoursRepository->find($id);
                    $dpe = $dpeParcoursRepository->findOneBy(['parcours' => $objet, 'campagneCollecte' => $this->getCampagneCollecte()]);
                    if ($objet === null) {
                        return JsonReponse::error('Parcours non trouvé');
                    }

                    $dpe->setEtatReconduction(TypeModificationDpeEnum::from($place));
                    //mettre à jour l'historique
                    $this->entityManager->flush();
                    return JsonReponse::success('Validation modifiée');
            }

            return JsonReponse::error('Erreur lors de la modification de l\'état de validation');
        }

        return $this->render('process_validation/_edit.html.twig', [
            'etats' => $this->validationProcess->getProcess(),
            'type' => $type,
            'id' => $id,
        ]);
    }

//    #[Route('/validation/valide-lot/{etape}/{transition}', name: 'app_validation_valider_lot')]
//    public function valideLot(
//        DpeParcoursRepository $dpeParcoursRepository,
//        string                $etape,
//        string                $transition,
//        Request               $request
//    ): Response {
//        $fileName = null;
//        $fileNameNote = null;
//        $fileOriginalName = null;
//        $fileNoteOriginalName = null;
//        if ($request->isMethod('POST')) {
//            $sParcours = $request->request->get('parcours');
//
//            try {
//                $uploadedFile = $this->secureUploadService->uploadFromRequest($request, 'file', 'conseils');
//                if ($uploadedFile !== null) {
//                    $fileName = $uploadedFile->getStoredFilename();
//                    $fileOriginalName = $uploadedFile->getOriginalFilename();
//                }
//
//                $uploadedFileNote = $this->secureUploadService->uploadFromRequest($request, 'fileNote', 'conseils');
//                if ($uploadedFileNote !== null) {
//                    $fileNameNote = $uploadedFileNote->getStoredFilename();
//                    $fileNoteOriginalName = $uploadedFileNote->getOriginalFilename();
//                }
//            } catch (FileUploadException $exception) {
//                return JsonReponse::error($exception->getPublicMessage());
//            }
//        } else {
//            $sParcours = $request->query->get('parcours');
//        }
//        $allParcours = explode(',', $sParcours);
//        $process = $this->validationProcess->getEtapeFromAll($etape);
//        $meta = $this->validationProcess->getMetaFromTransition($transition);
//        $laisserPasser = false;
//        $tParcours = [];
//
//        foreach ($allParcours as $id) {
//            // $objet = $dpeParcoursRepository->find($id);
//
//            $dpe = $dpeParcoursRepository->find($id);
//            if ($dpe === null) {
//                return JsonReponse::error('Parcours non trouvé');
//            }
//            $tParcours[] = $dpe;
//            //            if ($etape === 'cfvu') {
//            //                $histo = $objet->getHistoriqueFormations();
//            //                foreach ($histo as $h) {
//            //                    if ($h->getEtape() === 'conseil') {
//            //                        if ($h->getEtat() === 'laisserPasser' && ($laisserPasser === false || $laisserPasser->getCreated() < $h->getCreated())) {
//            //                            $laisserPasser = $h;
//            //                        } elseif ($h->getEtat() === 'valide' && $laisserPasser->getCreated() < $h->getCreated()) {
//            //                            $laisserPasser = false;
//            //                        }
//            //                    }
//            //                }
//            //            }
//
//            $processData = $this->parcoursProcess->etatParcours($dpe, $process);
//
//            if ($request->isMethod('POST')) {
//                $this->parcoursProcess->valideParcours(
//                    $dpe,
//                    $this->getUser(),
//                    $transition,
//                    $request,
//                    $fileName,
//                    $fileNameNote,
//                    $fileOriginalName,
//                    $fileNoteOriginalName,
//                );
//            }
//        }
//
//        if ($request->isMethod('POST')) {
//            $this->toast('success', 'Parcours validés');
//            if ($request->isXmlHttpRequest()) {
//                return (new JsonReponse(
//                    Response::HTTP_OK,
//                    'Parcours validés',
//                    [
//                        'count' => count($tParcours),
//                        'transition' => $transition,
//                        'etape' => $etape,
//                    ]
//                ))->getReponse();
//            }
//
//            $redirectRoute = $this->isGranted('ROLE_ADMIN') ? 'app_validation_dpe_index' : 'app_validation_composante_dpe_index';
//
//            return $redirectRoute === 'app_validation_dpe_index'
//                ? $this->redirectToRoute($redirectRoute)
//                : $this->redirectToRoute($redirectRoute, ['composante' => $request->query->get('composante')]);
//        }
//
//        return $this->render('process_validation/_valide_lot.html.twig', [
//            'formations' => $tParcours,
//            'sParcours' => $sParcours,
//            'process' => $process,
//            'meta' => $meta,
//            'type' => 'lot',
//            'id' => $id,
//            'etape' => $etape,
//            'transition' => $transition,
//            'processData' => $processData ?? null,
//            'laisserPasser' => $laisserPasser,
//        ]);
//    }

//    #[Route('/validation/refuse-lot/{etape}/{transition}', name: 'app_validation_refuser_lot')]
//    public function refuseLot(
//        DpeParcoursRepository $dpeParcoursRepository,
//        string                $etape,
//        string                $transition,
//        Request               $request
//    ): Response {
//        if ($request->isMethod('POST')) {
//            $sParcours = $request->request->get('parcours');
//        } else {
//            $sParcours = $request->query->get('parcours');
//        }
//        $allParcours = explode(',', $sParcours);
//
//        $process = $this->validationProcess->getEtape($etape);
//        $meta = $this->validationProcess->getMetaFromTransition($transition);
//        $tParcours = [];
//        foreach ($allParcours as $id) {
//            $dpe = $dpeParcoursRepository->find($id);
//            if ($dpe === null) {
//                return JsonReponse::error('Parcours non trouvé');
//            }
//            $tParcours[] = $dpe;
//            $processData = $this->parcoursProcess->etatParcours($dpe, $process);
//
//            if ($request->isMethod('POST')) {
//                $this->parcoursProcess->refuseParcours($dpe, $this->getUser(), $transition, $request);
//            }
//        }
//
//        if ($request->isMethod('POST')) {
//            $this->toast('success', 'Parcours refusés');
//            if ($request->isXmlHttpRequest()) {
//                return (new JsonReponse(
//                    Response::HTTP_OK,
//                    'Parcours refusés',
//                    [
//                        'count' => count($tParcours),
//                        'transition' => $transition,
//                        'etape' => $etape,
//                    ]
//                ))->getReponse();
//            }
//
//            return $this->redirectToRoute('app_validation_dpe_index');
//        }
//
//        return $this->render('process_validation/_refuse_lot.html.twig', [
//            'formations' => $tParcours,
//            'sParcours' => $sParcours,
//            'process' => $process,
//            'meta' => $meta,
//            'type' => 'lot',
//            'id' => $id,
//            'etape' => $etape,
//            'transition' => $transition,
//            'objet' => $dpe,
//            'processData' => $processData ?? null,
//        ]);
//    }

//    #[Route('/validation/reserve-lot/{etape}/{transition}', name: 'app_validation_reserver_lot')]
//    public function reserveLot(
//        DpeParcoursRepository $dpeParcoursRepository,
//        string                $etape,
//        string                $transition,
//        Request               $request
//    ): Response {
//        if ($request->isMethod('POST')) {
//            $sParcours = $request->request->get('parcours');
//        } else {
//            $sParcours = $request->query->get('parcours');
//        }
//        $allParcours = explode(',', $sParcours);
//
//        $process = $this->validationProcess->getEtape($etape);
//        $meta = $this->validationProcess->getMetaFromTransition($transition);
//        $tParcours = [];
//        foreach ($allParcours as $id) {
//            $dpe = $dpeParcoursRepository->find($id);
//            if ($dpe === null) {
//                return JsonReponse::error('Parcours non trouvé');
//            }
//            $tParcours[] = $dpe;
//            $processData = $this->parcoursProcess->etatParcours($dpe, $process);
//
//            if ($request->isMethod('POST')) {
//                $this->parcoursProcess->reserveParcours($dpe, $this->getUser(), $transition, $request);
//            }
//        }
//
//        if ($request->isMethod('POST')) {
//            $this->toast('success', 'Formations marquées avec des réserves');
//            if ($request->isXmlHttpRequest()) {
//                return (new JsonReponse(
//                    Response::HTTP_OK,
//                    'Formations marquées avec des réserves',
//                    [
//                        'count' => count($tParcours),
//                        'transition' => $transition,
//                        'etape' => $etape,
//                    ]
//                ))->getReponse();
//            }
//
//            return $this->redirectToRoute('app_validation_dpe_index');
//        }
//
//        return $this->render('process_validation/_reserve_lot.html.twig', [
//            'formations' => $tParcours,
//            'sParcours' => $sParcours,
//            'process' => $process,
//            'meta' => $meta,
//            'transition' => $transition,
//            'objet' => $dpe,
//            'processData' => $processData ?? null,
//            'type' => 'lot',
//            'id' => $id,
//            'etape' => $etape,
//        ]);
//    }

    private function getTransitionMeta(string $type, string $transition): array
    {
        if ($type === 'formation') {
            $transitions = $this->dpeFormationWorkflow->getDefinition()->getTransitions();
            foreach ($transitions as $t) {
                if ($t->getName() === $transition) {
                    return $this->dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($t);
                }
            }
            return [];
        }

        return $this->validationProcess->getMetaFromTransition($transition);
    }
}
