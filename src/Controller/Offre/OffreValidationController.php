<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DocumentConseil;
use App\Entity\DpeFormation;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use App\Entity\User;
use App\Exception\FileUploadException;
use App\Repository\AnneeRepository;
use App\Repository\DocumentConseilRepository;
use App\Repository\DpeFormationRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Service\Offre\RemplissageSuspension;
use App\Service\SecureUploadService;
use App\Service\Validation\OffreValidationService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OffreValidationController extends BaseController
{
    #[Route('/offre/composante/{composante}/validation/{transition}', name: 'offre_v2_composante_valider', methods: ['GET', 'POST'])]
    public function composanteValidation(
        Composante $composante,
        string $transition,
        Request $request,
        FormationRepository $formationRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        DpeFormationRepository $dpeFormationRepository,
        DocumentConseilRepository $documentConseilRepository,
        AnneeRepository $anneeRepository,
        PlateformeAdmissionParametreRepository $plateformeParamRepository,
        OffreValidationService $offreValidationService,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $em,
        WorkflowInterface $dpeFormationWorkflow,
        TurboStreamResponseFactory $turboStream,
        RemplissageSuspension $remplissageSuspension,
        TranslatorInterface $translator,
    ): Response {
        $campagne = $this->getCampagneCollecte();

        // 1. Contrôle des droits d'accès
        if ($transition === 'transmettre') {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->denyAccessUnlessGranted('MANAGE', [
                    'route' => 'app_composante',
                    'subject' => $composante,
                ]);
            }
            if (!$campagne->isPeriodActive() && !$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('La campagne de collecte est fermée.');
            }
        } else {
            if (!$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Accès interdit aux responsables de composante.');
            }
        }

        // 2. Récupérer les métadonnées de la transition du workflow
        $meta = null;
        foreach ($dpeFormationWorkflow->getDefinition()->getTransitions() as $t) {
            if ($t->getName() === $transition) {
                $meta = $dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($t);
                break;
            }
        }
        if ($meta === null) {
            throw $this->createNotFoundException(sprintf('Transition « %s » inconnue.', $transition));
        }
        $isRefuse = ($meta['type'] ?? '') === 'reserver' || str_starts_with($transition, 'reserver');

        // 3. Récupérer toutes les formations de la composante et leurs parcours
        [$allFormations, $formationsData] = $this->buildFormationsDataForComposante(
            $composante,
            $campagne,
            $formationRepository,
            $dpeParcoursRepository,
            $dpeFormationRepository,
            $anneeRepository,
            $plateformeParamRepository,
            $offreValidationService,
        );

        // 4. Traitement POST
        if ($request->isMethod('POST')) {
            $errors = $this->collectValidationErrors($composante, $request, $meta, $isRefuse);
            if ($errors !== []) {
                return $turboStream->stream('offre_v2/turbo/validation_errors.stream.html.twig', [
                    'title' => sprintf('Enregistrement impossible : %d point%s à corriger', count($errors), count($errors) > 1 ? 's' : ''),
                    'errors' => $errors,
                ]);
            }

            $selectedFormationIds = array_map('intval', (array)$request->request->all('formations'));
            $selectedParcoursIds = array_map('intval', (array)$request->request->all('parcours'));

            // Validation partielle possible : on ne garde que les formations cochées qui peuvent franchir la transition.
            $formationsAValider = [];
            $nbIgnorees = 0;
            foreach ($allFormations as $forma) {
                $fId = $forma->getId();
                if (!in_array($fId, $selectedFormationIds, true)) {
                    continue;
                }

                $dpeF = $dpeFormationByFormationId[$fId] ?? null;
                if ($dpeF === null) {
                    $dpeF = new DpeFormation();
                    $dpeF->setFormation($forma);
                    $dpeF->setCampagneCollecte($campagne);
                    $dpeF->setEtatValidation(['brouillon' => 1]);
                }

                if (!$dpeFormationWorkflow->can($dpeF, $transition)) {
                    ++$nbIgnorees;
                    continue;
                }
                $formationsAValider[] = ['formation' => $forma, 'dpeFormation' => $dpeF];
            }

            if ($formationsAValider === []) {
                return $turboStream->stream('offre_v2/turbo/validation_errors.stream.html.twig', [
                    'title' => 'Enregistrement impossible',
                    'errors' => ['Aucune des formations cochées ne peut passer cette étape : elles ont déjà été traitées.'],
                ]);
            }

            $dateStr = (string)$request->request->get('date');
            $dateConseil = !empty($dateStr) ? new \DateTime($dateStr) : new \DateTime();

            $commentaire = (string)$request->request->get('commentaire');
            $laisserPasser = (bool)$request->request->get('laisserPasser');
            $laisserPasserJustif = (string)$request->request->get('laisserPasserJustif');

            // Documents PV
            $docPv = null;
            $pvId = $request->request->get('pv_id');
            if ($pvId && is_numeric($pvId) && (int)$pvId > 0) {
                $docPv = $documentConseilRepository->findOneBy(['id' => (int)$pvId, 'composante' => $composante, 'type' => 'pv']);
                if ($docPv === null) {
                    return $turboStream->streamToastError('Le PV sélectionné est introuvable pour cette composante.');
                }
            } elseif ($request->files->has('file') && $request->files->get('file') !== null) {
                try {
                    $uploadedPv = $secureUploadService->uploadFromRequest($request, 'file', 'conseils');
                    if ($uploadedPv !== null) {
                        $docPv = new DocumentConseil();
                        $docPv->setType('pv');
                        $docPv->setFilename($uploadedPv->getStoredFilename());
                        $docPv->setOriginalFilename($uploadedPv->getOriginalFilename());
                        $docPv->setDateConseil($dateConseil);
                        /** @var \App\Entity\User|null $currentUser */
                        $currentUser = $this->getUser() instanceof \App\Entity\User ? $this->getUser() : null;
                        $docPv->setUploadedBy($currentUser);
                        $docPv->setComposante($composante);
                        $em->persist($docPv);
                    }
                } catch (FileUploadException $exception) {
                    return $turboStream->streamToastError($exception->getPublicMessage());
                }
            }

            // Document Note
            $docNote = null;
            $noteId = $request->request->get('note_id');
            if ($noteId && is_numeric($noteId) && (int)$noteId > 0) {
                $docNote = $documentConseilRepository->findOneBy(['id' => (int)$noteId, 'composante' => $composante, 'type' => 'note_explicative']);
                if ($docNote === null) {
                    return $turboStream->streamToastError('La note explicative sélectionnée est introuvable pour cette composante.');
                }
            } elseif ($request->files->has('fileNote') && $request->files->get('fileNote') !== null) {
                try {
                    $uploadedNote = $secureUploadService->uploadFromRequest($request, 'fileNote', 'conseils');
                    if ($uploadedNote !== null) {
                        $docNote = new DocumentConseil();
                        $docNote->setType('note_explicative');
                        $docNote->setFilename($uploadedNote->getStoredFilename());
                        $docNote->setOriginalFilename($uploadedNote->getOriginalFilename());
                        $docNote->setDateConseil($dateConseil);
                        /** @var \App\Entity\User|null $currentUser */
                        $currentUser = $this->getUser() instanceof \App\Entity\User ? $this->getUser() : null;
                        $docNote->setUploadedBy($currentUser);
                        $docNote->setComposante($composante);
                        $em->persist($docNote);
                    }
                } catch (FileUploadException $exception) {
                    return $turboStream->streamToastError($exception->getPublicMessage());
                }
            }

            $motifs = [];
            if ($laisserPasser) {
                $motifs['laisserPasser'] = $laisserPasserJustif ?: '1';
            }
            if ($commentaire !== '') {
                $motifs['motif'] = $commentaire;
            }

            foreach ($formationsAValider as ['formation' => $forma, 'dpeFormation' => $dpeF]) {
                $em->persist($dpeF);

                if ($docPv !== null) {
                    $docPv->addFormation($forma);
                    $forma->addDocumentConseil($docPv);
                }
                if ($docNote !== null) {
                    $docNote->addFormation($forma);
                    $forma->addDocumentConseil($docNote);
                }

                if ($laisserPasser) {
                    $dpeF->setLaissezPasser($laisserPasserJustif ?: '1');
                }

                // HistoriqueFormation
                $histo = new HistoriqueFormation();
                $histo->setFormation($forma);
                $histo->setDpeFormation($dpeF);
                $histo->setDate($dateConseil);
                /** @var \App\Entity\User|null $currentUser */
                $currentUser = $this->getUser() instanceof \App\Entity\User ? $this->getUser() : null;
                $histo->setUser($currentUser);
                $histo->setEtape($transition);
                $histo->setEtat($isRefuse ? 'refuse' : ($laisserPasser ? 'laisserPasser' : 'valide'));
                $histo->setCommentaire($commentaire);

                if ($docPv !== null) {
                    $histo->setDocumentPv($docPv);
                }
                if ($docNote !== null) {
                    $histo->setDocumentNote($docNote);
                }

                $complements = [];
                if ($docPv !== null) {
                    $complements['fichier'] = $docPv->getFilename();
                    $complements['fichier_original'] = $docPv->getOriginalFilename();
                }
                if ($docNote !== null) {
                    $complements['fichier_note'] = $docNote->getFilename();
                    $complements['fichier_note_original'] = $docNote->getOriginalFilename();
                }
                if ($laisserPasser) {
                    $complements['laisserPasser'] = $laisserPasserJustif ?: '1';
                }
                $histo->setComplements($complements);
                $em->persist($histo);

                $dpeFormationWorkflow->apply($dpeF, $transition, $motifs);
            }

            $remplissageSuspension->suspendre();
            $em->flush();

            $nbValidees = count($formationsAValider);
            $message = sprintf(
                'Enregistré pour %d formation%s de %s',
                $nbValidees,
                $nbValidees > 1 ? 's' : '',
                $composante->getLibelle()
            );
            if ($nbIgnorees > 0) {
                $message .= sprintf(' (%d ignorée%s : étape déjà franchie)', $nbIgnorees, $nbIgnorees > 1 ? 's' : '');
            }

            return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
                'message' => $message,
            ]);
        }

        // 5. Affichage GET Modal
        $existingPvs = $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'pv',
        ], ['uploadedAt' => 'DESC']);

        $existingNotes = $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'note_explicative',
        ], ['uploadedAt' => 'DESC']);

        $modalTitle = isset($meta['label'])
            ? $translator->trans($meta['label'], [], 'process')
            : sprintf('Validation — %s', $transition);

        return $turboStream->streamOpenModalFromTemplates(
            $modalTitle,
            sprintf('Composante : %s', $composante->getLibelle()),
            'offre_v2/_modal_validation_composante.html.twig',
            [
                'composante' => $composante,
                'transition' => $transition,
                'meta' => $meta,
                'isRefuse' => $isRefuse,
                'formationsData' => $formationsData,
                'existingPvs' => $existingPvs,
                'existingNotes' => $existingNotes,
            ],
            '_ui/_footer_submit_cancel.html.twig'
        );
    }

    /**
     * Messages d'erreur de la fenêtre de validation, collectés avant tout upload / persist / apply.
     *
     * @param  array<string, mixed> $meta
     * @return list<string>
     */
    private function collectValidationErrors(Composante $composante, Request $request, array $meta, bool $isRefuse): array
    {
        // Jeton CSRF : la session est peut-être expirée, on s'arrête là.
        if (!$this->isCsrfTokenValid('offre_v2_valider_' . $composante->getId(), (string)$request->request->get('_token'))) {
            return ['Session expirée : rechargez la page puis recommencez.'];
        }

        $errors = [];

        if ($request->request->all('formations') === []) {
            $errors[] = 'Cochez au moins une formation.';
        }

        $dateSaisie = trim((string)$request->request->get('date'));
        if ($dateSaisie === '' || \DateTime::createFromFormat('Y-m-d', $dateSaisie) === false) {
            $errors[] = 'Indiquez la date de validation.';
        }

        $laisserPasser = $request->request->getBoolean('laisserPasser');
        // Un champ fichier laissé vide est soumis avec UPLOAD_ERR_NO_FILE : ce n'est pas un dépôt.
        $aFichierPv = $request->files->has('file') && $request->files->get('file')?->isValid();
        $aFichierNote = $request->files->has('fileNote') && $request->files->get('fileNote')?->isValid();

        if ($isRefuse) {
            if (trim((string)$request->request->get('commentaire')) === '') {
                $errors[] = 'Indiquez le motif du renvoi.';
            }

            return $errors;
        }

        if (($meta['hasUpload'] ?? false) === true
            && trim((string)$request->request->get('pv_id')) === ''
            && !$aFichierPv
            && !$laisserPasser
        ) {
            $errors[] = 'Déposez le procès-verbal (PDF), choisissez un PV déjà déposé, ou cochez le laissez-passer exceptionnel.';
        }

        if ($laisserPasser && trim((string)$request->request->get('laisserPasserJustif')) === '') {
            $errors[] = 'Indiquez la justification du laissez-passer.';
        }

        if (($meta['hasUploadNote'] ?? false) === true
            && trim((string)$request->request->get('note_id')) === ''
            && !$aFichierNote
        ) {
            $errors[] = 'Déposez la note explicative (PDF) ou choisissez une note déjà déposée.';
        }

        return $errors;
    }

    #[Route('/offre/formation/{formation}/documents-conseil', name: 'offre_v2_formation_documents_modal', methods: ['GET'])]
    public function modalFormationDocuments(
        Formation $formation,
        DocumentConseilRepository $documentConseilRepository,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessCanManageDocuments($formation);

        $composante = $formation->getComposantePorteuse();

        $currentPv = null;
        $currentNote = null;
        foreach ($formation->getDocumentConseils() as $doc) {
            if ($doc->getType() === 'pv' && $currentPv === null) {
                $currentPv = $doc;
            } elseif ($doc->getType() === 'note_explicative' && $currentNote === null) {
                $currentNote = $doc;
            }
        }

        $existingPvs = $composante !== null ? $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'pv',
        ], ['uploadedAt' => 'DESC']) : [];

        $existingNotes = $composante !== null ? $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'note_explicative',
        ], ['uploadedAt' => 'DESC']) : [];

        return $turboStream->streamOpenModalFromTemplates(
            'Documents de conseil — PV & Note explicative',
            $formation->getDisplayLong(),
            'offre_v2/_modal_formation_documents.html.twig',
            [
                'formation' => $formation,
                'currentPv' => $currentPv,
                'currentNote' => $currentNote,
                'existingPvs' => $existingPvs,
                'existingNotes' => $existingNotes,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer',
            ]
        );
    }

    #[Route('/offre/formation/{formation}/documents-conseil/sauvegarder', name: 'offre_v2_formation_documents_sauvegarder', methods: ['POST'])]
    public function sauvegarderFormationDocuments(
        Formation $formation,
        Request $request,
        DocumentConseilRepository $documentConseilRepository,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessCanManageDocuments($formation);

        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('formation_documents_' . $formation->getId(), $csrfToken)) {
            return $turboStream->streamToastError('Session expirée ou jeton CSRF invalide.');
        }

        $composante = $formation->getComposantePorteuse();
        $dateStr = (string)$request->request->get('date_conseil');
        $dateConseil = !empty($dateStr) ? new \DateTime($dateStr) : new \DateTime();
        $commentaire = trim((string)$request->request->get('commentaire'));
        /** @var User|null $currentUser */
        $currentUser = $this->getUser() instanceof User ? $this->getUser() : null;

        // Gestion du PV
        $pvAction = (string)$request->request->get('pv_action', 'keep');
        $docPv = null;

        if ($pvAction === 'remove') {
            foreach ($formation->getDocumentConseils() as $doc) {
                if ($doc->getType() === 'pv') {
                    $formation->removeDocumentConseil($doc);
                }
            }
        } elseif ($pvAction === 'choose') {
            $pvId = $request->request->get('pv_id');
            if ($pvId) {
                $docPv = $documentConseilRepository->findOneBy([
                    'id' => (int)$pvId,
                    'type' => 'pv',
                ]);
                if ($docPv !== null) {
                    foreach ($formation->getDocumentConseils() as $doc) {
                        if ($doc->getType() === 'pv' && $doc->getId() !== $docPv->getId()) {
                            $formation->removeDocumentConseil($doc);
                        }
                    }
                    $formation->addDocumentConseil($docPv);
                }
            }
        } elseif ($pvAction === 'upload') {
            if ($request->files->has('file_pv') && $request->files->get('file_pv') !== null) {
                try {
                    $uploadedPv = $secureUploadService->uploadFromRequest($request, 'file_pv', 'conseils');
                    if ($uploadedPv !== null) {
                        foreach ($formation->getDocumentConseils() as $doc) {
                            if ($doc->getType() === 'pv') {
                                $formation->removeDocumentConseil($doc);
                            }
                        }
                        $docPv = new DocumentConseil();
                        $docPv->setType('pv');
                        $docPv->setFilename($uploadedPv->getStoredFilename());
                        $docPv->setOriginalFilename($uploadedPv->getOriginalFilename());
                        $docPv->setDateConseil($dateConseil);
                        $docPv->setUploadedBy($currentUser);
                        $docPv->setComposante($composante);
                        $docPv->addFormation($formation);
                        $em->persist($docPv);
                        $formation->addDocumentConseil($docPv);
                    }
                } catch (FileUploadException $e) {
                    return $turboStream->streamToastError($e->getPublicMessage());
                }
            }
        } else {
            // 'keep' : on conserve le PV existant
            foreach ($formation->getDocumentConseils() as $doc) {
                if ($doc->getType() === 'pv') {
                    $docPv = $doc;
                    break;
                }
            }
        }

        // Gestion de la Note explicative
        $noteAction = (string)$request->request->get('note_action', 'keep');
        $docNote = null;

        if ($noteAction === 'remove') {
            foreach ($formation->getDocumentConseils() as $doc) {
                if ($doc->getType() === 'note_explicative') {
                    $formation->removeDocumentConseil($doc);
                }
            }
        } elseif ($noteAction === 'choose') {
            $noteId = $request->request->get('note_id');
            if ($noteId) {
                $docNote = $documentConseilRepository->findOneBy([
                    'id' => (int)$noteId,
                    'type' => 'note_explicative',
                ]);
                if ($docNote !== null) {
                    foreach ($formation->getDocumentConseils() as $doc) {
                        if ($doc->getType() === 'note_explicative' && $doc->getId() !== $docNote->getId()) {
                            $formation->removeDocumentConseil($doc);
                        }
                    }
                    $formation->addDocumentConseil($docNote);
                }
            }
        } elseif ($noteAction === 'upload') {
            if ($request->files->has('file_note') && $request->files->get('file_note') !== null) {
                try {
                    $uploadedNote = $secureUploadService->uploadFromRequest($request, 'file_note', 'conseils');
                    if ($uploadedNote !== null) {
                        foreach ($formation->getDocumentConseils() as $doc) {
                            if ($doc->getType() === 'note_explicative') {
                                $formation->removeDocumentConseil($doc);
                            }
                        }
                        $docNote = new DocumentConseil();
                        $docNote->setType('note_explicative');
                        $docNote->setFilename($uploadedNote->getStoredFilename());
                        $docNote->setOriginalFilename($uploadedNote->getOriginalFilename());
                        $docNote->setDateConseil($dateConseil);
                        $docNote->setUploadedBy($currentUser);
                        $docNote->setComposante($composante);
                        $docNote->addFormation($formation);
                        $em->persist($docNote);
                        $formation->addDocumentConseil($docNote);
                    }
                } catch (FileUploadException $e) {
                    return $turboStream->streamToastError($e->getPublicMessage());
                }
            }
        } else {
            // 'keep' : on conserve la Note existante
            foreach ($formation->getDocumentConseils() as $doc) {
                if ($doc->getType() === 'note_explicative') {
                    $docNote = $doc;
                    break;
                }
            }
        }

        // Trace dans l'historique
        $campagne = $this->getCampagneCollecte();
        $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
            'formation' => $formation,
            'campagneCollecte' => $campagne,
        ]);

        $histo = new HistoriqueFormation();
        $histo->setFormation($formation);
        $histo->setDpeFormation($dpeFormation);
        $histo->setDate($dateConseil);
        $histo->setUser($currentUser);
        $histo->setEtape('documents_conseil');
        $histo->setEtat('valide');
        $histo->setCommentaire($commentaire !== '' ? $commentaire : 'Mise à jour des documents de conseil (PV / Note explicative)');
        if ($docPv !== null) {
            $histo->setDocumentPv($docPv);
        }
        if ($docNote !== null) {
            $histo->setDocumentNote($docNote);
        }
        $em->persist($histo);

        $em->flush();

        return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
            'message' => 'Les documents de conseil de la formation ont été mis à jour.',
        ]);
    }

    #[Route('/offre/composante/{composante}/documents-conseil', name: 'offre_v2_composante_documents_modal', methods: ['GET'])]
    public function composanteDocumentsModal(
        Composante $composante,
        FormationRepository $formationRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        DpeFormationRepository $dpeFormationRepository,
        DocumentConseilRepository $documentConseilRepository,
        AnneeRepository $anneeRepository,
        PlateformeAdmissionParametreRepository $plateformeParamRepository,
        OffreValidationService $offreValidationService,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessCanManageComposante($composante);

        $campagne = $this->getCampagneCollecte();
        [$allFormations, $formationsData] = $this->buildFormationsDataForComposante(
            $composante,
            $campagne,
            $formationRepository,
            $dpeParcoursRepository,
            $dpeFormationRepository,
            $anneeRepository,
            $plateformeParamRepository,
            $offreValidationService,
        );

        $existingPvs = $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'pv',
        ], ['uploadedAt' => 'DESC']);

        $existingNotes = $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'note_explicative',
        ], ['uploadedAt' => 'DESC']);

        return $turboStream->streamOpenModalFromTemplates(
            'Documents de conseil — PV & Notes explicatives',
            sprintf('Composante : %s', $composante->getLibelle()),
            'offre_v2/_modal_validation_composante.html.twig',
            [
                'composante' => $composante,
                'isDocumentsOnly' => true,
                'actionUrl' => $this->generateUrl('offre_v2_composante_documents_sauvegarder', ['composante' => $composante->getId()]),
                'csrfTokenName' => 'offre_v2_documents_' . $composante->getId(),
                'formationsData' => $formationsData,
                'existingPvs' => $existingPvs,
                'existingNotes' => $existingNotes,
                'meta' => [
                    'hasUpload' => false,
                    'hasUploadNote' => false,
                ],
                'isRefuse' => false,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer les documents',
            ]
        );
    }

    #[Route('/offre/composante/{composante}/documents-conseil/sauvegarder', name: 'offre_v2_composante_documents_sauvegarder', methods: ['POST'])]
    public function composanteDocumentsSauvegarder(
        Composante $composante,
        Request $request,
        FormationRepository $formationRepository,
        DocumentConseilRepository $documentConseilRepository,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessCanManageComposante($composante);

        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('offre_v2_documents_' . $composante->getId(), $csrfToken)) {
            return $turboStream->streamToastError('Session expirée ou jeton CSRF invalide.');
        }

        $selectedFormationIds = array_map('intval', (array)$request->request->all('formations'));
        if ($selectedFormationIds === []) {
            return $turboStream->streamToastError('Cochez au moins une formation.');
        }

        $campagne = $this->getCampagneCollecte();
        $formations = $formationRepository->findBy([
            'id' => $selectedFormationIds,
            'composantePorteuse' => $composante,
            'dpe' => $campagne,
        ]);

        if ($formations === []) {
            return $turboStream->streamToastError('Aucune formation valide sélectionnée pour cette composante.');
        }

        $dateStr = (string)$request->request->get('date');
        $dateConseil = !empty($dateStr) ? new \DateTime($dateStr) : new \DateTime();
        $commentaire = trim((string)$request->request->get('commentaire'));
        /** @var User|null $currentUser */
        $currentUser = $this->getUser() instanceof User ? $this->getUser() : null;

        // 1. PV
        $pvId = (string)$request->request->get('pv_id', '');
        $docPv = null;
        $removePv = ($pvId === '__remove__');
        $hasNewPvUpload = $request->files->has('file') && $request->files->get('file')?->isValid();

        if ($pvId === '__upload__' && !$hasNewPvUpload) {
            return $turboStream->streamToastError('Veuillez sélectionner un fichier PDF pour le nouveau PV.');
        }

        if ($hasNewPvUpload) {
            try {
                $uploadedPv = $secureUploadService->uploadFromRequest($request, 'file', 'conseils');
                if ($uploadedPv !== null) {
                    $docPv = new DocumentConseil();
                    $docPv->setType('pv');
                    $docPv->setFilename($uploadedPv->getStoredFilename());
                    $docPv->setOriginalFilename($uploadedPv->getOriginalFilename());
                    $docPv->setDateConseil($dateConseil);
                    $docPv->setUploadedBy($currentUser);
                    $docPv->setComposante($composante);
                    $em->persist($docPv);
                }
            } catch (FileUploadException $e) {
                return $turboStream->streamToastError($e->getPublicMessage());
            }
        } elseif (is_numeric($pvId) && (int)$pvId > 0) {
            $docPv = $documentConseilRepository->findOneBy([
                'id' => (int)$pvId,
                'composante' => $composante,
                'type' => 'pv',
            ]);
            if ($docPv === null) {
                return $turboStream->streamToastError('Le PV sélectionné est introuvable pour cette composante.');
            }
        }

        // 2. Note explicative
        $noteId = (string)$request->request->get('note_id', '');
        $docNote = null;
        $removeNote = ($noteId === '__remove__');
        $hasNewNoteUpload = $request->files->has('fileNote') && $request->files->get('fileNote')?->isValid();

        if ($noteId === '__upload__' && !$hasNewNoteUpload) {
            return $turboStream->streamToastError('Veuillez sélectionner un fichier PDF pour la nouvelle note explicative.');
        }

        if ($hasNewNoteUpload) {
            try {
                $uploadedNote = $secureUploadService->uploadFromRequest($request, 'fileNote', 'conseils');
                if ($uploadedNote !== null) {
                    $docNote = new DocumentConseil();
                    $docNote->setType('note_explicative');
                    $docNote->setFilename($uploadedNote->getStoredFilename());
                    $docNote->setOriginalFilename($uploadedNote->getOriginalFilename());
                    $docNote->setDateConseil($dateConseil);
                    $docNote->setUploadedBy($currentUser);
                    $docNote->setComposante($composante);
                    $em->persist($docNote);
                }
            } catch (FileUploadException $e) {
                return $turboStream->streamToastError($e->getPublicMessage());
            }
        } elseif (is_numeric($noteId) && (int)$noteId > 0) {
            $docNote = $documentConseilRepository->findOneBy([
                'id' => (int)$noteId,
                'composante' => $composante,
                'type' => 'note_explicative',
            ]);
            if ($docNote === null) {
                return $turboStream->streamToastError('La note explicative sélectionnée est introuvable pour cette composante.');
            }
        }

        // 3. Application aux formations sélectionnées
        $dpeFormationRepo = $em->getRepository(DpeFormation::class);
        $dpeFormations = $dpeFormationRepo->findBy([
            'formation' => $formations,
            'campagneCollecte' => $campagne,
        ]);
        $dpeFormationByFormationId = [];
        foreach ($dpeFormations as $df) {
            if ($df->getFormation() !== null) {
                $dpeFormationByFormationId[$df->getFormation()->getId()] = $df;
            }
        }

        foreach ($formations as $forma) {
            if ($removePv) {
                foreach ($forma->getDocumentConseils() as $doc) {
                    if ($doc->getType() === 'pv') {
                        $forma->removeDocumentConseil($doc);
                    }
                }
            } elseif ($docPv !== null) {
                foreach ($forma->getDocumentConseils() as $doc) {
                    if ($doc->getType() === 'pv' && $doc->getId() !== $docPv->getId()) {
                        $forma->removeDocumentConseil($doc);
                    }
                }
                $docPv->addFormation($forma);
                $forma->addDocumentConseil($docPv);
            }

            if ($removeNote) {
                foreach ($forma->getDocumentConseils() as $doc) {
                    if ($doc->getType() === 'note_explicative') {
                        $forma->removeDocumentConseil($doc);
                    }
                }
            } elseif ($docNote !== null) {
                foreach ($forma->getDocumentConseils() as $doc) {
                    if ($doc->getType() === 'note_explicative' && $doc->getId() !== $docNote->getId()) {
                        $forma->removeDocumentConseil($doc);
                    }
                }
                $docNote->addFormation($forma);
                $forma->addDocumentConseil($docNote);
            }

            $histo = new HistoriqueFormation();
            $histo->setFormation($forma);
            $histo->setDpeFormation($dpeFormationByFormationId[$forma->getId()] ?? null);
            $histo->setDate($dateConseil);
            $histo->setUser($currentUser);
            $histo->setEtape('documents_conseil');
            $histo->setEtat('valide');
            $histo->setCommentaire($commentaire !== '' ? $commentaire : 'Mise à jour groupée des documents de conseil');
            if ($docPv !== null) {
                $histo->setDocumentPv($docPv);
            }
            if ($docNote !== null) {
                $histo->setDocumentNote($docNote);
            }
            $em->persist($histo);
        }

        $em->flush();

        return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
            'message' => sprintf('Documents de conseil mis à jour pour %d formation(s) de %s.', count($formations), $composante->getLibelle()),
        ]);
    }

    /**
     * @return array{0: array<int, Formation>, 1: array<int, array<string, mixed>>}
     */
    private function buildFormationsDataForComposante(
        Composante $composante,
        CampagneCollecte $campagne,
        FormationRepository $formationRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        DpeFormationRepository $dpeFormationRepository,
        AnneeRepository $anneeRepository,
        PlateformeAdmissionParametreRepository $plateformeParamRepository,
        OffreValidationService $offreValidationService,
    ): array {
        $allFormations = $formationRepository->findBy([
            'dpe' => $campagne,
            'composantePorteuse' => $composante,
        ]);

        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);
        $paramsByAnnee = $plateformeParamRepository->findByCampagneIndexedByAnnee($campagne, $composante);
        $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne, $composante);

        $dpeFormations = !empty($allFormations) ? $dpeFormationRepository->findBy([
            'campagneCollecte' => $campagne,
            'formation' => $allFormations,
        ]) : [];

        $dpeFormationByFormationId = [];
        foreach ($dpeFormations as $df) {
            if ($df->getFormation() !== null) {
                $dpeFormationByFormationId[$df->getFormation()->getId()] = $df;
            }
        }

        $parcoursByFormationId = [];
        foreach ($allParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            if ($parcours === null) {
                continue;
            }
            $formation = $parcours->getFormation();
            if ($formation !== null) {
                $parcoursByFormationId[$formation->getId()][] = $dpePar;
            }
        }

        $formationsData = [];
        foreach ($allFormations as $forma) {
            $fId = $forma->getId();
            $dpeParList = $parcoursByFormationId[$fId] ?? [];
            $formaHasAnomalies = false;
            $formaAnomaliesCount = 0;
            $parcoursList = [];

            foreach ($dpeParList as $dpePar) {
                $parcours = $dpePar->getParcours();
                if ($parcours === null) {
                    continue;
                }
                $parcAnnees = $anneesByParcours[$parcours->getId()] ?? [];
                $parcAnoms = $offreValidationService->getAnomaliesParcours($parcours, $campagne, $dpePar, $paramsByAnnee, $parcAnnees);
                $isParcConforme = (count($parcAnoms) === 0);
                if (!$isParcConforme) {
                    $formaHasAnomalies = true;
                    $formaAnomaliesCount += count($parcAnoms);
                }

                $capParcours = 0;
                foreach ($parcAnnees as $an) {
                    $capParcours += $an->getCapaciteAccueil();
                }

                $parcoursList[] = [
                    'parcours' => $parcours,
                    'dpeParcours' => $dpePar,
                    'isOuvert' => $dpePar->isOuvert(),
                    'capacite' => $capParcours,
                    'isConforme' => $isParcConforme,
                    'anomalies' => $parcAnoms,
                ];
            }

            $pvDoc = null;
            $noteDoc = null;
            foreach ($forma->getDocumentConseils() as $doc) {
                if ($doc->getType() === 'pv' && $pvDoc === null) {
                    $pvDoc = $doc;
                } elseif ($doc->getType() === 'note_explicative' && $noteDoc === null) {
                    $noteDoc = $doc;
                }
            }

            $formationsData[$fId] = [
                'formation' => $forma,
                'dpeFormation' => $dpeFormationByFormationId[$fId] ?? null,
                'hasAnomalies' => $formaHasAnomalies,
                'anomaliesCount' => $formaAnomaliesCount,
                'parcoursList' => $parcoursList,
                'hasPv' => $pvDoc !== null,
                'hasNote' => $noteDoc !== null,
                'pvDoc' => $pvDoc,
                'noteDoc' => $noteDoc,
            ];
        }

        return [$allFormations, $formationsData];
    }

    private function denyAccessUnlessCanManageDocuments(Formation $formation): void
    {
        $composante = $formation->getComposantePorteuse();
        if ($composante === null) {
            throw $this->createAccessDeniedException();
        }

        $this->denyAccessUnlessCanManageComposante($composante);
    }

    private function denyAccessUnlessCanManageComposante(Composante $composante): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SES')) {
            return;
        }

        $this->denyAccessUnlessGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $composante,
        ]);
    }
}
