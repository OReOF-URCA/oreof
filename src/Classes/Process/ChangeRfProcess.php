<?php

namespace App\Classes\Process;

use App\DTO\ProcessData;
use App\Entity\ChangeRf;
use App\Enums\TypeRfEnum;
use App\Events\AddCentreFormationEvent;
use App\Events\HistoriqueChangeRfEvent;
use App\Repository\ProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private WorkflowInterface $changeRfWorkflow,
    ) {
        parent::__construct($entityManager, $eventDispatcher, $translator);
    }

    public function etatChangeRf(ChangeRf $changeRf, array $process): ProcessData
    {
        $processData = new ProcessData();
        $processData->definition = $this->changeRfWorkflow->getDefinition();
        $processData->place = $this->changeRfWorkflow->getMarking($changeRf);
        $processData->transitions = $this->changeRfWorkflow->getEnabledTransitions($changeRf);

        return $processData;
    }

    /** @param array<string, mixed> $input */
    public function completeValidatedChangeRf(
        ChangeRf $changeRf,
        UserInterface $user,
        string $previousPlace,
        array $input = [],
        ?string $fileName = null,
        ?string $originalFileName = null,
    ): void {
        $newPlace = array_key_first($this->changeRfWorkflow->getMarking($changeRf)->getPlaces());
        if ('soumis_cfvu' === $newPlace) {
            $this->updateChangeRf($changeRf);
        }

        $this->completeChangeRf(
            $changeRf,
            $user,
            $previousPlace,
            $input,
            'valide',
            $fileName,
            $originalFileName,
        );
    }

    /** @param array<string, mixed> $input */
    public function completeReservedChangeRf(
        ChangeRf $changeRf,
        UserInterface $user,
        string $previousPlace,
        array $input = [],
    ): void {
        $this->completeChangeRf($changeRf, $user, $previousPlace, $input, 'reserve');
    }

    /** @param array<string, mixed> $input */
    private function completeChangeRf(
        ChangeRf $changeRf,
        UserInterface $user,
        string $previousPlace,
        array $input,
        string $historyState,
        ?string $fileName = null,
        ?string $originalFileName = null,
    ): void {
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(
            new HistoriqueChangeRfEvent(
                $changeRf,
                $user,
                $previousPlace,
                $historyState,
                $input,
                $fileName,
                $originalFileName,
            ),
            HistoriqueChangeRfEvent::ADD_HISTORIQUE_CHANGE_RF,
        );
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
