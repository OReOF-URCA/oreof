<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Process/ParcoursProcess.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/09/2023 10:46
 */

namespace App\Classes\Process;

use App\Classes\JsonReponse;
use App\DTO\ProcessData;
use App\Entity\ChangeRf;
use App\Enums\TypeRfEnum;
use App\Events\AddCentreFormationEvent;
use App\Events\HistoriqueChangeRfEvent;
use App\Events\NotifCentreFormationEvent;
use App\Repository\ProfilRepository;
use App\Utils\Tools;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangeRfProcess extends AbstractProcess
{
    public function __construct(
        protected ProfilRepository $profilRepository,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator,
        private WorkflowInterface  $changeRfWorkflow
    ) {
        parent::__construct($entityManager, $eventDispatcher, $translator);
    }

    public function etatChangeRf(ChangeRf $changeRf, $process): ProcessData
    {
        $processData = new ProcessData();
        $processData->definition = $this->changeRfWorkflow->getDefinition();
        $processData->place = $this->changeRfWorkflow->getMarking($changeRf);
        $processData->transitions = $this->changeRfWorkflow->getEnabledTransitions($changeRf);

        return $processData;
    }

    public function valideChangeRf(
        ChangeRf      $changeRf,
        UserInterface $user,
        string|array  $transition,
                      $request,
        ?string       $fileName = null,
        ?string       $originalFileName = null,
    ): Response
    {
        $valid = $transition;
        $motifs = [];
        $place = array_keys($this->changeRfWorkflow->getMarking($changeRf)->getPlaces())[0];

        if ($request->request->has('date') && $request->request->get('date') !== null && $request->request->get('date') !== 'null') {
            $motifs['date'] = Tools::convertDate($request->request->get('date'));
        }

        if ($transition === 'soumis_cfvu') {
            //si PV de CFVU
            $valid = 'valider_cfvu_avec_pv';
        }

        if ($request->request->has('argumentaire')) {
            $motifs['motif'] = $request->request->get('argumentaire', '');
        }

        if ($request->request->has('sousReserveConseil')) {
            $motifs['sousReserveConseil'] = (bool)$request->request->get('sousReserveConseil');
            $valid = 'reserver_cfvu';
        }

        $this->changeRfWorkflow->apply($changeRf, $valid, $motifs);

        //vérifier la place pour savoir si on doit envoyer une notification
        $newPlace = array_keys($this->changeRfWorkflow->getMarking($changeRf)->getPlaces())[0];
        if ($newPlace === 'soumis_cfvu') { // on applique les changements dès qu'on est soumis au CFVU
            $this->updateChangeRf($changeRf);
        }

        $this->entityManager->flush();

        return $this->dispatchEventChangeRf($changeRf, $user, $place, $request, 'valide', $fileName, $originalFileName);
    }

    public function reserveChangeRf(ChangeRf $changeRf, UserInterface $user, string|array $transition, $request): Response
    {
        $place = array_keys($this->changeRfWorkflow->getMarking($changeRf)->getPlaces())[0];

        $this->changeRfWorkflow->apply($changeRf, $transition, ['motif' => $request->request->get('argumentaire')]);
        $this->entityManager->flush();
        return $this->dispatchEventChangeRf($changeRf, $user, $place, $request, 'reserve');
    }

    //todo: traitement de la date de prise de fonction du RF et impact sur N+1 ? selon la date ? voir si pas fait en V1 ?

    private function dispatchEventChangeRf(
        ChangeRf      $changeRf,
        UserInterface $user,
        string        $place,
        Request       $request,
        string        $etat,
        ?string       $fileName = null,
        ?string       $originalFileName = null,
    ): Response
    {
        $histoEvent = new HistoriqueChangeRfEvent($changeRf, $user, $place, $etat, $request, $fileName, $originalFileName);
        $this->eventDispatcher->dispatch($histoEvent, HistoriqueChangeRfEvent::ADD_HISTORIQUE_CHANGE_RF);
        return JsonReponse::success($this->translator->trans('changeRf.'.$etat.'.' . $place . '.flash.success', [], 'process'));
    }

    private function updateChangeRf(ChangeRf $demande): void
    {
        $formation = $demande->getFormation();
        if (null === $formation) {
            return;
        }

        $this->applyChangeToFormation($demande, $formation);

        // Si la date de prise de fonction est antérieure ou égale à la date de fin de la campagne de l'année précédente
        // on l'applique aussi sur la formation de l'année précédente si elle existe.
        $campagne = $demande->getCampagneCollecte();
        if ($campagne && $demande->getDatePriseFonction()) {
            $formationPrecedente = $formation->getFormationOrigineCopie();
            if ($formationPrecedente) {
                $campagnePrecedente = $formationPrecedente->getDpe();
                if ($campagnePrecedente && $campagnePrecedente->getDateClotureDpe()) {
                    if ($demande->getDatePriseFonction() <= $campagnePrecedente->getDateClotureDpe()) {
                        $this->applyChangeToFormation($demande, $formationPrecedente);
                    }
                }
            }
        }
    }

    private function applyChangeToFormation(ChangeRf $demande, \App\Entity\Formation $formation): void
    {
        $isRf = $demande->getTypeRf() === TypeRfEnum::RF;
        $role = $isRf ? 'ROLE_RESP_FORMATION' : 'ROLE_CO_RESP_FORMATION';
        $setter = $isRf ? 'setResponsableMention' : 'setCoResponsable';

        // On retire le responsable actuel
        $profil = $this->profilRepository->findOneBy(['code' => $role]);
        if (null === $profil) {
            return;
        }

        $formation->$setter(null);

        if ($ancien = $demande->getAncienResponsable()) {
            $event = new AddCentreFormationEvent($formation, $ancien, $profil, $demande->getCampagneCollecte());
            $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::REMOVE_CENTRE_FORMATION);
        }

        // On ajoute le nouveau responsable
        if ($nouveau = $demande->getNouveauResponsable()) {
            $event = new AddCentreFormationEvent($formation, $nouveau, $profil, $demande->getCampagneCollecte());
            $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::ADD_CENTRE_FORMATION);

            $formation->$setter($nouveau);
        }
    }
}
