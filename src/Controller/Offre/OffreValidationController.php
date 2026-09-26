<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\DocumentConseil;
use App\Entity\DpeFormation;
use App\Entity\HistoriqueFormation;
use App\Enums\TypeModificationDpeEnum;
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
        $meta = [];
        foreach ($dpeFormationWorkflow->getDefinition()->getTransitions() as $t) {
            if ($t->getName() === $transition) {
                $meta = $dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($t);
                break;
            }
        }
        $isRefuse = ($meta['type'] ?? '') === 'reserver' || str_starts_with($transition, 'reserver');

        // 3. Récupérer toutes les formations de la composante et leurs parcours
        $allFormations = $formationRepository->findBy([
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
                    'isOuvert' => ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT),
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
            $selectedFormationIds = array_map('intval', (array)$request->request->all('formations'));
            $selectedParcoursIds = array_map('intval', (array)$request->request->all('parcours'));

            $dateStr = (string)$request->request->get('date');
            $dateConseil = !empty($dateStr) ? new \DateTime($dateStr) : new \DateTime();

            $commentaire = (string)$request->request->get('commentaire');
            $laisserPasser = (bool)$request->request->get('laisserPasser');
            $laisserPasserJustif = (string)$request->request->get('laisserPasserJustif');

            // Documents PV
            $docPv = null;
            $pvId = $request->request->get('pv_id');
            if ($pvId) {
                $docPv = $documentConseilRepository->find($pvId);
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
                $docNote = $documentConseilRepository->find($noteId);
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

            foreach ($allFormations as $forma) {
                $fId = $forma->getId();
                if (!empty($selectedFormationIds) && !in_array($fId, $selectedFormationIds, true)) {
                    continue;
                }

                $dpeF = $dpeFormationByFormationId[$fId] ?? null;
                if ($dpeF === null) {
                    $dpeF = new DpeFormation();
                    $dpeF->setFormation($forma);
                    $dpeF->setCampagneCollecte($campagne);
                    $dpeF->setEtatValidation(['brouillon' => 1]);
                    $em->persist($dpeF);
                    $dpeFormationByFormationId[$fId] = $dpeF;
                }

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

                if ($dpeFormationWorkflow->can($dpeF, $transition)) {
                    $dpeFormationWorkflow->apply($dpeF, $transition, $motifs);
                }
            }

            $em->flush();

            return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
                'message' => sprintf('Validation de l\'offre enregistrée pour %s', $composante->getLibelle()),
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
}
