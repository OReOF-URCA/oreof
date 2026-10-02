<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\DocumentConseil;
use App\Entity\DpeFormation;
use App\Entity\HistoriqueFormation;
use App\Exception\FileUploadException;
use App\Repository\AnneeRepository;
use App\Repository\DocumentConseilRepository;
use App\Repository\DpeFormationRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Service\SecureUploadService;
use App\Service\Validation\OffreValidationService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

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
        TurboStreamResponseFactory $turboStream
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
        $allFormations = $formationRepository->findBy([
            'dpe' => $campagne,
            'composantePorteuse' => $composante,
        ]);

        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);
        $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne);
        $paramsByAnnee = $plateformeParamRepository->findByCampagneIndexedByAnnee($campagne);

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

            $formationsData[$fId] = [
                'formation' => $forma,
                'dpeFormation' => $dpeFormationByFormationId[$fId] ?? null,
                'hasAnomalies' => $formaHasAnomalies,
                'anomaliesCount' => $formaAnomaliesCount,
                'parcoursList' => $parcoursList,
            ];
        }

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
            if ($pvId) {
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
            if ($noteId) {
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

        $modalTitle = $meta['label'] ?? sprintf('Validation — %s', $transition);

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
}
