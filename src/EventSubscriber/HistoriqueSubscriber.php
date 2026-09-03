<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/WorkflowDpeMailSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\Formation;
use App\Entity\HistoriqueFicheMatiere;
use App\Entity\HistoriqueFormation;
use App\Entity\HistoriqueParcours;
use App\Events\HistoriqueChangeRfEvent;
use App\Events\HistoriqueFicheMatiereEvent;
use App\Events\HistoriqueFormationEditEvent;
use App\Events\HistoriqueFormationEvent;
use App\Events\HistoriqueParcoursEditEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use App\Classes\DataUserSession;
use App\Entity\DocumentConseil;
use App\Entity\DpeFormation;
use App\Exception\FileUploadException;
use App\Service\SecureUploadService;
use App\Utils\Tools;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class HistoriqueSubscriber implements EventSubscriberInterface
{
    protected array $cases = [
        'laisserPasser',
        'acceptationDirecte',
        'sousReserveConseil',
        'sousReserveModifications',
        'projetARevoir'
    ];

    public function __construct(
        protected UserRepository         $userRepository,
        protected ComposanteRepository   $composanteRepository,
        protected FormationRepository    $formationRepository,
        protected EntityManagerInterface     $entityManager,
        private readonly SecureUploadService $secureUploadService,
        private readonly DataUserSession $dataUserSession,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION => 'createHistoriqueFormation',
            HistoriqueChangeRfEvent::ADD_HISTORIQUE_CHANGE_RF => 'createHistoriqueFormationChangeRf',
            HistoriqueFormationEditEvent::EDIT_HISTORIQUE_FORMATION => 'editHistoriqueFormation',
            HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS => 'createHistoriqueParcours',
            HistoriqueParcoursEditEvent::EDIT_HISTORIQUE_PARCOURS => 'editHistoriqueParcours',
            HistoriqueFicheMatiereEvent::ADD_HISTORIQUE_FICHE_MATIERE => 'createHistoriqueFicheMatiere',
        ];
    }

    public function editHistoriqueFormation(HistoriqueFormationEditEvent $event): void
    {
        $request = $event->getRequest();

        if ($request === null) {
            throw new Exception('Pas de requete');
        }

        $histo = $event->getHistoriqueFormation();
        $histo->setDate($this->getDateTime($request));
        $histo->setCommentaire($this->getCommentaire($request));

        foreach ($this->cases as $cas) {
            if ($request->request->has($cas)) {
                $tab[$cas] = $request->request->get($cas);
                if ($cas === 'laisserPasser') {
                    $histo->setEtat('laisserPasser');
                }
            }

            if ($request->request->has('argumentaire_'.$cas)) {
                $tab['argumentaire_'.$cas] = $request->request->get('argumentaire_'.$cas);
            }
        }

        try {
            $upload = $this->secureUploadService->uploadFromRequest($request, 'file', 'conseils');
        } catch (FileUploadException) {
            throw new Exception('Fichier de validation invalide.');
        }

        if ($upload !== null) {
            $tab['fichier'] = $upload->getStoredFilename();
            $tab['fichier_original'] = $upload->getOriginalFilename();
        }

        $histo->setComplements($tab ?? []);

        $this->entityManager->persist($histo);
        $this->entityManager->flush();
    }

    public function editHistoriqueParcours(HistoriqueParcoursEditEvent $event): void
    {
        $request = $event->getRequest();

        if ($request === null) {
            throw new Exception('Pas de requete');
        }

        $histo = $event->getHistoriqueParcours();
        $histo->setDate($this->getDateTime($request));
        $histo->setCommentaire($this->getCommentaire($request));

        foreach ($this->cases as $cas) {
            if ($request->request->has($cas)) {
                $tab[$cas] = $request->request->get($cas);
                if ($cas === 'laisserPasser') {
                    $histo->setEtat('laisserPasser');
                }
            }

            if ($request->request->has('argumentaire_'.$cas)) {
                $tab['argumentaire_'.$cas] = $request->request->get('argumentaire_'.$cas);
            }
        }

        try {
            $upload = $this->secureUploadService->uploadFromRequest($request, 'file', 'conseils');
        } catch (FileUploadException) {
            throw new Exception('Fichier de validation invalide.');
        }

        if ($upload !== null) {
            $tab['fichier'] = $upload->getStoredFilename();
            $tab['fichier_original'] = $upload->getOriginalFilename();
        }

        $histo->setComplements($tab ?? []);

        $this->entityManager->persist($histo);
        $this->entityManager->flush();
    }

    public function createHistoriqueFormation(HistoriqueFormationEvent $event): void
    {
        $request = $event->getRequest();

        if ($request === null) {
            throw new Exception('Pas de requete');
        }

        $formation = $event->getFormation();
        $histo = new HistoriqueFormation();
        $histo->setFormation($formation);
        $histo->setDate($this->getDateTime($request));
        $histo->setUser($this->resolveUser($event->getUser()));
        $histo->setEtape($event->getEtape());
        $histo->setCommentaire($this->getCommentaire($request));
        $histo->setEtat($event->getEtat());

        // Lier DpeFormation
        $campagne = $this->dataUserSession->getCampagneCollecte();
        $dpeFormation = null;
        if ($campagne !== null && $formation !== null) {
            $dpeFormation = $this->entityManager->getRepository(DpeFormation::class)->findOneBy([
                'formation' => $formation,
                'campagneCollecte' => $campagne,
            ]);
            if ($dpeFormation === null) {
                $dpeFormation = new DpeFormation();
                $dpeFormation->setFormation($formation);
                $dpeFormation->setCampagneCollecte($campagne);
                $dpeFormation->setEtatValidation(['brouillon' => 1]);
                $this->entityManager->persist($dpeFormation);
            }
            $histo->setDpeFormation($dpeFormation);
        }

        foreach ($this->cases as $cas) {
            if ($request->request->has($cas)) {
                $tab[$cas] = $request->request->get($cas);
                if ($cas === 'laisserPasser') {
                    $histo->setEtat('laisserPasser');
                    if ($dpeFormation !== null) {
                        $dpeFormation->setLaissezPasser($request->request->get('laisserPasser'));
                    }
                }
            }

            if ($request->request->has('argumentaire_'.$cas)) {
                $tab['argumentaire_'.$cas] = $request->request->get('argumentaire_'.$cas);
            }
        }

        // Dépôt de fichier (PV) ou liaison PV existant
        $pvId = $request->request->get('pv_id');
        if ($pvId) {
            $existingPv = $this->entityManager->getRepository(DocumentConseil::class)->find($pvId);
            if ($existingPv !== null) {
                $existingPv->addFormation($formation);
                $histo->setDocumentPv($existingPv);
            }
        } else {
            try {
                $upload = $this->secureUploadService->uploadFromRequest($request, 'file', 'conseils');
            } catch (FileUploadException) {
                throw new Exception('Fichier de validation invalide.');
            }

            if ($upload !== null) {
                $tab['fichier'] = $upload->getStoredFilename();
                $tab['fichier_original'] = $upload->getOriginalFilename();

                $docPv = new DocumentConseil();
                $docPv->setType('pv');
                $docPv->setFilename($upload->getStoredFilename());
                $docPv->setOriginalFilename($upload->getOriginalFilename());
                $docPv->setDateConseil($histo->getDate());
                $docPv->setUploadedBy($histo->getUser());
                $docPv->setComposante($formation->getComposantePorteuse());
                $docPv->addFormation($formation);

                // Gérer le partage avec d'autres formations
                $otherFormations = (array)$request->request->all('formations_pv');
                foreach ($otherFormations as $fId) {
                    $f = $this->entityManager->getRepository(Formation::class)->find($fId);
                    if ($f !== null) {
                        $docPv->addFormation($f);
                    }
                }

                $this->entityManager->persist($docPv);
                $histo->setDocumentPv($docPv);
            }
        }

        // Dépôt de note explicative ou liaison note existante
        $noteId = $request->request->get('note_id');
        if ($noteId) {
            $existingNote = $this->entityManager->getRepository(DocumentConseil::class)->find($noteId);
            if ($existingNote !== null) {
                $existingNote->addFormation($formation);
                $histo->setDocumentNote($existingNote);
            }
        } else {
            try {
                $uploadNote = $this->secureUploadService->uploadFromRequest($request, 'fileNote', 'conseils');
            } catch (FileUploadException) {
                throw new Exception('Note explicative invalide.');
            }

            if ($uploadNote !== null) {
                $tab['fichier_note'] = $uploadNote->getStoredFilename();
                $tab['fichier_note_original'] = $uploadNote->getOriginalFilename();

                $docNote = new DocumentConseil();
                $docNote->setType('note_explicative');
                $docNote->setFilename($uploadNote->getStoredFilename());
                $docNote->setOriginalFilename($uploadNote->getOriginalFilename());
                $docNote->setDateConseil($histo->getDate());
                $docNote->setUploadedBy($histo->getUser());
                $docNote->setComposante($formation->getComposantePorteuse());
                $docNote->addFormation($formation);

                // Gérer le partage avec d'autres formations
                $otherFormationsNote = (array)$request->request->all('formations_note');
                foreach ($otherFormationsNote as $fId) {
                    $f = $this->entityManager->getRepository(Formation::class)->find($fId);
                    if ($f !== null) {
                        $docNote->addFormation($f);
                    }
                }

                $this->entityManager->persist($docNote);
                $histo->setDocumentNote($docNote);
            }
        }

        $histo->setComplements($tab ?? []);

        $this->entityManager->persist($histo);
        $this->entityManager->flush();
    }
    public function createHistoriqueFormationChangeRf(HistoriqueChangeRfEvent $event): void
    {
        $request = $event->getRequest();
        $fileName = $event->getFileName();
        $originalFileName = $event->getOriginalFileName();
        $demande = $event->getChangeRf();
        $formation = $demande->getFormation();

        if ($request === null) {
            throw new Exception('Pas de requete');
        }

        $histo = new HistoriqueFormation();
        $histo->setFormation($formation);
        $histo->setChangeRf($demande);
        $histo->setDate($this->getDateTime($request));
        $histo->setUser($this->resolveUser($event->getUser()));
        $histo->setEtape('changeRf.'.$event->getEtape());
        $histo->setCommentaire($this->getCommentaire($request));
        $histo->setEtat($event->getEtat());

        foreach ($this->cases as $cas) {
            if ($request->request->has($cas)) {
                $tab[$cas] = $request->request->get($cas);
                if ($cas === 'laisserPasser') {
                    $histo->setEtat('laisserPasser');
                }
            }

            if ($request->request->has('argumentaire_'.$cas)) {
                $tab['argumentaire_'.$cas] = $request->request->get('argumentaire_'.$cas);
            }
        }

        //upload
        if ($fileName !== null && $fileName !== '') {
            $tab['fichier'] = $fileName;
            if ($originalFileName !== null && $originalFileName !== '') {
                $tab['fichier_original'] = $originalFileName;
            }
        }

        $histo->setComplements($tab ?? []);

        $this->entityManager->persist($histo);
        $this->entityManager->flush();
    }

    public function createHistoriqueParcours(HistoriqueParcoursEvent $event): void
    {
        $request = $event->getRequest();
        $fileName = $event->getFileName();
        $fileNameNote = $event->getFileNameNote();
        $originalFileName = $event->getOriginalFileName();
        $originalFileNameNote = $event->getOriginalFileNameNote();

        if ($request === null) {
            throw new Exception('Pas de requete');
        }

        $histo = new HistoriqueParcours();
        $histo->setParcours($event->getParcours());
        $histo->setDate($this->getDateTime($request));
        $histo->setUser($this->resolveUser($event->getUser()));
        $histo->setEtape($event->getEtape());
        $histo->setCommentaire($this->getCommentaire($request));
        $histo->setEtat($event->getEtat());

        foreach ($this->cases as $cas) {
            if ($request->request->has($cas)) {
                $tab[$cas] = $request->request->get($cas);
                if ($cas === 'laisserPasser') {
                    $histo->setEtat('laisserPasser');
                }
            }

            if ($request->request->has('argumentaire_'.$cas)) {
                $tab['argumentaire_'.$cas] = $request->request->get('argumentaire_'.$cas);
            }
        }

        if ($fileName !== null && $fileName !== '') {
            $tab['fichier'] = $fileName;
            if ($originalFileName !== null && $originalFileName !== '') {
                $tab['fichier_original'] = $originalFileName;
            }
        }

        if ($fileNameNote !== null && $fileNameNote !== '') {
            $tab['fichier_note'] = $fileNameNote;
            if ($originalFileNameNote !== null && $originalFileNameNote !== '') {
                $tab['fichier_note_original'] = $originalFileNameNote;
            }
        }

        $histo->setComplements($tab ?? []);

        $this->entityManager->persist($histo);
        $this->entityManager->flush();
    }

    public function createHistoriqueFicheMatiere(HistoriqueFicheMatiereEvent $event): void
    {
        $request = $event->getRequest();

        if ($request === null) {
            throw new Exception('Pas de requete');
        }

        $histo = new HistoriqueFicheMatiere();
        $histo->setFicheMatiere($event->getFicheMatiere());
        $histo->setDate($this->getDateTime($request));
        $histo->setUser($this->resolveUser($event->getUser()));
        $histo->setEtape($event->getEtape());
        $histo->setCommentaire($this->getCommentaire($request));
        $histo->setEtat($event->getEtat());
        $this->entityManager->persist($histo);
        $this->entityManager->flush();
    }

    private function getDateTime(Request $request): ?DateTimeInterface
    {
        if ($request->request->has('date') && $request->request->get('date') !== null) {
            $date = Tools::convertDate($request->request->get('date'));
        } else {
            $date = null;
        }
        return $date;
    }

    private function getCommentaire(Request $request): string
    {
        if ($request->request->has('argumentaire')) {
            $commentaire = $request->request->get('argumentaire');
        } elseif ($request->request->has('argumentaire_demande_reouverture')) {
            $commentaire = $request->request->get('argumentaire_demande_reouverture');
        } else {
            $commentaire = '';
        }
        return $commentaire;
    }

    private function resolveUser(mixed $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
