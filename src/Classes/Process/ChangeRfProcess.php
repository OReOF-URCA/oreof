<?php

namespace App\Classes\Process;

use App\DTO\ProcessData;
use App\Entity\ChangeRf;
use App\Entity\Formation;
use App\Enums\TypeRfEnum;
use App\Events\AddCentreFormationEvent;
use App\Events\HistoriqueChangeRfEvent;
use App\Repository\FormationRepository;
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
        private readonly FormationRepository $formationRepository,
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

    /**
     * Retourne toutes les formations liées dans la lignée (antécédents et duplications futures).
     *
     * @return Formation[]
     */
    public function getLigneeFormations(Formation $formation): array
    {
        $formations = [];
        $formations[$formation->getId()] = $formation;

        // Formations antérieures (remontée de la chaîne de copies)
        $curr = $formation;
        while (($parent = $curr->getFormationOrigineCopie()) !== null) {
            if (isset($formations[$parent->getId()])) {
                break;
            }
            $formations[$parent->getId()] = $parent;
            $curr = $parent;
        }

        // Formations postérieures (descente de la chaîne de copies)
        $queue = [$formation];
        while (!empty($queue)) {
            $item = array_shift($queue);
            $children = $this->formationRepository->findBy(['formationOrigineCopie' => $item]);
            foreach ($children as $child) {
                if (!isset($formations[$child->getId()])) {
                    $formations[$child->getId()] = $child;
                    $queue[] = $child;
                }
            }
        }

        return array_values($formations);
    }

    private function updateChangeRf(ChangeRf $demande): void
    {
        $formation = $demande->getFormation();
        if (null === $formation) {
            return;
        }

        $anneePriseFonction = $demande->getAnneeUniversitaireDebut();
        $lignee = $this->getLigneeFormations($formation);

        foreach ($lignee as $f) {
            $anneeF = $f->getDpe()?->getAnneeUniversitaire()?->getAnnee();
            if ($anneePriseFonction !== null && $anneeF !== null) {
                // On applique le changement pour toute formation dont l'année universitaire >= année de début de prise de fonction
                if ($anneeF >= $anneePriseFonction) {
                    $this->applyChangeToFormation($demande, $f);
                }
            } elseif ($f->getId() === $formation->getId()) {
                $this->applyChangeToFormation($demande, $f);
            }
        }
    }

    private function applyChangeToFormation(ChangeRf $demande, Formation $formation): void
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

        $campagne = $formation->getDpe() ?? $demande->getCampagneCollecte();

        if ($ancien = $demande->getAncienResponsable()) {
            $event = new AddCentreFormationEvent($formation, $ancien, $profil, $campagne);
            $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::REMOVE_CENTRE_FORMATION);
        }

        // On ajoute le nouveau responsable
        if ($nouveau = $demande->getNouveauResponsable()) {
            $event = new AddCentreFormationEvent($formation, $nouveau, $profil, $campagne);
            $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::ADD_CENTRE_FORMATION);

            $formation->$setter($nouveau);
        }
    }
}

