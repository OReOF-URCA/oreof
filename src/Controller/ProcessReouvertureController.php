<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Classes\ValidationProcess;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Events\DpeDemandeEvent;
use App\Events\HistoriqueFormationEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\DpeDemandeRepository;
use App\Repository\DpeParcoursRepository;
use App\Service\VersioningFormation;
use App\Service\VersioningParcours;
use App\Utils\TurboStreamResponseFactory;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProcessReouvertureController extends BaseController
{
    public function __construct(
        private readonly EventDispatcherInterface   $eventDispatcher,
        private readonly EntityManagerInterface     $entityManager,
        private readonly TurboStreamResponseFactory $turboStream,
    ) {
    }

    #[Route('/demande/reouverture/{parcours}', name: 'app_validation_demande_reouverture')]
    public function demandeReouverture(
        Parcours           $parcours,
        VersioningParcours $versioningParcours,
        Request            $request
    ): Response {
        $formation = $parcours->getFormation();
        $typeDpe = 'P';

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $dpe = GetDpeParcours::getFromParcours($parcours);
            if ($dpe === null) {
                return JsonReponse::error('DPE non trouvé');
            }

            $currentUser = $this->getCurrentUserOrFail();
            $currentPlace = array_key_first($dpe->getEtatValidation()) ?? 'publie';
            $initialPlace = $currentPlace === 'tacite_reconduction' ? 'tacite_reconduction' : 'publie';

            // réouverture directe sans sauvegarde ou avec sauvegarde selon le choix
            if ($data['demandeReouverture'] === 'MODIFICATION_TEXTE') {
                $etatTypeModification = TypeModificationDpeEnum::MODIFICATION_TEXTE;
                $dpe->setEtatValidation(['en_cours_redaction_ss_cfvu' => 1]);
                $dpe->setEtatReconduction($etatTypeModification);
                $now = new DateTimeImmutable('now');
                $versioningParcours->saveVersionOfParcours($parcours, $now, true);
                $histoEvent = new HistoriqueParcoursEvent($parcours, $currentUser, 'en_cours_redaction_ss_cfvu', 'valide', $request, input: ['etat_depart' => $initialPlace]);
                $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                $this->entityManager->flush();
            } elseif ($data['demandeReouverture'] === 'MODIFICATION_MCCC_TEXTE') {
                $etatTypeModification = TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE;
                $dpe->setEtatValidation(['en_cours_redaction' => 1]);
                $dpe->setEtatReconduction($etatTypeModification);
                $now = new DateTimeImmutable('now');
                $versioningParcours->saveVersionOfParcours($parcours, $now, true);
                $histoEvent = new HistoriqueParcoursEvent($parcours, $currentUser, 'en_cours_redaction', 'valide', $request, input: ['etat_depart' => $initialPlace]);
                $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                $this->entityManager->flush();
            } else {
                return JsonReponse::error('Type de réouverture inconnu');
            }

            $demande = new DpeDemande();
            $demande->setFormation($formation);
            $demande->setParcours($parcours);
            $demande->setCampagneCollecte($this->getCampagneCollecte());
            $demande->setAuteur($currentUser);
            $demande->setNiveauDemande($typeDpe);
            $demande->setArgumentaireDemande($request->request->getString('argumentaire_demande_reouverture'));
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $demande->setNiveauModification($etatTypeModification);
            $this->entityManager->persist($demande);
            $this->entityManager->flush();

            // mail au SES
            $dpeDemandeEvent = new DpeDemandeEvent($demande, $currentUser);
            $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);

            return JsonReponse::success('DPE ouvert');
        }

        if ($this->isTurbo($request)) {
            return $this->turboStream->streamOpenModalFromTemplates(
                'Demande de réouverture du parcours',
                $parcours->getDisplay(),
                'process_validation/_demande_reouverture.html.twig',
                ['parcours' => $parcours],
                '_ui/_footer_cancel.html.twig',
            );
        }

        return $this->render('process_validation/_demande_reouverture.html.twig', [
            'parcours' => $parcours
        ]);
    }

    #[Route('/demande/switch/{parcours}', name: 'app_validation_demande_switch')]
    public function demandeSwitch(
        DpeDemandeRepository $dpeDemandeRepository,
        Parcours             $parcours,
        Request              $request
    ): Response
    {
        $demande = $dpeDemandeRepository->findLastOpenedDemande($parcours, EtatDpeEnum::en_cours_redaction, TypeModificationDpeEnum::MODIFICATION_TEXTE);
        $currentUser = $this->getCurrentUserOrFail();

        if ($request->isMethod('POST')) {
            if ($demande === null) {
                // on créé une demande pour switcher vers modification MCCC + texte
                $demande = new DpeDemande();
                $demande->setFormation($parcours->getFormation());
                $demande->setParcours($parcours);
                $demande->setCampagneCollecte($this->getCampagneCollecte());
                $demande->setAuteur($currentUser);
                $demande->setNiveauDemande('P');
                $demande->setniveauModification(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
                $this->entityManager->persist($demande);
            }

            $dpe = GetDpeParcours::getFromParcours($parcours);
            if ($dpe === null) {
                return JsonReponse::error('DPE non trouvé');
            }

            $dpe->setEtatValidation(['en_cours_redaction' => 1]);
            $dpe->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
            $demande->setArgumentaireDemande($request->request->getString('argumentaire_demande_reouverture'));
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $this->entityManager->flush();

            $histoEvent = new HistoriqueParcoursEvent($parcours, $currentUser, 'en_cours_redaction', 'valide', $request);
            $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);

            // mail au SES
            $dpeDemandeEvent = new DpeDemandeEvent($demande, $currentUser);
            $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);

            return JsonReponse::success('DPE ouvert avec modification de la structure');
        }

        return $this->render('process_validation/_demande_switch.html.twig', [
            'parcours' => $parcours,
            'demande' => $demande ?? null,
        ]);
    }

    #[Route('/demande/reouverture-mention/{formation}', name: 'app_validation_demande_reouverture_mention')]
    public function demandeReouvertureMention(
        VersioningFormation $versioningFormation,
        Formation          $formation,
        Request            $request
    ): Response {
        $typeDpe = 'F';

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            if ($data['demandeReouverture'] === 'MODIFICATION_TEXTE') {
                $etat = TypeModificationDpeEnum::MODIFICATION_TEXTE;
                $texte = 'DPE ouvert pour modification des textes';
            } elseif ($data['demandeReouverture'] === 'MODIFICATION_PARCOURS') {
                $etat = TypeModificationDpeEnum::MODIFICATION_PARCOURS;
                $texte = 'DPE ouvert pour modification de la structure de la mention';
            } else {
                return JsonReponse::error('Type de réouverture inconnu');
            }

            $currentUser = $this->getCurrentUserOrFail();
            $dpe = GetDpeParcours::getFromFormation($formation);
            $initialPlace = ($dpe !== null && array_key_first($dpe->getEtatValidation()) === 'tacite_reconduction')
                ? 'tacite_reconduction'
                : 'publie';

            // Sauvegarde de version et traçabilité historique
            $versioningFormation->saveVersionOfFormation($formation, new DateTimeImmutable('now'), true);
            $histoEvent = new HistoriqueFormationEvent($formation, $currentUser, 'en_cours_redaction', 'valide', $request, input: ['etat_depart' => $initialPlace]);
            $this->eventDispatcher->dispatch($histoEvent, HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);

            $formation->setEtatReconduction($etat);
            if ($dpe !== null) {
                $dpe->setEtatValidation(['en_cours_redaction' => 1]);
                $dpe->setEtatReconduction($etat);
            }

            // Historisation de la demande
            $demande = new DpeDemande();
            $demande->setFormation($formation);
            $demande->setParcours(null);
            $demande->setAuteur($currentUser);
            $demande->setCampagneCollecte($this->getCampagneCollecte());
            $demande->setNiveauDemande($typeDpe);
            $demande->setArgumentaireDemande($request->request->getString('argumentaire_demande_reouverture'));
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $demande->setNiveauModification($etat);
            $this->entityManager->persist($demande);
            $this->entityManager->flush();

            // Mail au SES
            $dpeDemandeEvent = new DpeDemandeEvent($demande, $currentUser);
            $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);

            return JsonReponse::success($texte);
        }

        if ($this->isTurbo($request)) {
            return $this->turboStream->streamOpenModalFromTemplates(
                'Demande de réouverture de la mention',
                $formation->getDisplay(),
                'process_validation/_demande_reouverture_formation.html.twig',
                ['formation' => $formation],
                '_ui/_footer_cancel.html.twig',
            );
        }

        return $this->render('process_validation/_demande_reouverture_formation.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/demande/reouverture/cloture/{parcours}', name: 'app_validation_demande_reouverture_cloture')]
    public function demandeReouvertureCloture(
        DpeDemandeRepository  $dpeDemandeRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        Parcours            $parcours,
        VersioningParcours    $versioningParcours,
        VersioningFormation $versioningFormation,
        Request               $request
    ): Response {
        $dpeParcours = $dpeParcoursRepository->findLastDpeForParcours($parcours);
        if ($dpeParcours === null) {
            return JsonReponse::error('Parcours non trouvé');
        }

        // On récupère la demande la plus récente et non clôturée
        $demande = $dpeDemandeRepository->findLastUnclosedDemande($parcours);
        if ($demande === null && $parcours->isParcoursDefaut() && $parcours->getFormation() !== null) {
            $demande = $dpeDemandeRepository->findLastUnclosedDemandeMention($parcours->getFormation());
        }
        if ($demande === null) {
            $demande = $dpeParcours->etatDemande();
        }

        // Si le DPE est bien réouvert mais qu'aucune trace de demande n'a été créée,
        // on l'initialise à la volée pour permettre la clôture/annulation sans bloquer l'utilisateur
        if ($demande === null && $dpeParcours->isReouvert()) {
            $demande = new DpeDemande();
            $demande->setFormation($parcours->getFormation());
            $demande->setParcours($parcours);
            $demande->setCampagneCollecte($dpeParcours->getCampagneCollecte() ?? $this->getCampagneCollecte());
            $demande->setAuteur($this->getCurrentUserOrFail());
            $demande->setNiveauDemande('P');
            $demande->setNiveauModification($dpeParcours->getEtatReconduction());
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $demande->setArgumentaireDemande('Demande synchronisée automatiquement');
            $this->entityManager->persist($demande);
            $this->entityManager->flush();
        }

        if ($demande === null) {
            if ($request->isMethod('POST')) {
                return $this->handleClotureErrorParcours($parcours, 'Demande non trouvée', $request);
            }
            if ($this->isTurbo($request)) {
                return $this->turboStream->streamOpenModalFromTemplates(
                    'Annulation de la réouverture',
                    $parcours->getDisplay(),
                    'process_validation/_demande_introuvable.html.twig',
                    ['message' => 'Aucune demande de réouverture en cours de rédaction n\'a été trouvée pour ce parcours.'],
                    '_ui/_footer_cancel.html.twig',
                );
            }
            return $this->render('process_validation/_demande_introuvable.html.twig', [
                'message' => 'Aucune demande de réouverture en cours de rédaction n\'a été trouvée pour ce parcours.'
            ]);
        }

        // Calcul des différences de texte
        $textDifferencesParcours = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours);
        $version = $versioningParcours->hasLastVersion($parcours);

        $hasDifferences = false;
        foreach ($textDifferencesParcours as $value) {
            if (is_string($value) && trim(strip_tags($value)) !== '') {
                $hasDifferences = true;
                break;
            }
        }

        // Si c'est le parcours par défaut, tester aussi les différences au niveau de la formation
        if ($parcours->isParcoursDefaut() && $parcours->getFormation() !== null) {
            $textDifferencesFormation = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($parcours->getFormation());
            foreach ($textDifferencesFormation as $key => $value) {
                if (is_string($value) && trim(strip_tags($value)) !== '') {
                    $hasDifferences = true;
                    $textDifferencesParcours[$key] = $value;
                }
            }
        }

        if ($request->isMethod('POST')) {
            $dpe = GetDpeParcours::getFromParcours($parcours);
            if ($dpe === null) {
                return $this->handleClotureErrorParcours($parcours, 'DPE non trouvé', $request);
            }

            $currentUser = $this->getCurrentUserOrFail();
            $targetState = $this->resolveInitialStateForParcours($parcours);

            // Annulation de la réouverture :
            // Si des différences existent, rollback des modifications apportées
            if ($hasDifferences) {
                $versioningParcours->rollbackToLastVersion($parcours);
                if ($parcours->isParcoursDefaut() && $parcours->getFormation() !== null) {
                    $versioningFormation->rollbackToLastVersion($parcours->getFormation());
                }
            }

            $dpe->setEtatValidation([$targetState => 1]);
            $dpe->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

            $demande->setDateCloture(new DateTime('now'));
            $demande->setEtatDemande(EtatDpeEnum::tryFrom($targetState) ?? EtatDpeEnum::publie);
            $demande->setNiveauModification(TypeModificationDpeEnum::ANNULATION_REOUVERTURE);

            $histoEvent = new HistoriqueParcoursEvent($parcours, $currentUser, 'annulation_reouverture', 'valide', $request);
            $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);

            // Notification événementielle de clôture de la demande
            $this->eventDispatcher->dispatch(new DpeDemandeEvent($demande, $currentUser), DpeDemandeEvent::DPE_DEMANDE_CLOSED);

            $this->entityManager->flush();

            $messageSuccess = ($targetState === 'tacite_reconduction')
                ? 'La réouverture est annulée, le parcours est restauré en tacite reconduction.'
                : 'La réouverture est annulée, le parcours est restauré en état publié.';

            return $this->handleClotureSuccessParcours($parcours, $messageSuccess, $request);
        }

        $targetState = $this->resolveInitialStateForParcours($parcours);
        $context = [
            'parcours' => $parcours,
            'hasDifferences' => $hasDifferences,
            'type_modif' => $dpeParcours->getEtatReconduction()->value,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'hasLastVersion' => $version,
            'targetState' => $targetState,
        ];

        if ($this->isTurbo($request)) {
            return $this->turboStream->streamOpenModalFromTemplates(
                'Annulation de la réouverture',
                $parcours->getDisplay(),
                'process_validation/_demande_reouverture_cloture.html.twig',
                $context,
                '_ui/_footer_cancel.html.twig',
            );
        }

        return $this->render('process_validation/_demande_reouverture_cloture.html.twig', $context);
    }

    #[Route('/demande/reouverture-mention/cloture/{formation}', name: 'app_validation_demande_reouverture_cloture_mention')]
    public function demandeReouvertureClotureMention(
        DpeDemandeRepository $dpeDemandeRepository,
        Formation           $formation,
        VersioningFormation $versioningFormation,
        Request             $request
    ): Response {
        // On récupère la demande la plus récente et non clôturée
        $demande = $dpeDemandeRepository->findLastUnclosedDemandeMention($formation);

        // Si la mention est bien réouverte mais qu'aucune trace de demande n'a été créée,
        // on l'initialise à la volée pour permettre la clôture/annulation sans bloquer l'utilisateur
        if ($demande === null && $formation->getEtatReconduction() !== null && $formation->getEtatReconduction() !== TypeModificationDpeEnum::OUVERT) {
            $demande = new DpeDemande();
            $demande->setFormation($formation);
            $demande->setParcours(null);
            $demande->setCampagneCollecte($this->getCampagneCollecte());
            $demande->setAuteur($this->getCurrentUserOrFail());
            $demande->setNiveauDemande('F');
            $demande->setNiveauModification($formation->getEtatReconduction());
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $demande->setArgumentaireDemande('Demande mention synchronisée automatiquement');
            $this->entityManager->persist($demande);
            $this->entityManager->flush();
        }

        if ($demande === null) {
            if ($request->isMethod('POST')) {
                return $this->handleClotureErrorFormation($formation, 'Demande non trouvée', $request);
            }
            if ($this->isTurbo($request)) {
                return $this->turboStream->streamOpenModalFromTemplates(
                    'Annulation de la réouverture de la mention',
                    $formation->getDisplay(),
                    'process_validation/_demande_introuvable.html.twig',
                    ['message' => 'Aucune demande de réouverture en cours de rédaction n\'a été trouvée pour cette mention.'],
                    '_ui/_footer_cancel.html.twig',
                );
            }
            return $this->render('process_validation/_demande_introuvable.html.twig', [
                'message' => 'Aucune demande de réouverture en cours de rédaction n\'a été trouvée pour cette mention.'
            ]);
        }

        $textDifferencesParcours = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation);
        $version = $versioningFormation->hasLastVersion($formation);

        $hasDifferences = false;
        foreach ($textDifferencesParcours as $value) {
            if (is_string($value) && trim(strip_tags($value)) !== '') {
                $hasDifferences = true;
                break;
            }
        }

        if ($request->isMethod('POST')) {
            $currentUser = $this->getCurrentUserOrFail();
            $targetState = $this->resolveInitialStateForFormation($formation);

            // Annulation de la réouverture :
            // Si des différences existent, rollback des modifications apportées
            if ($hasDifferences) {
                $versioningFormation->rollbackToLastVersion($formation);
            }

            $formation->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            $dpe = GetDpeParcours::getFromFormation($formation);
            if ($dpe !== null) {
                $dpe->setEtatValidation([$targetState => 1]);
                $dpe->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            }

            $demande->setDateCloture(new DateTime('now'));
            $demande->setEtatDemande(EtatDpeEnum::tryFrom($targetState) ?? EtatDpeEnum::publie);
            $demande->setNiveauModification(TypeModificationDpeEnum::ANNULATION_REOUVERTURE);

            $histoEvent = new HistoriqueFormationEvent($formation, $currentUser, 'annulation_reouverture', 'valide', $request);
            $this->eventDispatcher->dispatch($histoEvent, HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);

            $this->eventDispatcher->dispatch(new DpeDemandeEvent($demande, $currentUser), DpeDemandeEvent::DPE_DEMANDE_CLOSED);
            $this->entityManager->flush();

            $messageSuccess = ($targetState === 'tacite_reconduction')
                ? 'La réouverture est annulée, la mention est restaurée en tacite reconduction.'
                : 'La réouverture est annulée, la mention est restaurée en état publié.';

            return $this->handleClotureSuccessFormation($formation, $messageSuccess, $request);
        }

        $targetState = $this->resolveInitialStateForFormation($formation);
        $context = [
            'formation' => $formation,
            'hasDifferences' => $hasDifferences,
            'type_modif' => $formation->getEtatReconduction()->value,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'hasLastVersion' => $version,
            'targetState' => $targetState,
        ];

        if ($this->isTurbo($request)) {
            return $this->turboStream->streamOpenModalFromTemplates(
                'Annulation de la réouverture de la mention',
                $formation->getDisplay(),
                'process_validation/_demande_reouverture_formation_cloture.html.twig',
                $context,
                '_ui/_footer_cancel.html.twig',
            );
        }

        return $this->render('process_validation/_demande_reouverture_formation_cloture.html.twig', $context);
    }

    private function resolveInitialStateForParcours(Parcours $parcours): string
    {
        /** @var list<HistoriqueParcours> $historiques */
        $historiques = $this->entityManager->getRepository(HistoriqueParcours::class)->findBy(
            ['parcours' => $parcours],
            ['id' => 'DESC'],
            20
        );

        foreach ($historiques as $histo) {
            $complements = $histo->getComplements() ?? [];
            if (!empty($complements['etat_depart']) && in_array($complements['etat_depart'], ['tacite_reconduction', 'publie'], true)) {
                return (string)$complements['etat_depart'];
            }
            if ($histo->getEtape() === 'tacite_reconduction' || in_array($histo->getEtape(), ['reouvrir_tacite_mccc', 'reouvrir_tacite_sans_cfvu'], true)) {
                return 'tacite_reconduction';
            }
            if (in_array($histo->getEtape(), ['publie', 'valide_a_publier', 'reouvrir_mccc', 'reouvrir_sans_cfvu'], true)) {
                return 'publie';
            }
        }

        return 'publie';
    }

    private function resolveInitialStateForFormation(Formation $formation): string
    {
        /** @var list<HistoriqueFormation> $historiques */
        $historiques = $this->entityManager->getRepository(HistoriqueFormation::class)->findBy(
            ['formation' => $formation],
            ['id' => 'DESC'],
            20
        );

        foreach ($historiques as $histo) {
            $complements = $histo->getComplements() ?? [];
            if (!empty($complements['etat_depart']) && in_array($complements['etat_depart'], ['tacite_reconduction', 'publie'], true)) {
                return (string)$complements['etat_depart'];
            }
            if ($histo->getEtape() === 'tacite_reconduction') {
                return 'tacite_reconduction';
            }
            if (in_array($histo->getEtape(), ['publie', 'valide_a_publier'], true)) {
                return 'publie';
            }
        }

        return 'publie';
    }

    private function handleClotureSuccessParcours(Parcours $parcours, string $message, Request $request): Response
    {
        $this->toast('success', $message);
        $redirectUrl = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);

        if ($this->isTurbo($request)) {
            /** @var DpeParcoursRepository $dpeParcoursRepository */
            $dpeParcoursRepository = $this->entityManager->getRepository(DpeParcours::class);
            $dpeParcours = $dpeParcoursRepository->findLastDpeForParcours($parcours);

            return $this->turboStream->stream('process_validation/turbo/cloture_success.stream.html.twig', [
                'redirectUrl' => $redirectUrl,
                'toastMessage' => $message,
                'parcours' => $parcours,
                'dpeParcours' => $dpeParcours,
            ]);
        }

        return $this->redirect($redirectUrl);
    }

    private function handleClotureErrorParcours(Parcours $parcours, string $errorMessage, Request $request): Response
    {
        $this->toast('danger', $errorMessage);
        $redirectUrl = $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]);

        if ($this->isTurbo($request)) {
            return $this->turboStream->streamToastError($errorMessage, true);
        }

        return $this->redirect($redirectUrl);
    }

    private function handleClotureSuccessFormation(Formation $formation, string $message, Request $request): Response
    {
        $this->toast('success', $message);
        $redirectUrl = $this->generateUrl('formation_v2_voir', ['slug' => $formation->getSlug()]);

        if ($this->isTurbo($request)) {
            return $this->turboStream->stream('process_validation/turbo/cloture_success.stream.html.twig', [
                'redirectUrl' => $redirectUrl,
                'toastMessage' => $message,
                'formation' => $formation,
            ]);
        }

        return $this->redirect($redirectUrl);
    }

    private function handleClotureErrorFormation(Formation $formation, string $errorMessage, Request $request): Response
    {
        $this->toast('danger', $errorMessage);
        $redirectUrl = $this->generateUrl('formation_v2_voir', ['slug' => $formation->getSlug()]);

        if ($this->isTurbo($request)) {
            return $this->turboStream->streamToastError($errorMessage, true);
        }

        return $this->redirect($redirectUrl);
    }

    private function isTurbo(Request $request): bool
    {
        return $request->getPreferredFormat() === 'turbo-stream'
            || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
            || $request->isXmlHttpRequest();
    }

    private function getCurrentUserOrFail(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur ORéOF requis.');
        }

        return $user;
    }
}
