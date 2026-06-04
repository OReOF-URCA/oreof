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
    ) {
    }

    #[Route('/validation/valide/{etape}', name: 'app_validation_valider')]
    public function valide(
        ParcoursRepository     $parcoursRepository,
        FicheMatiereRepository $ficheMatiereRepository,
        LheoXML                $lheoXML,
        string                 $etape,
        Request                $request
    ): Response {
        $type = $request->query->get('type');
        $transition = $request->query->get('transition');
        $id = $request->query->get('id');
        $process = $this->validationProcess->getEtape($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);

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

        return $this->render('process_validation/_valide.html.twig', [
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
        ]);
    }

    #[Route('/validation/refuse/{etape}', name: 'app_validation_refuser')]
    public function refuse(
        ParcoursRepository  $parcoursRepository,
        FormationRepository $formationRepository,
        string              $etape,
        Request             $request
    ): Response {
        $type = $request->query->get('type');
        $transition = $request->query->get('transition');
        $id = $request->query->get('id');
        $process = $this->validationProcess->getEtape($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);

        switch ($type) {
            //            case 'formation':
            //                $objet = $formationRepository->find($id);
            //
            //                if ($objet === null) {
            //                    return JsonReponse::error('Formation non trouvée');
            //                }
            //
            //                $processData = $this->formationProcess->etatFormation($objet, $process);
            //
            //                if ($request->isMethod('POST')) {
            //                    return $this->formationProcess->refuseFormation($objet, $this->getUser(), $process, $etape, $request);
            //                }
            //                break;
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

        return $this->render('process_validation/_refuse.html.twig', [
            'process' => $process,
            'type' => $type,
            'id' => $id,
            'etape' => $etape,
            'objet' => $objet,
            'processData' => $processData ?? null,
            'meta' => $meta,
            'transition' => $transition
        ]);
    }

    #[Route('/validation/reserve/{etape}', name: 'app_validation_reserver')]
    public function reserve(
        FicheMatiereRepository $ficheMatiereRepository,
        ParcoursRepository     $parcoursRepository,
        FormationRepository    $formationRepository,
        string                 $etape,
        Request                $request
    ): Response {
        $type = $request->query->get('type');
        $transition = $request->query->get('transition');
        $id = $request->query->get('id');
        $process = $this->validationProcess->getEtape($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);

        switch ($type) {
            //            case 'formation':
            //                $objet = $formationRepository->find($id);
            //
            //                if ($objet === null) {
            //                    return JsonReponse::error('Formation non trouvée');
            //                }
            //
            //                $processData = $this->formationProcess->etatFormation($objet, $process);
            //
            //                if ($request->isMethod('POST')) {
            //                    return $this->formationProcess->reserveFormation($objet, $this->getUser(), $process, $etape, $request);
            //                }
            //                break;
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

        return $this->render('process_validation/_reserve.html.twig', [
            'process' => $process,
            'objet' => $objet,
            'processData' => $processData ?? null,
            'type' => $type,
            'id' => $id,
            'etape' => $etape,
            'transition' => $transition,
            'meta' => $meta
        ]);
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

    #[Route('/validation/valide-lot/{etape}/{transition}', name: 'app_validation_valider_lot')]
    public function valideLot(
        DpeParcoursRepository $dpeParcoursRepository,
        string                $etape,
        string                $transition,
        Request               $request
    ): Response {
        $fileName = null;
        $fileNameNote = null;
        $fileOriginalName = null;
        $fileNoteOriginalName = null;
        if ($request->isMethod('POST')) {
            $sParcours = $request->request->get('parcours');

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
        } else {
            $sParcours = $request->query->get('parcours');
        }
        $allParcours = explode(',', $sParcours);
        $process = $this->validationProcess->getEtapeFromAll($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $laisserPasser = false;
        $tParcours = [];

        foreach ($allParcours as $id) {
            // $objet = $dpeParcoursRepository->find($id);

            $dpe = $dpeParcoursRepository->find($id);
            if ($dpe === null) {
                return JsonReponse::error('Parcours non trouvé');
            }
            $tParcours[] = $dpe;
            //            if ($etape === 'cfvu') {
            //                $histo = $objet->getHistoriqueFormations();
            //                foreach ($histo as $h) {
            //                    if ($h->getEtape() === 'conseil') {
            //                        if ($h->getEtat() === 'laisserPasser' && ($laisserPasser === false || $laisserPasser->getCreated() < $h->getCreated())) {
            //                            $laisserPasser = $h;
            //                        } elseif ($h->getEtat() === 'valide' && $laisserPasser->getCreated() < $h->getCreated()) {
            //                            $laisserPasser = false;
            //                        }
            //                    }
            //                }
            //            }

            $processData = $this->parcoursProcess->etatParcours($dpe, $process);

            if ($request->isMethod('POST')) {
                $this->parcoursProcess->valideParcours(
                    $dpe,
                    $this->getUser(),
                    $transition,
                    $request,
                    $fileName,
                    $fileNameNote,
                    $fileOriginalName,
                    $fileNoteOriginalName,
                );
            }
        }

        if ($request->isMethod('POST')) {
            $this->toast('success', 'Parcours validés');
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_validation_dpe_index');
            } else {
                return $this->redirectToRoute('app_validation_composante_dpe_index', ['composante' => $request->query->get('composante')]);
            }
        }

        return $this->render('process_validation/_valide_lot.html.twig', [
            'formations' => $tParcours,
            'sParcours' => $sParcours,
            'process' => $process,
            'meta' => $meta,
            'type' => 'lot',
            'id' => $id,
            'etape' => $etape,
            'transition' => $transition,
            'processData' => $processData ?? null,
            'laisserPasser' => $laisserPasser,
        ]);
    }

    #[Route('/validation/refuse-lot/{etape}/{transition}', name: 'app_validation_refuser_lot')]
    public function refuseLot(
        DpeParcoursRepository $dpeParcoursRepository,
        string                $etape,
        string                $transition,
        Request               $request
    ): Response {
        if ($request->isMethod('POST')) {
            $sParcours = $request->request->get('parcours');
        } else {
            $sParcours = $request->query->get('parcours');
        }
        $allParcours = explode(',', $sParcours);

        $process = $this->validationProcess->getEtape($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $tParcours = [];
        foreach ($allParcours as $id) {
            $dpe = $dpeParcoursRepository->find($id);
            if ($dpe === null) {
                return JsonReponse::error('Parcours non trouvé');
            }
            $tParcours[] = $dpe;
            $processData = $this->parcoursProcess->etatParcours($dpe, $process);

            if ($request->isMethod('POST')) {
                $this->parcoursProcess->refuseParcours($dpe, $this->getUser(), $transition, $request);
            }
        }

        if ($request->isMethod('POST')) {
            $this->toast('success', 'Parcours refusés');
            return $this->redirectToRoute('app_validation_dpe_index');
        }

        return $this->render('process_validation/_refuse_lot.html.twig', [
            'formations' => $tParcours,
            'sParcours' => $sParcours,
            'process' => $process,
            'meta' => $meta,
            'type' => 'lot',
            'id' => $id,
            'etape' => $etape,
            'transition' => $transition,
            'objet' => $dpe,
            'processData' => $processData ?? null,
        ]);
    }

    #[Route('/validation/reserve-lot/{etape}/{transition}', name: 'app_validation_reserver_lot')]
    public function reserveLot(
        DpeParcoursRepository $dpeParcoursRepository,
        string                $etape,
        string                $transition,
        Request               $request
    ): Response {
        if ($request->isMethod('POST')) {
            $sParcours = $request->request->get('parcours');
        } else {
            $sParcours = $request->query->get('parcours');
        }
        $allParcours = explode(',', $sParcours);

        $process = $this->validationProcess->getEtape($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $tParcours = [];
        foreach ($allParcours as $id) {
            $dpe = $dpeParcoursRepository->find($id);
            if ($dpe === null) {
                return JsonReponse::error('Parcours non trouvé');
            }
            $tParcours[] = $dpe;
            $processData = $this->parcoursProcess->etatParcours($dpe, $process);

            if ($request->isMethod('POST')) {
                $this->parcoursProcess->reserveParcours($dpe, $this->getUser(), $transition, $request);
            }
        }

        if ($request->isMethod('POST')) {
            $this->toast('success', 'Formations marquées avec des réserves');
            return $this->redirectToRoute('app_validation_dpe_index');
        }

        return $this->render('process_validation/_reserve_lot.html.twig', [
            'formations' => $tParcours,
            'sParcours' => $sParcours,
            'process' => $process,
            'meta' => $meta,
            'transition' => $transition,
            'objet' => $dpe,
            'processData' => $processData ?? null,
            'type' => 'lot',
            'id' => $id,
            'etape' => $etape,
        ]);
    }
}
